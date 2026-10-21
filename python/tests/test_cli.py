"""Tests de las CLIs IA-5 (`ingest.py`, `retrieve.py`) sin red ni cuota."""
from __future__ import annotations

import contextlib
import io
import json
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

import ingest  # noqa: E402
import retrieve  # noqa: E402
from nexo import chunking, extractors  # noqa: E402


class FakeStore:
    def __init__(self, *args, **kwargs):
        pass

    def collection_info(self):
        return {}

    def search(self, *args, **kwargs):
        return [
            {
                "score": 0.9,
                "payload": {
                    "text": "contenido",
                    "source": "fuente.txt",
                    "location": "documento",
                },
            }
        ]


def _run_module(module, argv):
    stdout = io.StringIO()
    stderr = io.StringIO()
    with mock.patch.object(sys, "argv", argv), contextlib.redirect_stdout(
        stdout
    ), contextlib.redirect_stderr(stderr):
        code = module.main()
    return code, stdout.getvalue(), stderr.getvalue()


class RetrieveCliTest(unittest.TestCase):
    def test_json_limpio_aunque_haya_cache_miss(self):
        def fake_embed(query):
            # Simula el progreso que embeddings.py escribe en stderr.
            print("  embeddings: 1 unicos por calcular", file=sys.stderr)
            return [0.0] * 768

        with mock.patch.object(
            retrieve.vectorstore, "QdrantStore", return_value=FakeStore()
        ), mock.patch.object(
            retrieve.embeddings, "embed_query", side_effect=fake_embed
        ):
            code, stdout, stderr = _run_module(
                retrieve, ["retrieve.py", "consulta", "--json"]
            )

        self.assertEqual(code, 0)
        data = json.loads(stdout)  # stdout debe ser JSON puro
        self.assertEqual(data[0]["score"], 0.9)
        self.assertIn("embeddings:", stderr)

    def test_query_vacia_no_toca_qdrant(self):
        with mock.patch.object(retrieve.vectorstore, "QdrantStore") as store:
            code, stdout, stderr = _run_module(retrieve, ["retrieve.py", "   "])
        self.assertEqual(code, 2)
        self.assertEqual(stdout, "")
        self.assertIn("vacia", stderr)
        store.assert_not_called()

    def test_top_cero_es_error_de_argumentos(self):
        with mock.patch.object(
            sys, "argv", ["retrieve.py", "consulta", "--top", "0"]
        ), contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                retrieve.main()
        self.assertEqual(ctx.exception.code, 2)


