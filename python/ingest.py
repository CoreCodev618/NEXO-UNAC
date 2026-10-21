#!/usr/bin/env python3
"""IA-5: ingest del material chunked a Qdrant, idempotente por UUID5.

Uso:
    python/.venv/bin/python python/ingest.py TARGETS... \
        --curso-id 2 --class-date 2026-10-20 --topic "Programación Web II" \
        [--doc-id N] [--collection nexo_docs] [--qdrant-url URL] [--batch 64] [--dry-run]

Ejemplos:
    python/.venv/bin/python python/ingest.py "../samples/SILABUS  Programacion Web II 2026B.docx" \
        "../samples/SEMANA6/ci42" --curso-id 2 --class-date 2026-10-20 \
        --topic "Programación Web II"
    python/.venv/bin/python python/ingest.py "../samples" --dry-run

Reingestar el mismo material no duplica puntos: el id de cada punto es
`uuid5(NAMESPACE_URL, "nexo:{doc_id}:{chunk_index}")`.
"""
from __future__ import annotations

import argparse
import signal
import sys
from collections import Counter
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

from nexo import chunking, embeddings, env, extractors, vectorstore  # noqa: E402


def _preview(text: str, limit: int = 140) -> str:
    flat = " ".join(text.split())
    return flat[:limit] + ("..." if len(flat) > limit else "")


def _cache_size() -> int:
    cache = env.repo_root() / ".cache" / "embeddings"
    if not cache.is_dir():
        return 0
    return sum(1 for path in cache.glob("*.json") if path.is_file())


def _dedupe(chunks: list[chunking.Chunk]) -> tuple[list[chunking.Chunk], int]:
    """Dedupe por (doc_id, chunk_index), conservando el primer orden de aparicion."""
    seen: set[tuple[int, int]] = set()
    unique: list[chunking.Chunk] = []
    for chunk in chunks:
        key = (chunk.doc_id, chunk.chunk_index)
        if key in seen:
            continue
        seen.add(key)
        unique.append(chunk)
    return unique, len(chunks) - len(unique)


def _points(chunks: list[chunking.Chunk], vectors: list[list[float]], args) -> list[dict]:
    return [
        {
            "id": vectorstore.point_id(chunk.doc_id, chunk.chunk_index),
            "vector": vector,
            "payload": vectorstore.payload_for_chunk(
                chunk, args.curso_id, args.class_date, args.topic
            ),
        }
        for chunk, vector in zip(chunks, vectors)
    ]


