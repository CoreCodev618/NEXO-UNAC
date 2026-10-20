# Progreso IA - NEXO-UNAC

Estado del modulo IA/RAG por fases. Un PR pequeno por hito, probado en terminal antes de abrir PR.
Rama de trabajo: `feature/ia-chatbot`. `main` solo recibe merge aprobado.

> Ultima actualizacion: 2026-10-20 · Rama: `feature/ia-chatbot`

## Fases

| # | Fase | Estado | Evidencia |
|---|------|--------|-----------|
| IA-0 | Provider free (Groq) + runbook + bootstrap Qdrant | ✅ mergeado (PR #2) | `scripts/test-provider.sh`, `scripts/qdrant-init.sh`, `docs/ia-setup.md` |
| IA-1 | Contrato `AIServiceInterface` + factory por `.env` | ⬜ (espera CI4 en `main`) | |
| IA-2 | Prompts diario de clases (resumen, por fecha/tema, sin contexto -> "no puedo confirmar") | ⬜ | |
| IA-3 | Extraccion docx/pptx/pdf + OCR fotos + codigo/zip | ✅ mergeado (PR #6) | `python/` (`extract.py` + `nexo/`) |
| IA-4 | Chunking 500 tokens con metadata curso/fecha/tema + embeddings | ✅ (en PR) | `python/nexo/chunking.py`, `embeddings.py`, `python/chunk.py` |
| IA-5 | Ingest a Qdrant + retrieval filtrado top-5 | ⬜ | |
| IA-6 | `/api/v1/chat` con `chat_sessions` + citas a material original | ⬜ | |
| IA-7 | (Opcional) resumen IA de eventos para Tech Radar | ⬜ | |

## Decisiones cerradas
- **Proveedor free por defecto:** Groq `openai/gpt-oss-120b` (fallback `openai/gpt-oss-20b`). Cambiable por `.env` via factory (DeepSeek u otro OpenAI-compatible sin tocar el controller). Nota: la familia `llama-3.x` ya no esta disponible en Groq; los disponibles son `gpt-oss-120b/20b` y `qwen3.8-27b`.
- **Embeddings:** Gemini `gemini-embedding-001` (768 via `outputDimensionality`; probado 2026-10-19). `text-embedding-004` quedo retirado para cuentas nuevas; la coleccion Qdrant (768) no cambia. Alternativa local MiniLM (384, `EMBED_DIM=384`).
- **OCR de fotos/pizarras:** Gemini Vision `gemini-3.8-flash` (`GEMINI_OCR_MODEL`), con fallback a `gemini-3.7-flash`/`3.6` y reintentos ante 503/timeout. Probado con formulas a mano -> LaTeX + descripcion de figuras.
- **Chunking:** **<=500 tokens** con 50 de solape, corte por oracion, metadata `curso_id, class_date, topic, file_path, location`; `doc_id` estable por hash de la ruta (o `--doc-id` explicito); conteo real con tiktoken (`cl100k_base`). OCR y embeddings se cachean en `.cache/` (gitignored) para no gastar cuota free.
- **Seguridad:** la `GEMINI_API_KEY` viaja en el header `x-goog-api-key` (nunca en la URL) y no se incluye en los mensajes de error.
- **Qdrant:** los vectores de Gemini no vienen normalizados -> coleccion con `distance=Cosine`.
- **RAG es nucleo del producto** (no "V2 opcional"): MySQL = texto + metadata (fuente de verdad), Qdrant = vectores. Caso de uso: diario de clases FIIS (foto/PDF por fecha y tema, consultable por lenguaje natural).
- **Anti-alucinacion:** temperature 0.2, contexto acotado (<4k tokens), cita obligatoria o "no puedo confirmar".

## Contratos con otros modulos
- Respuestas API del backend: `{ success, data, message, errors }` (ver `docs/ProgresoBackend.md`).
- Tablas IA ya migradas por backend: `chat_sessions`, `chat_messages`, `documents`, `document_chunks`.
- Archivos fisicos: `writable/uploads/...` -> `attachments.file_path` (MySQL solo guarda ruta).
- Este modulo NO toca migraciones MySQL ni la vista `/events`.

## Como verificar (terminal)
```bash
qdrant-up                  # contenedor global (~/docker-infra)
./scripts/qdrant-init.sh
./scripts/test-provider.sh

# IA-3: extraer material (venv en python/.venv)
python/.venv/bin/python python/extract.py "../samples/Semana 7 Programación entera.pptx"
python/.venv/bin/python python/extract.py "../samples/SEMANA6/ci42" --preview 0

# IA-4: chunking + embeddings + retrieval de prueba
python/.venv/bin/python python/chunk.py "../samples/Semana 7 Programación entera.pptx" \
    --embed --query "¿Qué es programación entera?"
python/.venv/bin/python -m unittest discover -s python/tests -v
```
