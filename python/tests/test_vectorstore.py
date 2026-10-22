"""Tests de vectorstore (IA-5), sin red.

Ejecutar:
    python/.venv/bin/python -m unittest discover -s python/tests -v
"""
from __future__ import annotations

import json
import sys
import unittest
import uuid
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from nexo import chunking, vectorstore  # noqa: E402


def _info(size: int = 768, distance: str = "Cosine") -> dict:
    return {"config": {"params": {"vectors": {"size": size, "distance": distance}}}}


class FakeResponse:
    def __init__(self, status_code=200, payload=None, text=None, headers=None):
        self.status_code = status_code
        self._payload = payload
        if text is not None:
            self.text = text
        elif payload is not None:
            self.text = json.dumps(payload)
        else:
            self.text = ""
        self.content = self.text.encode()
        self.headers = headers or {}

    def json(self):
        if self._payload is None:
            raise ValueError("respuesta sin JSON")
        return self._payload


class PointIdTest(unittest.TestCase):
    def test_deterministico(self):
        self.assertEqual(
            vectorstore.point_id(123, 4),
            vectorstore.point_id(123, 4),
        )

    def test_distingue_chunk_index_y_doc(self):
        self.assertNotEqual(
            vectorstore.point_id(123, 4),
            vectorstore.point_id(123, 5),
        )
        self.assertNotEqual(
            vectorstore.point_id(123, 4),
            vectorstore.point_id(124, 4),
        )

    def test_formato_uuid(self):
        value = vectorstore.point_id(1, 0)
        self.assertEqual(len(value), 36)
        self.assertEqual(value.count("-"), 4)


class NormalizationTest(unittest.TestCase):
    def test_topic_nfkc_espacios_y_casefold(self):
        self.assertEqual(
            vectorstore.normalize_topic("  INVESTIGACIÓN   de Operaciones "),
            "investigación de operaciones",
        )

    def test_fecha_iso_valida_y_normaliza(self):
        self.assertEqual(vectorstore.normalize_class_date("2026-10-20"), "2026-10-20")
        self.assertEqual(vectorstore.normalize_class_date(""), "")
        self.assertEqual(vectorstore.normalize_class_date(None), "")

    def test_fecha_iso_invalida(self):
        with self.assertRaises(ValueError):
            vectorstore.normalize_class_date("20/10/2026")
        with self.assertRaises(ValueError):
            vectorstore.normalize_class_date("2026-13-99")

    def test_sanitize_url_oculta_password(self):
        url = vectorstore._sanitize_url("http://user:secret@example.com:6333")
        self.assertEqual(url, "http://***@example.com:6333")
        self.assertNotIn("secret", url)


class BuildFilterTest(unittest.TestCase):
    def test_sin_filtros_es_none(self):
        self.assertIsNone(vectorstore.build_filter())
        self.assertIsNone(
            vectorstore.build_filter(curso_id=None, class_date="", topic="", doc_id=None)
        )

    def test_filtro_curso_y_doc_son_enteros(self):
        result = vectorstore.build_filter(curso_id=4, doc_id=9)
        self.assertEqual(
            result,
            {
                "must": [
                    {"key": "curso_id", "match": {"value": 4}},
                    {"key": "doc_id", "match": {"value": 9}},
                ]
            },
        )

    def test_filtros_de_texto_normalizados(self):
        result = vectorstore.build_filter(
            curso_id=2,
            class_date="2026-10-20",
            topic="  Programación   Web II ",
            doc_id=7,
        )
        self.assertEqual(len(result["must"]), 4)
        by_key = {entry["key"]: entry["match"]["value"] for entry in result["must"]}
        self.assertEqual(by_key["curso_id"], 2)
        self.assertEqual(by_key["doc_id"], 7)
        self.assertEqual(by_key["class_date"], "2026-10-20")
        self.assertEqual(by_key["topic_norm"], "programación web ii")

    def test_fecha_invalida_en_filtro(self):
        with self.assertRaises(ValueError):
            vectorstore.build_filter(class_date="2026-99-99")


