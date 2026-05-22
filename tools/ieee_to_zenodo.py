#!/usr/bin/env python3
"""
ieee_to_zenodo.py
=================
Importa artigos do IEEEXplore para o Zenodo a partir de DOIs.

Dependências:
    pip install requests python-dotenv

Configuração (.env ou variáveis de ambiente):
    IEEE_API_KEY        - Chave da API IEEEXplore  (https://developer.ieee.org/)
    ZENODO_TOKEN        - Token de acesso do Zenodo (https://zenodo.org/account/settings/applications/)
    ZENODO_SANDBOX      - "true" para usar sandbox.zenodo.org (padrão: false)
    ZENODO_COMMUNITIES  - IDs de comunidades separados por vírgula (ex: "minha-comunidade,outra")

Modos de uso
------------

  MODO 1 — Coletar metadados e salvar em JSON intermediário:
    python ieee_to_zenodo.py --export saida.json 10.1109/TPAMI.2021.3059968
    python ieee_to_zenodo.py --export saida.json --file lista_dois.txt

  MODO 2 — Inserir no Zenodo a partir do JSON intermediário:
    python ieee_to_zenodo.py --import saida.json
    python ieee_to_zenodo.py --import saida.json --publish

  MODO 3 — Fluxo direto (coleta + inserção, sem JSON intermediário):
    python ieee_to_zenodo.py 10.1109/TPAMI.2021.3059968
    python ieee_to_zenodo.py --publish --file lista_dois.txt

  MODO 4 — Apenas pré-visualizar metadados sem criar nada:
    python ieee_to_zenodo.py --dry-run 10.1109/TPAMI.2021.3059968

Outras opções:
    --community ID      Submeter a uma comunidade Zenodo específica
    --access TYPE       Controle de acesso: open (padrão), embargoed, restricted, closed
    --embargo-date DATE Data de embargo no formato YYYY-MM-DD (obrigatório se --access embargoed)
    --access-conditions TEXTO  Condições de acesso (obrigatório se --access restricted)
    --output FILE       Salvar resultados da inserção em JSON
"""

import argparse
import json
import os
import sys
import time
from datetime import datetime
from typing import Any

import requests
from dotenv import load_dotenv

load_dotenv()

# ---------------------------------------------------------------------------
# Configuração
# ---------------------------------------------------------------------------

IEEE_API_KEY: str = os.getenv("IEEE_API_KEY", "")
ZENODO_TOKEN: str = os.getenv("ZENODO_TOKEN", "")
ZENODO_SANDBOX: bool = os.getenv("ZENODO_SANDBOX", "false").lower() == "true"
ZENODO_COMMUNITIES: list[str] = [
    c.strip()
    for c in os.getenv("ZENODO_COMMUNITIES", "").split(",")
    if c.strip()
]

# Endpoint correto da API IEEEXplore
IEEE_SEARCH_URL = "https://ieeexploreapi.ieee.org/api/v1/search/articles"
CROSSREF_URL = "https://api.crossref.org/works"

ZENODO_BASE_URL = (
    "https://sandbox.zenodo.org/api" if ZENODO_SANDBOX else "https://zenodo.org/api"
)

REQUEST_DELAY = 1.0  # segundos entre requisições

# Versão do formato JSON intermediário — permite detectar arquivos desatualizados
INTERMEDIATE_FORMAT_VERSION = "1.0"


# ---------------------------------------------------------------------------
# Helpers HTTP
# ---------------------------------------------------------------------------

def _get(url: str, params: dict | None = None, headers: dict | None = None) -> dict:
    resp = requests.get(url, params=params, headers=headers, timeout=30)
    if not resp.ok:
        raise RuntimeError(
            f"GET {url} retornou {resp.status_code}: {resp.text[:200]}"
        )
    return resp.json()


def _post(url: str, payload: dict, headers: dict) -> dict:
    resp = requests.post(url, json=payload, headers=headers, timeout=30)
    if not resp.ok:
        raise RuntimeError(
            f"POST {url} retornou {resp.status_code}: {resp.text[:400]}"
        )
    return resp.json()


def _put(url: str, payload: dict, headers: dict) -> dict:
    resp = requests.put(url, json=payload, headers=headers, timeout=30)
    if not resp.ok:
        raise RuntimeError(
            f"PUT {url} retornou {resp.status_code}: {resp.text[:400]}"
        )
    return resp.json()


