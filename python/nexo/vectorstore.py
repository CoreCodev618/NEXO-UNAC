"""Cliente REST minimo para Qdrant (IA-5), sin SDK.

Solo cubre lo que necesita el pipeline RAG: crear/validar la coleccion,
indices de payload, upsert por lotes, count y busqueda con filtros.

Decisiones:
- `point_id` es UUID5 determinista de `"nexo:{doc_id}:{chunk_index}"`, de modo
  que reingestar el mismo material actualiza los puntos en vez de duplicarlos.
- El payload lleva `chunk_id` entero (el indice del chunk dentro del doc) para
  que el indice Qdrant sea integer; la trazabilidad legible va en `chunk_key`
  ("doc_id-chunk_index"). MySQL sigue siendo la fuente de verdad del texto.
"""
from __future__ import annotations

import time
import uuid
from typing import Any, TYPE_CHECKING

from . import env

if TYPE_CHECKING:  # evita importar tiktoken/chunking en tiempo de ejecucion
    from .chunking import Chunk

DEFAULT_COLLECTION = "nexo_docs"
UPSERT_BATCH = 64
MAX_RETRIES = 3
RETRY_STATUS = {429, 500, 502, 503, 504}

# Esquema de indices: integer para ids numericos, keyword para filtros de texto.
PAYLOAD_INDEXES: dict[str, str] = {
    "chunk_id": "integer",
    "doc_id": "integer",
    "page_id": "integer",
    "curso_id": "integer",
    "class_date": "keyword",
    "topic": "keyword",
}

_DISTANCES = {"cosine", "euclid", "dot", "manhattan"}


class QdrantError(RuntimeError):
    """Error de Qdrant con el status HTTP (None si fue error de red)."""

    def __init__(self, message: str, status: int | None = None) -> None:
        super().__init__(message)
        self.status = status


def point_id(doc_id: int, chunk_index: int) -> str:
    """ID determinista e idempotente para (doc_id, chunk_index)."""
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"nexo:{int(doc_id)}:{int(chunk_index)}"))


def build_filter(
    *,
    curso_id: int | None = None,
    class_date: str | None = None,
    topic: str | None = None,
    doc_id: int | None = None,
) -> dict | None:
    """Filtro Qdrant (`must`) para los campos indexados; None si no hay filtros."""
    must: list[dict[str, Any]] = []
    if curso_id is not None:
        must.append({"key": "curso_id", "match": {"value": int(curso_id)}})
    if doc_id is not None:
        must.append({"key": "doc_id", "match": {"value": int(doc_id)}})
    if class_date:
        must.append({"key": "class_date", "match": {"value": str(class_date)}})
    if topic:
        must.append({"key": "topic", "match": {"value": str(topic)}})
    return {"must": must} if must else None


def payload_for_chunk(
    chunk: "Chunk",
    curso_id: int,
    class_date: str = "",
    topic: str = "",
) -> dict[str, Any]:
    """Payload indexable y autocontenido (para demo/retrieval) de un chunk."""
    return {
        "chunk_id": int(chunk.chunk_index),  # indice integer en Qdrant
        "doc_id": int(chunk.doc_id),
        "page_id": 0,
        "curso_id": int(curso_id),
        "class_date": class_date or "",
        "topic": topic or "",
        "file_path": chunk.source,
        "source": chunk.source,
        "location": chunk.location,
        "kind": chunk.kind,
        "text": chunk.text,
        "tokens": int(chunk.tokens),
        "chunk_key": chunk.chunk_id,
    }


