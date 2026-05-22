#!/usr/bin/env python3
"""
json_to_db.py
=============
Carrega (ou atualiza) publicações do JSON intermediário no banco MySQL.

Dependências:
    pip install mysql-connector-python python-dotenv

Configuração (.env):
    DB_HOST     - host do MySQL       (padrão: localhost)
    DB_PORT     - porta               (padrão: 3306)
    DB_NAME     - nome do banco
    DB_USER     - usuário
    DB_PASSWORD - senha

Uso:
    # Inserir entradas novas (ignora as já existentes pelo DOI)
    python json_to_db.py publicacoes.json

    # Inserir E atualizar entradas já existentes
    python json_to_db.py --update publicacoes.json

    # Reprocessar apenas as entradas com status específico
    python json_to_db.py --status pending publicacoes.json
    python json_to_db.py --update --status error publicacoes.json

    # Pré-visualizar sem alterar o banco
    python json_to_db.py --dry-run publicacoes.json
"""

import argparse
import json
import os
import sys
from datetime import datetime
from typing import Any

import mysql.connector
from dotenv import load_dotenv

load_dotenv()

# ---------------------------------------------------------------------------
# Conexão
# ---------------------------------------------------------------------------

def get_connection():
    return mysql.connector.connect(
        host=os.getenv("DB_HOST", "localhost"),
        port=int(os.getenv("DB_PORT", 3306)),
        database=os.getenv("DB_NAME"),
        user=os.getenv("DB_USER"),
        password=os.getenv("DB_PASSWORD"),
        charset="utf8mb4",
    )


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def _parse_date(value: str | None) -> str | None:
    """Converte datas parciais (YYYY, YYYY-MM) para YYYY-MM-DD aceitável pelo MySQL."""
    if not value:
        return None
    parts = value.split("-")
    if len(parts) == 1:
        return f"{parts[0]}-01-01"
    if len(parts) == 2:
        return f"{parts[0]}-{parts[1]}-01"
    return value[:10]  # trunca se vier com hora


def _now() -> str:
    return datetime.utcnow().strftime("%Y-%m-%d %H:%M:%S")


# ---------------------------------------------------------------------------
# Operações por tabela
# ---------------------------------------------------------------------------

def _upsert_publication(cursor, entry: dict[str, Any], update: bool) -> int | None:
    """
    Insere ou atualiza a tabela publications.
    Retorna o id do registro, ou None se foi ignorado (sem --update).
    """
    meta = entry.get("zenodo_metadata", {})
    doi = entry.get("doi", "")

    # Verifica se já existe
    cursor.execute("SELECT id FROM publications WHERE doi = %s", (doi,))
    row = cursor.fetchone()
    existing_id = row[0] if row else None

    if existing_id and not update:
        return None  # ignora

    # Extrai campos escalares
    pub_date_raw = meta.get("publication_date", "")
    # Aceita só ano para MySQL DATE: converte para None e guarda em publication_date (VARCHAR)
    embargo_date = _parse_date(meta.get("embargo_date"))

    ieee_html = ""
    ieee_pdf = ""
    for ri in meta.get("related_identifiers", []):
        rel = ri.get("relation", "")
        ident = ri.get("identifier", "")
        if "ieeexplore.ieee.org" in ident:
            if ri.get("resource_type") == "publication-article":
                if rel == "isAlternateIdentifier":
                    ieee_html = ident
                elif rel == "isIdenticalTo" and ident.endswith(".pdf") or "pdf" in ident.lower():
                    ieee_pdf = ident
        # fallback: qualquer ieee link não classificado
        if "ieeexplore.ieee.org" in ident and not ieee_html and not ieee_pdf:
            ieee_html = ident

    collected_at = None
    if entry.get("collected_at"):
        try:
            collected_at = datetime.fromisoformat(
                entry["collected_at"].replace("Z", "+00:00")
            ).strftime("%Y-%m-%d %H:%M:%S")
        except ValueError:
            pass

    fields = {
        "doi":                doi,
        "zenodo_deposit_id":  entry.get("zenodo_deposit_id"),
        "zenodo_doi":         entry.get("zenodo_doi"),
        "zenodo_deposit_url": entry.get("zenodo_deposit_url"),
        "title":              (meta.get("title") or "")[:65535],
        "description":        meta.get("description"),
        "upload_type":        meta.get("upload_type", "publication"),
        "publication_date":   pub_date_raw or None,
        "publisher":          meta.get("publisher"),
        "journal_title":      meta.get("journal_title"),
        "journal_volume":     meta.get("journal_volume"),
        "journal_issue":      meta.get("journal_issue"),
        "journal_pages":      meta.get("journal_pages"),
        "issn":               meta.get("issn"),
        "conference_title":   meta.get("conference_title"),
        "conference_place":   meta.get("conference_place"),
        "conference_dates":   meta.get("conference_dates"),
        "isbn":               meta.get("isbn"),
        "access_right":       meta.get("access_right", "open"),
        "embargo_date":       embargo_date,
        "access_conditions":  meta.get("access_conditions"),
        "ieee_html_url":      ieee_html or None,
        "ieee_pdf_url":       ieee_pdf or None,
        "status":             entry.get("status", "pending"),
        "error_message":      entry.get("error"),
        "collected_at":       collected_at,
        "imported_at":        _now(),
    }

    if existing_id:
        # UPDATE — não toca em campos de controle de importação desnecessariamente
        set_clause = ", ".join(f"`{k}` = %s" for k in fields if k != "doi")
        values = [v for k, v in fields.items() if k != "doi"] + [existing_id]
        cursor.execute(
            f"UPDATE publications SET {set_clause} WHERE id = %s",
            values,
        )
        return existing_id
    else:
        cols = ", ".join(f"`{k}`" for k in fields)
        placeholders = ", ".join(["%s"] * len(fields))
        cursor.execute(
            f"INSERT INTO publications ({cols}) VALUES ({placeholders})",
            list(fields.values()),
        )
        return cursor.lastrowid