# ---------------------------------------------------------------------------
# IEEEXplore
# ---------------------------------------------------------------------------

def fetch_ieee_metadata(doi: str) -> dict[str, Any]:
    """Busca metadados no IEEEXplore pelo DOI."""
    if not IEEE_API_KEY:
        raise RuntimeError(
            "IEEE_API_KEY não configurada. "
            "Obtenha em https://developer.ieee.org/ e defina no .env"
        )
    params = {"doi": doi, "apikey": IEEE_API_KEY}
    data = _get(IEEE_SEARCH_URL, params=params)
    articles = data.get("articles", [])
    if not articles:
        raise ValueError(f"Nenhum artigo encontrado no IEEEXplore para DOI: {doi}")
    return articles[0]


# ---------------------------------------------------------------------------
# CrossRef (fallback)
# ---------------------------------------------------------------------------

def fetch_crossref_metadata(doi: str) -> dict[str, Any]:
    """Busca metadados no CrossRef pelo DOI (usado como complemento)."""
    url = f"{CROSSREF_URL}/{doi}"
    try:
        data = _get(url)
        return data.get("message", {})
    except Exception:
        return {}


# ---------------------------------------------------------------------------
# Conversão para formato Zenodo
# ---------------------------------------------------------------------------

PUBLICATION_TYPE_MAP = {
    "Journals": "article",
    "Conference Publications": "conferencepaper",
    "Books": "book",
    "Magazines": "article",
    "Standards": "report",
    "Early Access Articles": "article",
}


def _extract_authors(ieee_data: dict, crossref_data: dict) -> list[dict]:
    """Extrai autores com ORCID e afiliação."""
    authors = []
    ieee_authors = ieee_data.get("authors", {}).get("authors", [])

    for a in ieee_authors:
        entry: dict[str, Any] = {"name": a.get("full_name", "").strip()}
        if a.get("affiliation"):
            entry["affiliation"] = a["affiliation"]
        if a.get("authorUrl"):
            # ORCID pode estar em authorUrl
            url = a["authorUrl"]
            if "orcid.org" in url:
                entry["orcid"] = url.split("orcid.org/")[-1].strip()
        if a.get("orcid"):
            entry["orcid"] = a["orcid"].strip()
        authors.append(entry)

    # Se IEEE não retornou autores, tenta CrossRef
    if not authors:
        for a in crossref_data.get("author", []):
            name = f"{a.get('family', '')}, {a.get('given', '')}".strip(", ")
            entry = {"name": name}
            if a.get("affiliation"):
                affs = [af.get("name", "") for af in a["affiliation"] if af.get("name")]
                if affs:
                    entry["affiliation"] = "; ".join(affs)
            if a.get("ORCID"):
                entry["orcid"] = a["ORCID"].split("orcid.org/")[-1].strip()
            authors.append(entry)

    return authors


def _extract_keywords(ieee_data: dict, crossref_data: dict) -> list[str]:
    """Extrai todas as palavras-chave disponíveis."""
    kws: set[str] = set()
    index_terms = ieee_data.get("index_terms", {})
    for source in index_terms.values():
        for term in source.get("terms", []):
            kws.add(term)
    # CrossRef: subject (classificação de área) e keyword
    for kw in crossref_data.get("subject", []):
        kws.add(kw)
    for kw in crossref_data.get("keyword", []):
        kws.add(kw)
    return sorted(kws)


def _publication_date(ieee_data: dict, crossref_data: dict) -> str:
    """Retorna a data de publicação no formato YYYY-MM-DD ou YYYY."""
    # Tenta ano do IEEEXplore
    year = ieee_data.get("publication_year") or ieee_data.get("publication_date", "")[:4]
    if not year:
        # Tenta CrossRef
        dp = crossref_data.get("published-print") or crossref_data.get("published-online")
        if dp:
            parts = dp.get("date-parts", [[]])[0]
            year = str(parts[0]) if parts else ""
    return str(year) if year else ""