class QdrantStore:
    """Cliente HTTP minimo de Qdrant; falla rapido con mensajes accionables."""

    def __init__(self, url: str | None = None, collection: str = DEFAULT_COLLECTION, timeout: int = 30) -> None:
        self.url = (url or env.get("QDRANT_URL", "http://localhost:6333")).rstrip("/")
        self.collection = collection
        self.timeout = timeout

    # -- transporte ---------------------------------------------------------

    def _request(
        self,
        method: str,
        path: str,
        payload: dict | None = None,
        params: dict | None = None,
    ) -> Any:
        import requests

        url = f"{self.url}{path}"
        last_error = ""

        for attempt in range(MAX_RETRIES):
            try:
                response = requests.request(
                    method, url, json=payload, params=params, timeout=self.timeout
                )
            except requests.RequestException as exc:
                last_error = f"{type(exc).__name__} (red/timeout)"
                if attempt + 1 < MAX_RETRIES:
                    time.sleep(0.5 * (attempt + 1))
                    continue
                raise QdrantError(
                    f"Qdrant no responde en {self.url}; corre qdrant-up ({last_error})"
                ) from exc

            if 200 <= response.status_code < 300:
                if not response.content:
                    return None
                try:
                    return response.json()
                except ValueError:
                    return None

            detail = (response.text or "").strip()[:300]
            last_error = f"HTTP {response.status_code}: {detail}"
            if response.status_code in RETRY_STATUS and attempt + 1 < MAX_RETRIES:
                retry_after = response.headers.get("Retry-After", "")
                try:
                    delay = min(5.0, max(0.0, float(retry_after)))
                except ValueError:
                    delay = 0.5 * (attempt + 1)
                time.sleep(delay)
                continue

            raise QdrantError(
                f"Qdrant {method} {path} fallo ({last_error})", response.status_code
            )

        raise QdrantError(f"Qdrant {method} {path} fallo ({last_error})")

    # -- coleccion ----------------------------------------------------------

    def collection_info(self) -> dict:
        data = self._request("GET", f"/collections/{self.collection}")
        return (data or {}).get("result") or {}

    def ensure_collection(self, size: int, distance: str = "Cosine") -> dict:
        """Crea la coleccion si no existe; si existe valida dimension y distancia."""
        size = int(size)
        distance = str(distance)
        if size <= 0:
            raise QdrantError(f"Dimension invalida para la coleccion: {size}")
        if distance.lower() not in _DISTANCES:
            raise QdrantError(f"Distancia no soportada: {distance}")

        try:
            info = self.collection_info()
        except QdrantError as exc:
            if exc.status != 404:
                raise
            self._request(
                "PUT",
                f"/collections/{self.collection}",
                {"vectors": {"size": size, "distance": distance}},
            )
            return self.collection_info()

        params = (
            ((info.get("config") or {}).get("params") or {})
            .get("vectors")
            or {}
        )
        if isinstance(params, dict) and "size" not in params:
            # Configuracion con vectores nombrados: no la soportamos a proposito.
            raise QdrantError(
                f"La coleccion '{self.collection}' usa vectores nombrados; "
                "se esperaba un unico vector."
            )
        actual_size = int(params.get("size", 0))
        actual_distance = str(params.get("distance", ""))
        if actual_size != size:
            raise QdrantError(
                f"La coleccion '{self.collection}' tiene size={actual_size} y se "
                f"esperaba {size}; cambia EMBED_DIM o reingesta en otra coleccion."
            )
        if actual_distance.lower() != distance.lower():
            raise QdrantError(
                f"La coleccion '{self.collection}' usa distance={actual_distance} y "
                f"se esperaba {distance}; recrea la coleccion con la distancia correcta."
            )
        return info

    def ensure_payload_indexes(self) -> None:
        """Crea los indices de payload de forma idempotente."""
        for field, schema in PAYLOAD_INDEXES.items():
            self._request(
                "PUT",
                f"/collections/{self.collection}/index",
                {"field_name": field, "field_schema": schema},
                {"wait": "true"},
            )

    # -- puntos -------------------------------------------------------------

    def upsert(self, points: list[dict], wait: bool = True, batch_size: int = UPSERT_BATCH) -> int:
        """Upsert por lotes; devuelve la cantidad de puntos enviados."""
        if not points:
            return 0
        batch_size = max(1, int(batch_size))
        sent = 0
        for start in range(0, len(points), batch_size):
            batch = points[start : start + batch_size]
            self._request(
                "PUT",
                f"/collections/{self.collection}/points",
                {"points": batch},
                {"wait": "true" if wait else "false"},
            )
            sent += len(batch)
        return sent

    def count(self, exact: bool = True) -> int:
        data = self._request(
            "POST",
            f"/collections/{self.collection}/points/count",
            {"exact": bool(exact)},
        )
        return int(((data or {}).get("result") or {}).get("count", 0))

    def search(
        self,
        vector: list[float],
        limit: int = 5,
        filters: dict | None = None,
        score_threshold: float | None = None,
    ) -> list[dict]:
        """Busqueda por similitud; devuelve los hits crudos de Qdrant."""
        if not vector:
            raise QdrantError("Vector de busqueda vacio")
        body: dict[str, Any] = {
            "vector": [float(value) for value in vector],
            "limit": max(1, int(limit)),
            "with_payload": True,
            "with_vector": False,
        }
        if filters:
            body["filter"] = filters
        if score_threshold is not None:
            body["score_threshold"] = float(score_threshold)

        data = self._request(
            "POST", f"/collections/{self.collection}/points/search", body
        )
        return (data or {}).get("result") or []