def _replace_authors(cursor, pub_id: int, meta: dict[str, Any]) -> None:
    cursor.execute("DELETE FROM authors WHERE publication_id = %s", (pub_id,))
    for pos, creator in enumerate(meta.get("creators", [])):
        cursor.execute(
            """INSERT INTO authors
               (publication_id, position, full_name, affiliation, orcid, email)
               VALUES (%s, %s, %s, %s, %s, %s)""",
            (
                pub_id,
                pos,
                creator.get("name", ""),
                creator.get("affiliation"),
                creator.get("orcid"),
                creator.get("email"),
            ),
        )


def _replace_keywords(cursor, pub_id: int, meta: dict[str, Any]) -> None:
    cursor.execute("DELETE FROM keywords WHERE publication_id = %s", (pub_id,))
    for kw in meta.get("keywords", []):
        if kw:
            cursor.execute(
                "INSERT INTO keywords (publication_id, keyword) VALUES (%s, %s)",
                (pub_id, kw[:512]),
            )


def _replace_related_identifiers(cursor, pub_id: int, meta: dict[str, Any]) -> None:
    cursor.execute("DELETE FROM related_identifiers WHERE publication_id = %s", (pub_id,))
    for ri in meta.get("related_identifiers", []):
        cursor.execute(
            """INSERT INTO related_identifiers
               (publication_id, identifier, relation, resource_type)
               VALUES (%s, %s, %s, %s)""",
            (
                pub_id,
                ri.get("identifier", "")[:1024],
                ri.get("relation", ""),
                ri.get("resource_type"),
            ),
        )


def _replace_communities(cursor, pub_id: int, meta: dict[str, Any]) -> None:
    cursor.execute("DELETE FROM communities WHERE publication_id = %s", (pub_id,))
    for comm in meta.get("communities", []):
        cid = comm.get("identifier", "")
        if cid:
            cursor.execute(
                "INSERT INTO communities (publication_id, community_id) VALUES (%s, %s)",
                (pub_id, cid),
            )


# ---------------------------------------------------------------------------
# Fluxo principal
# ---------------------------------------------------------------------------

def load_json(
    json_file: str,
    update: bool = False,
    status_filter: str | None = None,
    dry_run: bool = False,
) -> None:
    with open(json_file, encoding="utf-8") as f:
        data = json.load(f)

    entries = data.get("entries", [])
    if status_filter:
        entries = [e for e in entries if e.get("status") == status_filter]

    print(f"Arquivo : {json_file}")
    print(f"Entradas: {len(data.get('entries', []))} total | {len(entries)} a processar")
    print(f"Modo    : {'dry-run' if dry_run else ('update' if update else 'insert-only')}")
    if status_filter:
        print(f"Filtro  : status = {status_filter}")
    print()

    if dry_run:
        for entry in entries:
            doi = entry.get("doi", "?")
            title = entry.get("zenodo_metadata", {}).get("title", "?")
            print(f"  [dry-run] {doi} | {title[:70]}")
        return

    conn = get_connection()
    cursor = conn.cursor()

    inserted = updated = skipped = errors = 0

    for entry in entries:
        doi = entry.get("doi", "")
        title = entry.get("zenodo_metadata", {}).get("title", "")[:60]
        try:
            pub_id = _upsert_publication(cursor, entry, update)

            if pub_id is None:
                print(f"  [skip]   {doi}")
                skipped += 1
                continue

            meta = entry.get("zenodo_metadata", {})
            _replace_authors(cursor, pub_id, meta)
            _replace_keywords(cursor, pub_id, meta)
            _replace_related_identifiers(cursor, pub_id, meta)
            _replace_communities(cursor, pub_id, meta)

            conn.commit()

            # Determina se foi insert ou update
            cursor.execute(
                "SELECT COUNT(*) FROM publications WHERE id = %s AND imported_at = updated_at",
                (pub_id,),
            )
            action = "insert"
            # Simplificado: se o registro já existia antes, é update
            if entry.get("status") in ("uploaded", "published") and entry.get("zenodo_deposit_id"):
                action = "update"

            if action == "update":
                print(f"  [update] {doi} | {title}")
                updated += 1
            else:
                print(f"  [insert] {doi} | {title}")
                inserted += 1

        except Exception as e:
            conn.rollback()
            print(f"  [error]  {doi} | {e}")
            errors += 1

    cursor.close()
    conn.close()

    print()
    print(f"Concluído: {inserted} inseridos | {updated} atualizados | {skipped} ignorados | {errors} erros")


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def main() -> None:
    p = argparse.ArgumentParser(
        description="Carrega publicações do JSON intermediário no banco MySQL.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    p.add_argument("json_file", help="Caminho para o arquivo JSON intermediário")
    p.add_argument(
        "--update", action="store_true",
        help="Atualizar entradas já existentes (padrão: ignorar duplicatas por DOI)",
    )
    p.add_argument(
        "--status", metavar="STATUS",
        help="Processar apenas entradas com este status (ex: pending, error)",
    )
    p.add_argument(
        "--dry-run", action="store_true",
        help="Exibir o que seria feito sem alterar o banco",
    )
    args = p.parse_args()

    load_json(
        json_file=args.json_file,
        update=args.update,
        status_filter=args.status,
        dry_run=args.dry_run,
    )


if __name__ == "__main__":
    main()