def build_zenodo_metadata(
    ieee_data: dict,
    crossref_data: dict | None = None,
    communities: list[str] | None = None,
    access_right: str = "open",
    embargo_date: str | None = None,
    access_conditions: str | None = None,
) -> dict[str, Any]:
    """Converte metadados IEEE/CrossRef para o formato esperado pela API do Zenodo."""
    if crossref_data is None:
        crossref_data = {}

    pub_type = PUBLICATION_TYPE_MAP.get(
        ieee_data.get("publication_type", ""), "publication"
    )
    authors = _extract_authors(ieee_data, crossref_data)
    keywords = _extract_keywords(ieee_data, crossref_data)
    pub_date = _publication_date(ieee_data, crossref_data)

    # Identificadores relacionados
    related: list[dict] = []
    doi = ieee_data.get("doi", "")
    if doi:
        related.append({"identifier": f"https://doi.org/{doi}", "relation": "isIdenticalTo"})

    # Página do artigo no IEEExplore
    html_url = ieee_data.get("html_url", "")
    if html_url:
        related.append({
            "identifier": html_url,
            "relation": "isAlternateIdentifier",
            "resource_type": "publication-article",
        })

    # PDF do artigo no IEEExplore (link direto para o arquivo)
    pdf_url = ieee_data.get("pdf_url", "")
    if pdf_url and pdf_url != html_url:
        related.append({
            "identifier": pdf_url,
            "relation": "isIdenticalTo",
            "resource_type": "publication-article",
        })

    metadata: dict[str, Any] = {
        "upload_type": pub_type,
        "title": ieee_data.get("title", "").strip(),
        "description": ieee_data.get("abstract", "Sem resumo disponível.").strip(),
        "creators": [
            {k: v for k, v in a.items() if v}
            for a in authors
        ],
        "publication_date": pub_date,
        "access_right": access_right,
    }

    if keywords:
        metadata["keywords"] = keywords

    if doi:
        metadata["doi"] = doi

    # Informações da publicação
    pub_title = ieee_data.get("publication_title", "")
    if pub_title:
        if pub_type == "article":
            metadata["journal_title"] = pub_title
            if ieee_data.get("volume"):
                metadata["journal_volume"] = str(ieee_data["volume"])
            if ieee_data.get("issue"):
                metadata["journal_issue"] = str(ieee_data["issue"])
            if ieee_data.get("start_page"):
                pages = ieee_data["start_page"]
                if ieee_data.get("end_page"):
                    pages += f"-{ieee_data['end_page']}"
                metadata["journal_pages"] = pages
        elif pub_type == "conferencepaper":
            metadata["conference_title"] = pub_title
            conf_loc = ieee_data.get("conference_location", "")
            conf_dates = ieee_data.get("conference_dates", "")
            if conf_loc:
                metadata["conference_place"] = conf_loc
            if conf_dates:
                metadata["conference_dates"] = conf_dates

    if ieee_data.get("publisher"):
        metadata["publisher"] = ieee_data["publisher"]

    if ieee_data.get("isbn") or ieee_data.get("issn"):
        isbn = ieee_data.get("isbn", {})
        if isinstance(isbn, dict):
            for v in isbn.values():
                metadata.setdefault("isbn", v)
                break

    if related:
        metadata["related_identifiers"] = related

    # Comunidades
    all_communities = list(ZENODO_COMMUNITIES)
    if communities:
        for c in communities:
            if c not in all_communities:
                all_communities.append(c)
    if all_communities:
        metadata["communities"] = [{"identifier": c} for c in all_communities]

    # Controle de acesso
    if access_right == "embargoed":
        metadata["embargo_date"] = embargo_date
        metadata["license"] = "cc-by-4.0"
    elif access_right == "restricted":
        metadata["access_conditions"] = access_conditions
    elif access_right == "open":
        metadata["license"] = "cc-by-4.0"

    return metadata


# ---------------------------------------------------------------------------
# Formato JSON intermediário
# ---------------------------------------------------------------------------