class PayloadTest(unittest.TestCase):
    def _chunk(self) -> chunking.Chunk:
        return chunking.Chunk(
            text="Una ruta se define en app/Config/Routes.php.",
            doc_id=42,
            chunk_index=3,
            source="ci42/app/Config/Routes.php",
            kind="code",
            location="app/Config/Routes.php",
            tokens=17,
            source_key="samples/SEMANA6/ci42/app/Config/Routes.php",
        )

    def test_chunk_id_es_entero(self):
        payload = vectorstore.payload_for_chunk(self._chunk(), curso_id=2)
        self.assertIsInstance(payload["chunk_id"], int)
        self.assertEqual(payload["chunk_id"], 3)
        self.assertEqual(payload["chunk_key"], "42-3")

    def test_incluye_campos_de_retrieval(self):
        payload = vectorstore.payload_for_chunk(
            self._chunk(), curso_id=2, class_date="2026-10-20", topic="Programación Web II"
        )
        self.assertEqual(payload["doc_id"], 42)
        self.assertEqual(payload["doc_id_str"], "42")
        self.assertEqual(payload["curso_id"], 2)
        self.assertEqual(payload["class_date"], "2026-10-20")
        self.assertEqual(payload["topic"], "Programación Web II")
        self.assertEqual(payload["topic_norm"], "programación web ii")
        self.assertEqual(payload["source"], "ci42/app/Config/Routes.php")
        self.assertEqual(
            payload["source_key"], "samples/SEMANA6/ci42/app/Config/Routes.php"
        )
        self.assertEqual(payload["location"], "app/Config/Routes.php")
        self.assertIn("Routes.php", payload["text"])
        self.assertEqual(payload["page_id"], 0)

    def test_defaults_vacios(self):
        payload = vectorstore.payload_for_chunk(self._chunk(), curso_id=1)
        self.assertEqual(payload["class_date"], "")
        self.assertEqual(payload["topic"], "")
        self.assertEqual(payload["topic_norm"], "")


class RequestTest(unittest.TestCase):
    def setUp(self):
        self.store = vectorstore.QdrantStore("http://localhost:6333", "nexo_docs")

    def test_204_devuelve_none(self):
        with mock.patch("requests.request", return_value=FakeResponse(204, text="")):
            self.assertIsNone(self.store._request("DELETE", "/x"))

    def test_200_no_json_es_error(self):
        with mock.patch(
            "requests.request", return_value=FakeResponse(200, text="<html>")
        ):
            with self.assertRaises(vectorstore.QdrantError) as ctx:
                self.store._request("GET", "/x")
        self.assertEqual(ctx.exception.status, 200)
        self.assertIn("no-JSON", str(ctx.exception))

    def test_reintenta_429_y_luego_200(self):
        responses = [
            FakeResponse(429, text="slow", headers={"Retry-After": "0"}),
            FakeResponse(200, {"result": {"ok": True}}),
        ]
        with mock.patch("requests.request", side_effect=responses) as request, \
                mock.patch.object(vectorstore.time, "sleep") as sleep:
            result = self.store._request("GET", "/x")
        self.assertEqual(result, {"result": {"ok": True}})
        self.assertEqual(request.call_count, 2)
        sleep.assert_called_once_with(0.0)

    def test_500_tras_reintentos_conserva_status(self):
        responses = [FakeResponse(500, text="boom") for _ in range(3)]
        with mock.patch("requests.request", side_effect=responses) as request, \
                mock.patch.object(vectorstore.time, "sleep"):
            with self.assertRaises(vectorstore.QdrantError) as ctx:
                self.store._request("GET", "/x")
        self.assertEqual(ctx.exception.status, 500)
        self.assertEqual(request.call_count, 3)

    def test_api_key_va_en_header_y_no_en_error(self):
        store = vectorstore.QdrantStore("http://q", "c", api_key="secreto")
        with mock.patch(
            "requests.request", return_value=FakeResponse(200, {"result": {}})
        ) as request:
            store._request("GET", "/x")
        self.assertEqual(request.call_args.kwargs["headers"], {"api-key": "secreto"})

        with mock.patch(
            "requests.request", return_value=FakeResponse(500, text="boom")
        ), mock.patch.object(vectorstore.time, "sleep"):
            with self.assertRaises(vectorstore.QdrantError) as ctx:
                store._request("GET", "/x")
        self.assertNotIn("secreto", str(ctx.exception))


