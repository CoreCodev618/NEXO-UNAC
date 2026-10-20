"""Embeddings con Gemini (IA-4) para el RAG de NEXO-UNAC.

Modelo: gemini-embedding-001 con outputDimensionality=768 (coleccion Qdrant).
Configurable con EMBED_MODEL / EMBED_DIM en el .env del repo.
Cache local por hash de texto para no gastar cuota en re-ejecuciones.
"""
from __future__ import annotations

import hashlib
import json
import time
from pathlib import Path

from . import env

DEFAULT_MODEL = "gemini-embedding-001"
DEFAULT_DIM = 768
BATCH_SIZE = 10  # textos por request: mantiene el TPM bajo en free tier
MAX_RETRIES = 5
RETRY_STATUS = {429, 500, 502, 503, 504}


def model_name() -> str:
    return env.get("EMBED_MODEL", DEFAULT_MODEL)


def dimension() -> int:
    return int(env.get("EMBED_DIM", str(DEFAULT_DIM)))


def _cache_dir() -> Path:
    return env.repo_root() / ".cache" / "embeddings"


def _cache_key(text: str, model: str, output_dim: int, task_type: str) -> str:
    payload = f"{model}|{output_dim}|{task_type}|{text}"
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


def _cache_read(key: str) -> list[float] | None:
    path = _cache_dir() / f"{key}.json"
    if not path.is_file():
        return None
    try:
        values = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, ValueError):
        return None
    return values if isinstance(values, list) and values else None


def _cache_write(key: str, vector: list[float]) -> None:
    cache = _cache_dir()
    cache.mkdir(parents=True, exist_ok=True)
    (cache / f"{key}.json").write_text(json.dumps(vector), encoding="utf-8")


def _retry_delay(response, attempt: int) -> float:
    try:
        details = response.json().get("error", {}).get("details", [])
        for detail in details:
            delay = detail.get("retryDelay")
            if delay:
                return float(str(delay).rstrip("s")) + 1
    except (ValueError, AttributeError):
        pass
    return min(60.0, 15.0 * (attempt + 1))


def _post_with_retry(url: str, payload: dict, timeout: int = 120) -> dict:
    import requests

    last_error = ""
    for attempt in range(MAX_RETRIES):
        response = requests.post(url, json=payload, timeout=timeout)
        if response.status_code == 200:
            return response.json()
        last_error = f"HTTP {response.status_code}: {response.text[:300]}"
        if response.status_code in RETRY_STATUS:
            time.sleep(_retry_delay(response, attempt))
            continue
        break
    raise RuntimeError(f"Gemini embeddings: {last_error}")


def embed_texts(
    texts: list[str],
    *,
    task_type: str = "RETRIEVAL_DOCUMENT",
    model: str | None = None,
    output_dim: int | None = None,
) -> list[list[float]]:
    """Devuelve un vector por texto (mismo orden). Usa cache local cuando puede."""
    if not texts:
        return []

    key = env.get("GEMINI_API_KEY")
    if not key:
        raise RuntimeError("Falta GEMINI_API_KEY en el .env del repo")

    model = model or model_name()
    output_dim = int(output_dim or dimension())

    cache_keys = [_cache_key(text, model, output_dim, task_type) for text in texts]
    results: list[list[float] | None] = []
    missing: list[int] = []

    for index, cache_key in enumerate(cache_keys):
        vector = _cache_read(cache_key)
        results.append(vector)
        if vector is None:
            missing.append(index)

    if missing:
        url = (
            "https://generativelanguage.googleapis.com/v1beta/models/"
            f"{model}:batchEmbedContents?key={key}"
        )
        total_missing = len(missing)
        print(f"  embeddings: {total_missing} por calcular, {len(texts) - total_missing} en cache", flush=True)

        for start in range(0, total_missing, BATCH_SIZE):
            batch_indexes = missing[start : start + BATCH_SIZE]
            payload = {
                "requests": [
                    {
                        "model": f"models/{model}",
                        "content": {"parts": [{"text": texts[index]}]},
                        "taskType": task_type,
                        "outputDimensionality": output_dim,
                    }
                    for index in batch_indexes
                ]
            }

            data = _post_with_retry(url, payload)
            embeddings = data.get("embeddings") or []
            if len(embeddings) != len(batch_indexes):
                raise RuntimeError(
                    f"Gemini embeddings: se pidieron {len(batch_indexes)} y llegaron {len(embeddings)}"
                )

            for index, embedding in zip(batch_indexes, embeddings):
                vector = embedding["values"]
                results[index] = vector
                _cache_write(cache_keys[index], vector)

            done = min(start + len(batch_indexes), total_missing)
            print(f"  embeddings: {done}/{total_missing}", flush=True)
            time.sleep(1.0)

    if any(vector is None for vector in results):
        raise RuntimeError("Gemini embeddings: quedaron textos sin vector")

    return results  # type: ignore[return-value]


def embed_query(text: str, *, model: str | None = None, output_dim: int | None = None) -> list[float]:
    vectors = embed_texts(
        [text], task_type="RETRIEVAL_QUERY", model=model, output_dim=output_dim
    )
    return vectors[0]