def collect_entry(
    doi: str,
    communities: list[str] | None = None,
    access_right: str = "open",
    embargo_date: str | None = None,
    access_conditions: str | None = None,
) -> dict[str, Any]:
    """
    Coleta metadados de um DOI (IEEE + CrossRef) e retorna uma entrada
    no formato intermediário, pronta para ser salva em JSON.
    """
    print(f"  → IEEEXplore...")
    ieee_data = fetch_ieee_metadata(doi)
    print(f"    Título : {ieee_data.get('title', 'N/A')}")
    print(f"    Tipo   : {ieee_data.get('publication_type', 'N/A')}")
    print(f"    Autores: {len(ieee_data.get('authors', {}).get('authors', []))}")

    print(f"  → CrossRef (complemento)...")
    time.sleep(REQUEST_DELAY)
    crossref_data = fetch_crossref_metadata(doi)

    metadata = build_zenodo_metadata(
        ieee_data,
        crossref_data,
        communities=communities,
        access_right=access_right,
        embargo_date=embargo_date,
        access_conditions=access_conditions,
    )

    return {
        "doi": doi,
        "collected_at": datetime.utcnow().isoformat() + "Z",
        "ieee_raw": ieee_data,       # dados brutos IEEE para referência/reprocessamento
        "crossref_raw": crossref_data,
        "zenodo_metadata": metadata,  # payload pronto para envio
        "status": "pending",          # pending | uploaded | published | error
        "zenodo_deposit_id": None,
        "zenodo_deposit_url": None,
        "zenodo_doi": None,
        "error": None,
    }


def export_to_json(dois: list[str], output_file: str, args: argparse.Namespace) -> None:
    """
    Coleta metadados de todos os DOIs e salva no arquivo JSON intermediário.
    Entradas já existentes no arquivo são preservadas (merge por DOI).
    """
    # Carrega arquivo existente, se houver
    existing: dict[str, dict] = {}
    if os.path.exists(output_file):
        with open(output_file, encoding="utf-8") as f:
            data = json.load(f)
        for entry in data.get("entries", []):
            existing[entry["doi"]] = entry
        print(f"Arquivo existente carregado: {len(existing)} entradas.")

    communities = args.community if args.community else []
    access_right = args.access
    embargo_date = getattr(args, "embargo_date", None)
    access_conditions = getattr(args, "access_conditions", None)

    results = []
    errors = []

    for doi in dois:
        print(f"\n[{dois.index(doi)+1}/{len(dois)}] DOI: {doi}")
        if doi in existing:
            print(f"  ⚠ Já existe no arquivo — ignorando (use --force para sobrescrever).")
            results.append(existing[doi])
            continue
        try:
            entry = collect_entry(
                doi,
                communities=communities,
                access_right=access_right,
                embargo_date=embargo_date,
                access_conditions=access_conditions,
            )
            results.append(entry)
            print(f"  ✓ Coletado.")
        except Exception as e:
            print(f"  ✗ Erro: {e}")
            errors.append({"doi": doi, "error": str(e)})
        time.sleep(REQUEST_DELAY)

    # Mescla preservando entradas existentes não reprocessadas
    doi_set = {e["doi"] for e in results}
    for doi, entry in existing.items():
        if doi not in doi_set:
            results.append(entry)

    output = {
        "format_version": INTERMEDIATE_FORMAT_VERSION,
        "generated_at": datetime.utcnow().isoformat() + "Z",
        "total": len(results),
        "entries": results,
    }

    with open(output_file, "w", encoding="utf-8") as f:
        json.dump(output, f, ensure_ascii=False, indent=2)

    print(f"\n✓ JSON salvo em: {output_file}")
    print(f"  {len(results)} entrada(s) | {len(errors)} erro(s)")
    if errors:
        print("  Erros:")
        for e in errors:
            print(f"    {e['doi']}: {e['error']}")


