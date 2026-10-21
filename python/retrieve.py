#!/usr/bin/env python3
"""IA-5: retrieval top-N desde Qdrant con filtros de curso/fecha/tema/doc.

Uso:
    python/.venv/bin/python python/retrieve.py "consulta" \
        [--top 5] [--curso-id 4] [--class-date 2026-10-19] [--topic "..."] \
        [--doc-id N] [--min-score 0.0] [--collection nexo_docs] \
        [--qdrant-url URL] [--json]

Ejemplos:
    python/.venv/bin/python python/retrieve.py "¿Cómo se resuelve por compuertas NAND?"
    python/.venv/bin/python python/retrieve.py "¿Cómo se define una ruta en CodeIgniter 4?" \
        --curso-id 2 --json
"""
from __future__ import annotations

import argparse
import json
import signal
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from nexo import embeddings, vectorstore  # noqa: E402

PREVIEW_CHARS = 240


def _preview(text: str, limit: int = PREVIEW_CHARS) -> str:
    flat = " ".join((text or "").split())
    return flat[:limit] + ("..." if len(flat) > limit else "")


def _meta(payload: dict) -> str:
    parts = []
    for key, label in (("curso_id", "curso"), ("class_date", "fecha"), ("topic", "tema")):
        value = payload.get(key)
        if value not in (None, ""):
            parts.append(f"{label}={value}")
    return " · ".join(parts)


def main() -> int:
    parser = argparse.ArgumentParser(description="IA-5: retrieval filtrado desde Qdrant")
    parser.add_argument("query", help="consulta en lenguaje natural")
    parser.add_argument("--top", type=int, default=5)
    parser.add_argument("--curso-id", type=int, default=None)
    parser.add_argument("--class-date", default=None)
    parser.add_argument("--topic", default=None)
    parser.add_argument("--doc-id", type=int, default=None)
    parser.add_argument("--min-score", type=float, default=0.0)
    parser.add_argument("--collection", default=vectorstore.DEFAULT_COLLECTION)
    parser.add_argument("--qdrant-url", default=None, help="default: QDRANT_URL del .env")
    parser.add_argument("--json", action="store_true", help="salida JSON (score + payload)")
    args = parser.parse_args()

    filters = vectorstore.build_filter(
        curso_id=args.curso_id,
        class_date=args.class_date,
        topic=args.topic,
        doc_id=args.doc_id,
    )

    try:
        store = vectorstore.QdrantStore(args.qdrant_url, args.collection)
        store.collection_info()  # falla rapido si Qdrant esta caido (no gasta embedding)
        vector = embeddings.embed_query(args.query)
        hits = store.search(
            vector,
            limit=max(1, args.top),
            filters=filters,
            score_threshold=args.min_score if args.min_score > 0 else None,
        )
    except vectorstore.QdrantError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1
    except RuntimeError as exc:
        print(f"ERROR: {exc}", file=sys.stderr)
        return 1

    if not hits:
        if args.json:
            print("[]")
        print("Sin resultados para los filtros dados.", file=sys.stderr)
        return 0

    if args.json:
        output = [
            {"score": round(float(hit.get("score", 0.0)), 6), "payload": hit.get("payload") or {}}
            for hit in hits
        ]
        print(json.dumps(output, ensure_ascii=False, indent=2))
        return 0

    for rank, hit in enumerate(hits, start=1):
        payload = hit.get("payload") or {}
        meta = _meta(payload)
        print(f"{rank}. score={float(hit.get('score', 0.0)):.3f}" + (f"  ({meta})" if meta else ""))
        source = payload.get("source") or payload.get("file_path") or "?"
        location = payload.get("location") or "?"
        print(f"   {source} · {location}")
        print(f"   {_preview(payload.get('text', ''))}")
        print()
    return 0


if __name__ == "__main__":
    try:
        signal.signal(signal.SIGPIPE, signal.SIG_DFL)
    except (AttributeError, ValueError):
        pass
    raise SystemExit(main())