class IngestCliTest(unittest.TestCase):
    def _fake_extraction(self, source: str, text: str = "texto de prueba"):
        return extractors.Extraction(
            items=[
                extractors.Extracted(
                    source=source,
                    kind="docx",
                    units=[extractors.Unit("documento", text)],
                )
            ]
        )

    def test_dry_run_no_embebe_ni_escribe(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "a.docx"
            path.write_text("x", encoding="utf-8")
            store = mock.Mock()
            with mock.patch.object(
                ingest.extractors, "extract_path", return_value=self._fake_extraction("a.docx")
            ), mock.patch.object(
                ingest.vectorstore, "QdrantStore", return_value=store
            ), mock.patch.object(
                ingest.embeddings, "embed_texts"
            ) as embed:
                code, stdout, stderr = _run_module(
                    ingest, ["ingest.py", str(path), "--dry-run"]
                )

        self.assertEqual(code, 0)
        self.assertIn("DRY-RUN", stdout)
        self.assertEqual(stderr, "")
        embed.assert_not_called()
        store.upsert.assert_not_called()
        store.ensure_collection.assert_not_called()

    def test_batch_cero_es_error_de_argumentos(self):
        with mock.patch.object(
            sys, "argv", ["ingest.py", "x", "--batch", "0"]
        ), contextlib.redirect_stderr(io.StringIO()):
            with self.assertRaises(SystemExit) as ctx:
                ingest.main()
        self.assertEqual(ctx.exception.code, 2)

    def test_doc_id_con_varias_fuentes_pide_manifiesto(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "a.docx"
            path.write_text("x", encoding="utf-8")
            extraction = extractors.Extraction(
                items=[
                    extractors.Extracted(
                        source="a.docx",
                        kind="docx",
                        units=[extractors.Unit("documento", "uno")],
                    ),
                    extractors.Extracted(
                        source="b.docx",
                        kind="docx",
                        units=[extractors.Unit("documento", "dos")],
                    ),
                ]
            )
            with mock.patch.object(
                ingest.extractors, "extract_path", return_value=extraction
            ), mock.patch.object(ingest.vectorstore, "QdrantStore") as store:
                code, stdout, stderr = _run_module(
                    ingest, ["ingest.py", str(path), "--doc-id", "5", "--dry-run"]
                )

        self.assertEqual(code, 1)
        self.assertIn("doc-id-map", stderr)
        store.return_value.upsert.assert_not_called()

    def test_colision_de_basename_aborta(self):
        with tempfile.TemporaryDirectory() as tmp:
            first = Path(tmp) / "x" / "doc.txt"
            second = Path(tmp) / "y" / "doc.txt"
            first.parent.mkdir(parents=True)
            second.parent.mkdir(parents=True)
            first.write_text("uno", encoding="utf-8")
            second.write_text("dos", encoding="utf-8")

            def fake_extract(path):
                return self._fake_extraction("doc.txt", text=str(path))

            with mock.patch.object(
                ingest.extractors, "extract_path", side_effect=fake_extract
            ), mock.patch.object(ingest.vectorstore, "QdrantStore") as store:
                code, stdout, stderr = _run_module(
                    ingest,
                    ["ingest.py", str(first), str(second), "--dry-run"],
                )

        self.assertEqual(code, 1)
        self.assertIn("colision", stderr)
        store.return_value.upsert.assert_not_called()

    def test_doc_id_map_asigna_por_fuente(self):
        with tempfile.TemporaryDirectory() as tmp:
            first = Path(tmp) / "a.docx"
            second = Path(tmp) / "b.docx"
            first.write_text("uno", encoding="utf-8")
            second.write_text("dos", encoding="utf-8")
            manifest = Path(tmp) / "ids.json"
            manifest.write_text(
                json.dumps({"a.docx": 10, "b.docx": 11}), encoding="utf-8"
            )

            def fake_extract(path):
                return self._fake_extraction(path.name, text=path.name)

            with mock.patch.object(
                ingest.extractors, "extract_path", side_effect=fake_extract
            ), mock.patch.object(ingest.vectorstore, "QdrantStore"):
                code, stdout, stderr = _run_module(
                    ingest,
                    [
                        "ingest.py",
                        str(first),
                        str(second),
                        "--doc-id-map",
                        str(manifest),
                        "--dry-run",
                    ],
                )

        self.assertEqual(code, 0)
        self.assertIn("doc_id=10", stdout)
        self.assertIn("doc_id=11", stdout)
        self.assertEqual(stderr, "")


class IngestHelpersTest(unittest.TestCase):
    def _chunk(self, doc_id, index, source, source_key):
        return chunking.Chunk(
            text="texto",
            doc_id=doc_id,
            chunk_index=index,
            source=source,
            kind="docx",
            location="documento",
            tokens=2,
            source_key=source_key,
        )

    def test_source_conflicts_detecta_dos_fuentes(self):
        chunks = [
            self._chunk(1, 0, "x/doc.txt", "x/doc.txt"),
            self._chunk(1, 1, "y/doc.txt", "y/doc.txt"),
        ]
        conflicts = ingest._source_conflicts(chunks)
        self.assertIn(1, conflicts)
        self.assertEqual(conflicts[1], ["x/doc.txt", "y/doc.txt"])

    def test_orphan_ids(self):
        self.assertEqual(
            ingest._orphan_ids({"a", "b", "c"}, {"b", "c"}),
            ["a"],
        )

    def test_check_existing_sources_detecta_source_key_distinta(self):
        existing = [{"payload": {"doc_id": 7, "source_key": "viejo/x"}}]
        problems = ingest._check_existing_sources(existing, {7: ("nuevo/x", "x")})
        self.assertEqual(len(problems), 1)
        self.assertIn("doc_id=7", problems[0])


if __name__ == "__main__":
    unittest.main()