def import_from_json(input_file: str, args: argparse.Namespace) -> None:
    """
    Lê o JSON intermediário e envia as entradas pendentes ao Zenodo.
    Atualiza o arquivo com os resultados (deposit_id, URL, status).
    """
    if not os.path.exists(input_file):
        print(f"Arquivo não encontrado: {input_file}", file=sys.stderr)
        sys.exit(1)

    with open(input_file, encoding="utf-8") as f:
        data = json.load(f)

    version = data.get("format_version")
    if version != INTERMEDIATE_FORMAT_VERSION:
        print(f"⚠ Versão do arquivo ({version}) difere da esperada ({INTERMEDIATE_FORMAT_VERSION}).")

    entries = data.get("entries", [])
    pending = [e for e in entries if e.get("status") == "pending"]
    already_done = len(entries) - len(pending)

    print(f"Arquivo: {input_file}")
    print(f"Total de entradas : {len(entries)}")
    print(f"Já processadas    : {already_done}")
    print(f"Pendentes         : {len(pending)}")

    if not pending:
        print("Nenhuma entrada pendente. Nada a fazer.")
        return

    publish = getattr(args, "publish", False)
    output_results = getattr(args, "output", None)
    upload_results = []

    for i, entry in enumerate(entries):
        if entry.get("status") != "pending":
            continue

        doi = entry["doi"]
        print(f"\n[{i+1}/{len(entries)}] DOI: {doi}")
        try:
            result = upload_to_zenodo(entry["zenodo_metadata"], publish=publish)
            entry["status"] = "published" if publish else "uploaded"
            entry["zenodo_deposit_id"] = result["deposit_id"]
            entry["zenodo_deposit_url"] = result["deposit_url"]
            entry["zenodo_doi"] = result.get("zenodo_doi")
            entry["uploaded_at"] = datetime.utcnow().isoformat() + "Z"
            entry["error"] = None
            print(f"  ✓ {'Publicado' if publish else 'Rascunho criado'}: {result['deposit_url']}")
            upload_results.append({**result, "doi": doi, "status": entry["status"]})
        except Exception as e:
            entry["status"] = "error"
            entry["error"] = str(e)
            print(f"  ✗ Erro: {e}")
        time.sleep(REQUEST_DELAY)

    # Salva o JSON atualizado com os resultados
    data["last_import_at"] = datetime.utcnow().isoformat() + "Z"
    with open(input_file, "w", encoding="utf-8") as f:
        json.dump(data, f, ensure_ascii=False, indent=2)
    print(f"\n✓ Arquivo atualizado: {input_file}")

    if output_results and upload_results:
        with open(output_results, "w", encoding="utf-8") as f:
            json.dump(upload_results, f, ensure_ascii=False, indent=2)
        print(f"✓ Resultados salvos em: {output_results}")


# ---------------------------------------------------------------------------
# Zenodo
# ---------------------------------------------------------------------------

def _zenodo_headers() -> dict:
    if not ZENODO_TOKEN:
        raise RuntimeError(
            "ZENODO_TOKEN não configurado. "
            "Obtenha em https://zenodo.org/account/settings/applications/"
        )
    return {"Authorization": f"Bearer {ZENODO_TOKEN}", "Content-Type": "application/json"}


def upload_to_zenodo(metadata: dict[str, Any], publish: bool = False) -> dict[str, Any]:
    """Cria um depósito no Zenodo e, opcionalmente, publica."""
    headers = _zenodo_headers()

    # Criar rascunho
    url = f"{ZENODO_BASE_URL}/deposit/depositions"
    deposit = _post(url, {"metadata": metadata}, headers)
    deposit_id = deposit["id"]
    deposit_url = deposit.get("links", {}).get("html", "")

    result: dict[str, Any] = {
        "deposit_id": deposit_id,
        "deposit_url": deposit_url,
        "zenodo_doi": None,
    }

    if publish:
        pub_url = f"{ZENODO_BASE_URL}/deposit/depositions/{deposit_id}/actions/publish"
        pub = requests.post(pub_url, headers=headers, timeout=30)
        pub.raise_for_status()
        result["zenodo_doi"] = pub.json().get("doi", "")

    return result


# ---------------------------------------------------------------------------
# Fluxo direto (sem JSON intermediário)
# ---------------------------------------------------------------------------

def process_doi_direct(
    doi: str,
    communities: list[str],
    access_right: str,
    embargo_date: str | None,
    access_conditions: str | None,
    dry_run: bool,
    publish: bool,
) -> dict[str, Any]:
    """Coleta e envia um DOI diretamente ao Zenodo (sem JSON intermediário)."""
    print(f"\n{'='*60}")
    print(f"  DOI: {doi}")
    print(f"{'='*60}")

    ieee_data = fetch_ieee_metadata(doi)
    print(f"  Título : {ieee_data.get('title', 'N/A')}")
    time.sleep(REQUEST_DELAY)
    crossref_data = fetch_crossref_metadata(doi)
    metadata = build_zenodo_metadata(
        ieee_data, crossref_data,
        communities=communities,
        access_right=access_right,
        embargo_date=embargo_date,
        access_conditions=access_conditions,
    )

    if dry_run:
        print("\n[DRY-RUN] Metadados que seriam enviados ao Zenodo:")
        print(json.dumps(metadata, indent=2, ensure_ascii=False))
        return {"doi": doi, "status": "dry-run"}

    result = upload_to_zenodo(metadata, publish=publish)
    result["doi"] = doi
    result["status"] = "published" if publish else "uploaded"
    print(f"  ✓ {'Publicado' if publish else 'Rascunho criado'}: {result['deposit_url']}")
    return result