class CollectionTest(unittest.TestCase):
    def setUp(self):
        self.store = vectorstore.QdrantStore("http://q", "c")

    def test_crea_si_404(self):
        with mock.patch.object(
            self.store,
            "collection_info",
            side_effect=[vectorstore.QdrantError("404", 404), _info()],
        ), mock.patch.object(self.store, "_request") as request:
            result = self.store.ensure_collection(768)
        self.assertEqual(result["config"]["params"]["vectors"]["size"], 768)
        request.assert_called_once()
        self.assertEqual(request.call_args.args[:2], ("PUT", "/collections/c"))

    def test_409_por_carrera_y_luego_valida(self):
        with mock.patch.object(
            self.store,
            "collection_info",
            side_effect=[vectorstore.QdrantError("404", 404), _info()],
        ), mock.patch.object(
            self.store, "_request", side_effect=vectorstore.QdrantError("conflict", 409)
        ):
            result = self.store.ensure_collection(768)
        self.assertEqual(result["config"]["params"]["vectors"]["distance"], "Cosine")

    def test_size_distinto_es_error(self):
        with mock.patch.object(self.store, "collection_info", return_value=_info(384)):
            with self.assertRaises(vectorstore.QdrantError) as ctx:
                self.store.ensure_collection(768)
        self.assertIn("size=384", str(ctx.exception))

    def test_distance_distinta_es_error(self):
        with mock.patch.object(
            self.store, "collection_info", return_value=_info(distance="Dot")
        ):
            with self.assertRaises(vectorstore.QdrantError) as ctx:
                self.store.ensure_collection(768)
        self.assertIn("distance=Dot", str(ctx.exception))

    def test_vectores_nombrados_es_error(self):
        info = {"config": {"params": {"vectors": {"dense": {"size": 768}}}}}
        with mock.patch.object(self.store, "collection_info", return_value=info):
            with self.assertRaises(vectorstore.QdrantError) as ctx:
                self.store.ensure_collection(768)
        self.assertIn("nombrados", str(ctx.exception))


class PointsTest(unittest.TestCase):
    def setUp(self):
        self.store = vectorstore.QdrantStore("http://q", "c")

    def test_upsert_por_lotes(self):
        points = [{"id": str(uuid.uuid4())} for _ in range(130)]
        with mock.patch.object(self.store, "_request") as request:
            sent = self.store.upsert(points, batch_size=64)
        self.assertEqual(sent, 130)
        self.assertEqual(request.call_count, 3)
        sizes = [len(call.args[2]["points"]) for call in request.call_args_list]
        self.assertEqual(sizes, [64, 64, 2])

    def test_upsert_batch_invalido(self):
        with self.assertRaises(vectorstore.QdrantError):
            self.store.upsert([{"id": "x"}], batch_size=0)

    def test_search_usa_points_query_y_filtro(self):
        response = {"result": {"points": [{"id": "1", "score": 0.9}]}}
        with mock.patch.object(
            self.store, "_request", return_value=response
        ) as request:
            hits = self.store.search(
                [0.1, 0.2], limit=4, filters={"must": []}, score_threshold=0.0
            )
        self.assertEqual(hits[0]["score"], 0.9)
        self.assertEqual(
            request.call_args.args[1], "/collections/c/points/query"
        )
        body = request.call_args.args[2]
        self.assertEqual(body["query"], [0.1, 0.2])
        self.assertEqual(body["limit"], 4)
        self.assertEqual(body["filter"], {"must": []})
        self.assertEqual(body["score_threshold"], 0.0)

    def test_search_vector_y_limit_invalidos(self):
        with self.assertRaises(vectorstore.QdrantError):
            self.store.search([], limit=5)
        with self.assertRaises(vectorstore.QdrantError):
            self.store.search([0.1], limit=0)

    def test_scroll_pagina(self):
        pages = [
            {"result": {"points": [{"id": "1"}], "next_page_offset": "p2"}},
            {"result": {"points": [{"id": "2"}], "next_page_offset": None}},
        ]
        with mock.patch.object(self.store, "_request", side_effect=pages) as request:
            points = self.store.scroll(
                filters={"must": []}, with_payload=["doc_id"]
            )
        self.assertEqual([point["id"] for point in points], ["1", "2"])
        self.assertEqual(request.call_count, 2)
        self.assertEqual(request.call_args_list[1].args[2]["offset"], "p2")

    def test_delete_points_por_lotes(self):
        ids = [str(uuid.uuid4()) for _ in range(300)]
        with mock.patch.object(self.store, "_request") as request:
            deleted = self.store.delete_points(ids)
        self.assertEqual(deleted, 300)
        self.assertEqual(request.call_count, 2)


if __name__ == "__main__":
    unittest.main()
