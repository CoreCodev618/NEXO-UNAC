"""OCR de imagenes (pizarras, examenes, apuntes) con Gemini Vision.

Usa la misma GEMINI_API_KEY de los embeddings. El modelo es configurable:
GEMINI_OCR_MODEL en .env (default: gemini-3.8-flash).
"""
from __future__ import annotations

import base64
import mimetypes
from pathlib import Path

import requests

from . import env

_API = "https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key={key}"
DEFAULT_MODEL = "gemini-3.8-flash"

PROMPT = (
    "Transcribe TODO el texto de esta imagen (foto de examen, pizarra o material de clase). "
    "Reglas: manten el orden de lectura; transcribe formulas y operaciones tal cual (texto plano o LaTeX simple); "
    "si hay tablas, diagramas o graficos, describelos en una linea empezando con [FIGURA: ...]; "
    "marca lo que no se lea como [ilegible]. No inventes ni completes nada que no se vea en la imagen."
)


def ocr_image(path: Path, model: str | None = None) -> str:
    """Devuelve el texto transcrito de la imagen."""
    key = env.get("GEMINI_API_KEY")
    if not key:
        raise RuntimeError("Falta GEMINI_API_KEY en el .env del repo")
    model = model or env.get("GEMINI_OCR_MODEL", DEFAULT_MODEL)

    mime = mimetypes.guess_type(path.name)[0] or "image/jpeg"
    data = base64.b64encode(path.read_bytes()).decode("ascii")

    payload = {
        "contents": [
            {
                "parts": [
                    {"text": PROMPT},
                    {"inline_data": {"mime_type": mime, "data": data}},
                ]
            }
        ],
        "generationConfig": {"temperature": 0.0},
    }

    resp = requests.post(_API.format(model=model, key=key), json=payload, timeout=180)
    if resp.status_code != 200:
        raise RuntimeError(f"Gemini OCR HTTP {resp.status_code}: {resp.text[:300]}")

    body = resp.json()
    candidates = body.get("candidates") or []
    if not candidates:
        raise RuntimeError(f"Gemini OCR sin respuesta util: {str(body)[:300]}")

    parts = (candidates[0].get("content") or {}).get("parts") or []
    return "\n".join(p.get("text", "") for p in parts).strip()
