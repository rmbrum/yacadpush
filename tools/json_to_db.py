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

    # Sincronizar: também apagar do banco as publicações cujo DOI não está no JSON
    python json_to_db.py --update --prune publicacoes.json
    python json_to_db.py --prune --dry-run publicacoes.json   # só lista o que seria apagado
    # Por segurança, --prune recusa apagar mais de 10% das publicações do banco
    # (ajuste com --prune-max PCT) a menos que --force seja dado.
"""

import argparse
import json
import os
import sys
from datetime import datetime, timezone
from typing import Any

from dotenv import load_dotenv

load_dotenv()

# ---------------------------------------------------------------------------
# Conexão
# ---------------------------------------------------------------------------

def get_connection():
    # Imported here so that --dry-run works without the MySQL driver installed.
    import mysql.connector

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
    return datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S")


# ---------------------------------------------------------------------------
# Operações por tabela
# ---------------------------------------------------------------------------

def _ieee_links(related_identifiers: list[dict[str, Any]]) -> tuple[str, str]:
    """
    Return (html_url, pdf_url) from the IEEE Xplore related identifiers.

    ieee_to_zenodo.py writes the article page as `isAlternateIdentifier`
    (https://ieeexplore.ieee.org/document/N) and the PDF as `isIdenticalTo`
    (https://ieeexplore.ieee.org/stamp/stamp.jsp?tp=&arnumber=N), which
    contains neither ".pdf" nor "pdf", so the relation is what tells them apart.
    """
    html = pdf = ""
    for ri in related_identifiers:
        ident = ri.get("identifier", "")
        if "ieeexplore.ieee.org" not in ident:
            continue
        rel = ri.get("relation", "")
        if rel == "isIdenticalTo" or "/stamp/" in ident or ident.lower().endswith(".pdf"):
            pdf = pdf or ident
        else:
            html = html or ident
    return html, pdf


def _upsert_publication(
    cursor, entry: dict[str, Any], update: bool
) -> tuple[int | None, str]:
    """
    Insere ou atualiza a tabela publications.
    Returns (id, action) with action "insert", "update" or "skip" (id None:
    the DOI already exists and --update was not given).
    """
    meta = entry.get("zenodo_metadata", {})
    doi = entry.get("doi", "")

    # Verifica se já existe
    cursor.execute("SELECT id FROM publications WHERE doi = %s", (doi,))
    row = cursor.fetchone()
    existing_id = row[0] if row else None

    if existing_id and not update:
        return None, "skip"

    # Extrai campos escalares
    pub_date_raw = meta.get("publication_date", "")
    # Aceita só ano para MySQL DATE: converte para None e guarda em publication_date (VARCHAR)
    embargo_date = _parse_date(meta.get("embargo_date"))

    ieee_html, ieee_pdf = _ieee_links(meta.get("related_identifiers", []))

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
        return existing_id, "update"
    else:
        cols = ", ".join(f"`{k}`" for k in fields)
        placeholders = ", ".join(["%s"] * len(fields))
        cursor.execute(
            f"INSERT INTO publications ({cols}) VALUES ({placeholders})",
            list(fields.values()),
        )
        return cursor.lastrowid, "insert"


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

CHILD_TABLES = ("authors", "keywords", "related_identifiers", "communities")


def _prune_candidates(cursor, keep_dois: set[str]) -> tuple[list[tuple[int, str, str]], int]:
    """Publications whose DOI (case-insensitive) is not in keep_dois, and the table size."""
    cursor.execute("SELECT id, doi, title FROM publications")
    rows = cursor.fetchall()
    gone = [(pid, doi, title or "") for pid, doi, title in rows if (doi or "").strip().lower() not in keep_dois]
    return gone, len(rows)


def prune(
    cursor,
    conn,
    keep_dois: set[str],
    max_pct: float,
    force: bool,
    dry_run: bool,
) -> bool:
    """Delete publications (and their child rows) whose DOI is not in the JSON.

    Returns False when the safety stop prevented the deletion.
    """
    gone, total = _prune_candidates(cursor, keep_dois)
    pct = (100.0 * len(gone) / total) if total else 0.0
    print()
    print(f"Prune   : {len(gone)} de {total} publicações no banco não estão no JSON ({pct:.1f}%)")
    for _pid, doi, title in gone:
        print(f"  [{'dry-run' if dry_run else 'delete'}] {doi} | {title[:60]}")

    if not gone:
        return True
    if pct > max_pct and not force:
        print(
            f"PARADA DE SEGURANÇA: apagaria {pct:.1f}% das publicações (limite {max_pct:g}%). "
            "Nada foi apagado. Confira o JSON; para apagar mesmo assim, use --force "
            "(ou ajuste --prune-max)."
        )
        return False
    if dry_run:
        return True

    try:
        ids = [pid for pid, _doi, _title in gone]
        for start in range(0, len(ids), 500):
            chunk = ids[start:start + 500]
            marks = ",".join(["%s"] * len(chunk))
            # The schema cascades, but delete the children explicitly in case the
            # foreign keys were not created (e.g. MyISAM).
            for table in CHILD_TABLES:
                cursor.execute(f"DELETE FROM {table} WHERE publication_id IN ({marks})", chunk)
            cursor.execute(f"DELETE FROM publications WHERE id IN ({marks})", chunk)
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    print(f"Prune   : {len(gone)} publicações apagadas")
    return True


def load_json(
    json_file: str,
    update: bool = False,
    status_filter: str | None = None,
    dry_run: bool = False,
    do_prune: bool = False,
    prune_max: float = 10.0,
    force: bool = False,
) -> int:
    with open(json_file, encoding="utf-8") as f:
        data = json.load(f)

    all_entries = data.get("entries", [])
    entries = all_entries
    if status_filter:
        entries = [e for e in entries if e.get("status") == status_filter]

    print(f"Arquivo : {json_file}")
    print(f"Entradas: {len(all_entries)} total | {len(entries)} a processar")
    print(f"Modo    : {'dry-run' if dry_run else ('update' if update else 'insert-only')}"
          f"{' + prune' if do_prune else ''}")
    if status_filter:
        print(f"Filtro  : status = {status_filter}")
    print()

    keep_dois = {(e.get("doi") or "").strip().lower() for e in all_entries if (e.get("doi") or "").strip()}
    if do_prune and not keep_dois:
        print("ERRO: --prune com um JSON sem nenhum DOI apagaria o banco inteiro. Nada foi feito.")
        return 1

    if dry_run:
        for entry in entries:
            doi = entry.get("doi", "?")
            title = entry.get("zenodo_metadata", {}).get("title", "?")
            print(f"  [dry-run] {doi} | {title[:70]}")
        if do_prune:
            conn = get_connection()
            cursor = conn.cursor()
            ok = prune(cursor, conn, keep_dois, prune_max, force, dry_run=True)
            cursor.close()
            conn.close()
            return 0 if ok else 2
        return 0

    conn = get_connection()
    cursor = conn.cursor()

    inserted = updated = skipped = errors = 0

    for entry in entries:
        doi = entry.get("doi", "")
        title = entry.get("zenodo_metadata", {}).get("title", "")[:60]
        try:
            pub_id, action = _upsert_publication(cursor, entry, update)

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

    pruned_ok = True
    if do_prune:
        pruned_ok = prune(cursor, conn, keep_dois, prune_max, force, dry_run=False)

    cursor.close()
    conn.close()

    print()
    print(f"Concluído: {inserted} inseridos | {updated} atualizados | {skipped} ignorados | {errors} erros")
    if errors:
        return 1
    return 0 if pruned_ok else 2


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
    p.add_argument(
        "--prune", action="store_true",
        help="Apagar do banco as publicações (e seus autores, palavras-chave, identificadores "
             "e comunidades) cujo DOI não está no JSON",
    )
    p.add_argument(
        "--prune-max", metavar="PCT", type=float, default=10.0,
        help="Parada de segurança do --prune: não apagar mais que PCT%% das publicações (padrão: 10)",
    )
    p.add_argument(
        "--force", action="store_true",
        help="Com --prune, apagar mesmo acima do limite de --prune-max",
    )
    args = p.parse_args()

    if args.prune and args.status:
        p.error("--prune não pode ser combinado com --status: o JSON inteiro define o que fica no banco")

    sys.exit(load_json(
        json_file=args.json_file,
        update=args.update,
        status_filter=args.status,
        dry_run=args.dry_run,
        do_prune=args.prune,
        prune_max=args.prune_max,
        force=args.force,
    ))


if __name__ == "__main__":
    main()
