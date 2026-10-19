# Progreso IA - NEXO-UNAC

Estado del modulo IA/RAG por fases. Un PR pequeno por hito, probado en terminal antes de abrir PR.
Rama de trabajo: `feature/ia-chatbot`. `main` solo recibe merge aprobado.

> Ultima actualizacion: 2026-10-18 · Rama: `feature/ia-chatbot`

## Fases

| # | Fase | Estado | Evidencia |
|---|------|--------|-----------|
| IA-0 | Provider free (Groq) + runbook + bootstrap Qdrant | OK (en PR) | `scripts/test-provider.sh`, `scripts/qdrant-init.sh`, `docs/ia-setup.md` |
| IA-1 | Contrato `AIServiceInterface` + factory por `.env` | ⬜ (espera CI4 en `main`) | |
| IA-2 | Prompts diario de clases (resumen, por fecha/tema, sin contexto -> "no puedo confirmar") | ⬜ | |
| IA-3 | Extraccion PDF/PPT + OCR imagenes de pizarra | ⬜ | `python/` |
| IA-4 | Chunking 500 tokens con metadata curso/fecha/tema + embeddings | ⬜ | |
| IA-5 | Ingest a Qdrant + retrieval filtrado top-5 | ⬜ | |
| IA-6 | `/api/v1/chat` con `chat_sessions` + citas a material original | ⬜ | |
| IA-7 | (Opcional) resumen IA de eventos para Tech Radar | ⬜ | |

## Decisiones cerradas
- **Proveedor free por defecto:** Groq `llama-3.3-70b-versatile` (fallback `llama-3.1-8b-instant`). Cambiable por `.env` via factory (DeepSeek u otro OpenAI-compatible sin tocar el controller).
- **Embeddings:** Gemini `text-embedding-004` (768) por defecto; alternativa local MiniLM (384).
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
```