def main() -> int:
    parser = argparse.ArgumentParser(description="IA-5: ingest de chunks a Qdrant")
    parser.add_argument("targets", nargs="+", help="archivos, carpetas o .zip")
    parser.add_argument(
        "--doc-id",
        type=int,
        default=None,
        help="doc_id inicial explicito (default: hash estable de la ruta)",
    )
    parser.add_argument("--curso-id", type=int, default=1)
    parser.add_argument("--class-date", default="")
    parser.add_argument("--topic", default="")
    parser.add_argument("--max-tokens", type=int, default=chunking.DEFAULT_CHUNK_TOKENS)
    parser.add_argument("--overlap", type=int, default=chunking.DEFAULT_OVERLAP_TOKENS)
    parser.add_argument("--collection", default=vectorstore.DEFAULT_COLLECTION)
    parser.add_argument("--qdrant-url", default=None, help="default: QDRANT_URL del .env")
    parser.add_argument("--batch", type=int, default=vectorstore.UPSERT_BATCH)
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="solo extrae/chunkea e imprime; no calcula embeddings ni escribe",
    )
    args = parser.parse_args()

    store = vectorstore.QdrantStore(args.qdrant_url, args.collection)
    batch_size = max(1, args.batch)

    all_chunks: list[chunking.Chunk] = []
    warnings: list[str] = []
    sources: list[tuple[str, int, int]] = []
    next_doc_id = args.doc_id if args.doc_id is not None else 0

    for target in args.targets:
        path = Path(target).expanduser().resolve()
        if not path.exists():
            print(f"ERROR: no existe: {path}")
            return 1

        try:
            extraction = extractors.extract_path(path)
        except Exception as exc:  # noqa: BLE001
            print(f"ERROR extrayendo {path}: {type(exc).__name__}: {exc}")
            return 1

        warnings.extend(extraction.warnings)
        chunks = chunking.chunk_extraction(
            extraction.items,
            doc_id_start=next_doc_id,
            stable_ids=args.doc_id is None,
            curso_id=args.curso_id,
            class_date=args.class_date,
            topic=args.topic,
            max_tokens=args.max_tokens,
            overlap_tokens=args.overlap,
        )
        if args.doc_id is not None:
            next_doc_id += len(extraction.items)
        all_chunks.extend(chunks)
        sources.append((path.name, len(extraction.items), len(chunks)))

    print("=" * 70)
    for name, n_sources, n_chunks in sources:
        print(f"{name}: {n_sources} fuentes -> {n_chunks} chunks")

    if not all_chunks:
        print("No se generaron chunks.")
        for warning in warnings:
            print(f"AVISO: {warning}")
        return 1

    chunks, duplicates = _dedupe(all_chunks)
    tokens = [chunk.tokens for chunk in chunks]
    kinds = Counter(chunk.kind for chunk in chunks)
    print("=" * 70)
    print(
        f"Total: {len(chunks)} chunks únicos · {duplicates} duplicados omitidos · "
        f"tokens min/avg/max = {min(tokens)}/{sum(tokens) // len(tokens)}/{max(tokens)}"
    )
    print("Por tipo: " + ", ".join(f"{n} {k}" for k, n in sorted(kinds.items())))

    sample = chunks[0]
    print(f"\nMuestra [{sample.chunk_id}] {sample.source} · {sample.location}")
    print(f"  > {_preview(sample.text)}")

    if args.dry_run:
        print(
            f"\nDRY-RUN: {len(chunks)} chunks listos para '{args.collection}'; "
            "no se calcularon embeddings ni se escribió en Qdrant."
        )
        for warning in warnings:
            print(f"AVISO: {warning}")
        return 0

    try:
        store.ensure_collection(embeddings.dimension())
        store.ensure_payload_indexes()
    except vectorstore.QdrantError as exc:
        print(f"ERROR: {exc}")
        return 1

    before_cache = _cache_size()
    print(
        f"\nEmbeddings con {embeddings.model_name()} "
        f"(dim {embeddings.dimension()}) para {len(chunks)} chunks..."
    )
    try:
        vectors = embeddings.embed_texts([chunk.text for chunk in chunks])
    except RuntimeError as exc:
        print(f"ERROR: {exc}")
        return 1
    new_embeddings = max(0, _cache_size() - before_cache)
    cached = len(chunks) - new_embeddings
    print(f"Embeddings: {len(vectors)} · en caché: {cached} · nuevos: {new_embeddings}")

    if len(vectors) != len(chunks):
        print(f"ERROR: embeddings devolvió {len(vectors)} vectores para {len(chunks)} chunks")
        return 1

    points = _points(chunks, vectors, args)
    try:
        sent = store.upsert(points, wait=True, batch_size=batch_size)
        total = store.count()
    except vectorstore.QdrantError as exc:
        print(f"ERROR: {exc}")
        return 1
    except (KeyError, TypeError, ValueError) as exc:
        print(f"ERROR preparando puntos: {type(exc).__name__}: {exc}")
        return 1

    print(f"Puntos enviados: {sent} (lotes de {batch_size})")
    print(f"count() en '{args.collection}': {total}")

    if warnings:
        print()
        for warning in warnings:
            print(f"AVISO: {warning}")
    return 0


if __name__ == "__main__":
    try:
        signal.signal(signal.SIGPIPE, signal.SIG_DFL)
    except (AttributeError, ValueError):
        pass
    raise SystemExit(main())
