"""Tests de embeddings (IA-4/IA-5) sin red ni cuota.

Verifican el contrato que necesita `retrieve.py --json`:
- el progreso va a stderr (stdout queda limpio);
- textos repetidos se embeben una sola vez;
- las estadisticas de cache son exactas.
"""
from __future__ import annotations

import contextlib
import io
import sys
import unittest
from pathlib import Path
from unittest import mock

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))

from nexo import embeddings  # noqa: E402


class EmbeddingStatsTest(unittest.TestCase):
    def _env_get(self, key, default=""):
        if key == "GEMINI_API_KEY":
            return "fake-key"
        return default

    def test_progreso_va_a_stderr_y_dedupea_textos(self):
        calls = []

        def fake_post(url, payload, headers):
            calls.append(payload)
            return {
                "embeddings": [
                    {"values": [float(index)] * 768}
                    for index, _ in enumerate(payload["requests"])
                ]
            }

        with mock.patch.object(embeddings, "_cache_read", return_value=None), \
                mock.patch.object(embeddings, "_cache_write") as cache_write, \
                mock.patch.object(embeddings, "_post_with_retry", side_effect=fake_post), \
                mock.patch.object(embeddings.env, "get", side_effect=self._env_get):
            stdout = io.StringIO()
            stderr = io.StringIO()
            with contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
                vectors, stats = embeddings.embed_texts(
                    ["a", "a", "b"], return_stats=True
                )

        self.assertEqual(stdout.getvalue(), "")
        self.assertIn("embeddings:", stderr.getvalue())
        self.assertEqual(len(vectors), 3)
        self.assertEqual(vectors[0], vectors[1])
        self.assertNotEqual(vectors[0], vectors[2])
        self.assertEqual(len(calls), 1)
        self.assertEqual(len(calls[0]["requests"]), 2)
        self.assertEqual(stats.requested, 3)
        self.assertEqual(stats.unique, 2)
        self.assertEqual(stats.cached, 0)
        self.assertEqual(stats.new, 2)
        self.assertEqual(cache_write.call_count, 2)

    def test_cache_hit_no_imprime_ni_llama_api(self):
        with mock.patch.object(
            embeddings, "_cache_read", return_value=[0.25] * 768
        ), mock.patch.object(embeddings, "_post_with_retry") as post, \
                mock.patch.object(embeddings.env, "get", side_effect=self._env_get):
            stdout = io.StringIO()
            stderr = io.StringIO()
            with contextlib.redirect_stdout(stdout), contextlib.redirect_stderr(stderr):
                vectors, stats = embeddings.embed_texts(
                    ["a", "b"], return_stats=True
                )

        self.assertEqual(stdout.getvalue(), "")
        self.assertEqual(stderr.getvalue(), "")
        self.assertEqual(len(vectors), 2)
        self.assertEqual(stats.cached, 2)
        self.assertEqual(stats.new, 0)
        post.assert_not_called()

    def test_sin_api_key_falla_sin_escribir_stdout(self):
        def env_get(key, default=""):
            return default

        with mock.patch.object(embeddings, "_cache_read", return_value=None), \
                mock.patch.object(embeddings.env, "get", side_effect=env_get):
            stdout = io.StringIO()
            with contextlib.redirect_stdout(stdout):
                with self.assertRaises(RuntimeError):
                    embeddings.embed_texts(["a"])
        self.assertEqual(stdout.getvalue(), "")

    def test_sin_stats_devuelve_lista(self):
        with mock.patch.object(
            embeddings, "_cache_read", return_value=[0.25] * 768
        ), mock.patch.object(embeddings.env, "get", side_effect=self._env_get):
            vectors = embeddings.embed_texts(["a"])
        self.assertIsInstance(vectors, list)
        self.assertEqual(len(vectors), 1)

    def test_query_vacia(self):
        with self.assertRaises(ValueError):
            embeddings.embed_query("   ")


if __name__ == "__main__":
    unittest.main()