# ---------------------------------------------------------------------------
# CLI
# ---------------------------------------------------------------------------

def parse_args() -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Importa artigos do IEEEXplore para o Zenodo via DOI.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )

    # Fonte dos DOIs
    p.add_argument("dois", nargs="*", metavar="DOI", help="Um ou mais DOIs")
    p.add_argument("--file", metavar="FILE", help="Arquivo com um DOI por linha")

    # Modo de operação
    mode = p.add_mutually_exclusive_group()
    mode.add_argument(
        "--export", metavar="JSON_FILE",
        help="Coletar metadados e salvar no JSON intermediário (não envia ao Zenodo)"
    )
    mode.add_argument(
        "--import", dest="import_file", metavar="JSON_FILE",
        help="Ler JSON intermediário e enviar entradas pendentes ao Zenodo"
    )
    mode.add_argument(
        "--dry-run", action="store_true",
        help="Exibir metadados sem criar nada no Zenodo"
    )

    # Opções de envio
    p.add_argument("--publish", action="store_true", help="Publicar imediatamente (padrão: rascunho)")
    p.add_argument("--community", metavar="ID", action="append", default=[], help="ID de comunidade Zenodo")
    p.add_argument("--output", metavar="FILE", help="Salvar resultados da inserção em JSON")
    p.add_argument("--force", action="store_true", help="Sobrescrever entradas já existentes no JSON")

    # Controle de acesso
    p.add_argument("--access", choices=["open", "embargoed", "restricted", "closed"],
                   default="open", help="Tipo de acesso (padrão: open)")
    p.add_argument("--embargo-date", metavar="YYYY-MM-DD", help="Data de fim do embargo")
    p.add_argument("--access-conditions", metavar="TEXTO", help="Condições de acesso restrito")

    return p.parse_args()


def collect_dois(args: argparse.Namespace) -> list[str]:
    dois = list(args.dois)
    if args.file:
        with open(args.file, encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if line and not line.startswith("#"):
                    dois.append(line)
    return dois


def validate_args(args: argparse.Namespace) -> None:
    if args.access == "embargoed" and not args.embargo_date:
        print("Erro: --access embargoed requer --embargo-date YYYY-MM-DD", file=sys.stderr)
        sys.exit(1)
    if args.access == "restricted" and not args.access_conditions:
        print("Erro: --access restricted requer --access-conditions TEXTO", file=sys.stderr)
        sys.exit(1)


def main() -> None:
    args = parse_args()
    validate_args(args)

    # --- MODO IMPORT: lê JSON e envia ao Zenodo ---
    if args.import_file:
        import_from_json(args.import_file, args)
        return

    # Para os demais modos, precisamos de DOIs
    dois = collect_dois(args)
    if not dois:
        print("Erro: forneça ao menos um DOI ou use --file.", file=sys.stderr)
        sys.exit(1)

    # --- MODO EXPORT: coleta e salva JSON intermediário ---
    if args.export:
        export_to_json(dois, args.export, args)
        return

    # --- MODO DIRETO (dry-run ou envio imediato) ---
    communities = args.community or []
    results = []
    for doi in dois:
        try:
            result = process_doi_direct(
                doi,
                communities=communities,
                access_right=args.access,
                embargo_date=args.embargo_date,
                access_conditions=args.access_conditions,
                dry_run=args.dry_run,
                publish=args.publish,
            )
            results.append(result)
        except Exception as e:
            print(f"  ✗ Erro ao processar {doi}: {e}")
            results.append({"doi": doi, "status": "error", "error": str(e)})
        time.sleep(REQUEST_DELAY)

    if args.output and results:
        with open(args.output, "w", encoding="utf-8") as f:
            json.dump(results, f, ensure_ascii=False, indent=2)
        print(f"\n✓ Resultados salvos em: {args.output}")


if __name__ == "__main__":
    main()
