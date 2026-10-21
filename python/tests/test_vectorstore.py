"""Tests de vectorstore (IA-5), sin red.

Ejecutar:
    python/.venv/bin/python -m unittest discover -s python/tests -v
"""
from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from nexo import chunking, vectorstore  # noqa: E402


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

    def test_filtros_de_texto(self):
        result = vectorstore.build_filter(
            curso_id=2,
            class_date="2026-10-20",
            topic="Programación Web II",
            doc_id=7,
        )
        self.assertEqual(len(result["must"]), 4)
        by_key = {entry["key"]: entry["match"]["value"] for entry in result["must"]}
        self.assertEqual(by_key["curso_id"], 2)
        self.assertEqual(by_key["doc_id"], 7)
        self.assertEqual(by_key["class_date"], "2026-10-20")
        self.assertEqual(by_key["topic"], "Programación Web II")


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
        self.assertEqual(payload["curso_id"], 2)
        self.assertEqual(payload["class_date"], "2026-10-20")
        self.assertEqual(payload["topic"], "Programación Web II")
        self.assertEqual(payload["source"], "ci42/app/Config/Routes.php")
        self.assertEqual(payload["location"], "app/Config/Routes.php")
        self.assertIn("Routes.php", payload["text"])
        self.assertEqual(payload["page_id"], 0)

    def test_defaults_vacios(self):
        payload = vectorstore.payload_for_chunk(self._chunk(), curso_id=1)
        self.assertEqual(payload["class_date"], "")
        self.assertEqual(payload["topic"], "")


if __name__ == "__main__":
    unittest.main()
