"""Tests de chunking (IA-4).

Ejecutar:
    python/.venv/bin/python -m unittest discover -s python/tests -v
"""
from __future__ import annotations

import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from nexo import chunking  # noqa: E402


class SplitTextTest(unittest.TestCase):
    def test_texto_vacio(self):
        self.assertEqual(chunking.split_text("   \n "), [])

    def test_texto_corto_es_un_chunk(self):
        texto = "La programacion entera resuelve modelos con variables enteras."
        self.assertEqual(chunking.split_text(texto, max_tokens=500), [texto])

    def test_una_oracion_larga_respeta_el_maximo(self):
        texto = " ".join(f"palabra{i}" for i in range(2000))
        chunks = chunking.split_text(texto, max_tokens=100, overlap_tokens=10)
        self.assertGreater(len(chunks), 1)
        self.assertTrue(all(chunking.count_tokens(c) <= 100 for c in chunks))

    def test_muchas_oraciones_respetan_el_maximo(self):
        texto = " ".join(f"Esta es la oracion numero {i}." for i in range(400))
        chunks = chunking.split_text(texto, max_tokens=80, overlap_tokens=10)
        self.assertGreater(len(chunks), 1)
        self.assertTrue(all(chunking.count_tokens(c) <= 80 for c in chunks))

    def test_utf8_no_se_rompe(self):
        texto = "Programación entera: solución óptima con variables enteras. " * 120
        chunks = chunking.split_text(texto, max_tokens=100, overlap_tokens=10)
        self.assertTrue(chunks)
        self.assertTrue(all("Programación" in chunk for chunk in chunks))

    def test_solape_entre_chunks(self):
        texto = " ".join(f"oracion numero {i} con contenido." for i in range(200))
        chunks = chunking.split_text(texto, max_tokens=60, overlap_tokens=15)
        self.assertGreater(len(chunks), 2)
        for actual, siguiente in zip(chunks, chunks[1:]):
            cola = " ".join(actual.split()[-3:])
            self.assertIn(cola, siguiente)


class StableDocIdTest(unittest.TestCase):
    def test_es_estable_y_distingue_fuentes(self):
        self.assertEqual(chunking.stable_doc_id("a.docx"), chunking.stable_doc_id("a.docx"))
        self.assertNotEqual(chunking.stable_doc_id("a.docx"), chunking.stable_doc_id("b.docx"))
        self.assertLess(chunking.stable_doc_id("a.docx"), 2**60)


class ChunkExtractionTest(unittest.TestCase):
    class _Unit:
        def __init__(self, text, location="documento"):
            self.text = text
            self.location = location

    class _Item:
        def __init__(self, source, kind, units):
            self.source = source
            self.kind = kind
            self.units = units

    def test_metadata_y_chunk_ids_unicos(self):
        items = [
            self._Item("a.docx", "docx", [self._Unit("A" * 2000)]),
            self._Item("b.pptx", "pptx", [self._Unit("B" * 2000, "slide 1")]),
        ]
        chunks = chunking.chunk_extraction(
            items, stable_ids=True, curso_id=7, class_date="2026-10-20", topic="Demo"
        )
        ids = [chunk.chunk_id for chunk in chunks]
        self.assertEqual(len(ids), len(set(ids)))
        self.assertEqual(chunks[0].metadata["curso_id"], 7)
        self.assertEqual(chunks[-1].metadata["location"], "slide 1")
        self.assertEqual(chunks[0].doc_id, chunking.stable_doc_id("a.docx"))

    def test_max_tokens_en_todos_los_chunks(self):
        items = [self._Item("a.docx", "docx", [self._Unit("Frase de prueba. " * 800)])]
        chunks = chunking.chunk_extraction(items, max_tokens=100, overlap_tokens=10)
        self.assertTrue(all(chunk.tokens <= 100 for chunk in chunks))
        self.assertTrue(all(chunk.text == chunk.text.strip() for chunk in chunks))


if __name__ == "__main__":
    unittest.main()
