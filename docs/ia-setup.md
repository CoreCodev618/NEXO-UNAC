# IA Setup - NEXO-UNAC (chatbot + RAG)

Runbook del modulo IA (integrante 3). Todo se prueba en terminal, sin frontend.

## Prerrequisitos
- Docker global en `~/docker-infra` (contenedores `mariadb` y `qdrant`).
- `GROQ_API_KEY` free: https://console.groq.com -> API Keys.
- `GEMINI_API_KEY` free: https://aistudio.google.com -> API Keys (OCR IA-3 + embeddings IA-4).

## Contenedores (aliases globales)
```bash
dockerd-up     # si el daemon esta apagado (pide password)
mariadb-up     # MariaDB para backend/BD
qdrant-up      # Qdrant para RAG
d-ps           # estado de contenedores
qdrant-down    # apagar cuando no se use
```
- Qdrant dashboard: http://localhost:6333/dashboard
- Qdrant REST: http://localhost:6333/collections

## Variables (.env del repo, no se commitea)
| Variable | Default | Uso |
|---|---|---|
| `GROQ_API_KEY` | — | provider free del chatbot |
| `GROQ_MODEL` | `openai/gpt-oss-120b` | modelo (fallback: `openai/gpt-oss-20b`) |
| `GEMINI_API_KEY` | — | OCR de fotos (IA-3) + embeddings (IA-4) |
| `GEMINI_OCR_MODEL` | `gemini-3.8-flash` | modelo Vision que transcribe fotos/pizarras |
| `EMBED_MODEL` | `gemini-embedding-001` | modelo de embeddings (IA-4) |
| `QDRANT_URL` | `http://localhost:6333` | vector DB |
| `EMBED_DIM` | `768` | dimension del embedding de la coleccion |

## Verificacion (terminal)
```bash
# 1. provider responde y no alucina
./scripts/test-provider.sh

# 2. comparar modelo rapido (menos alucinacion-control, mas velocidad)
GROQ_MODEL=openai/gpt-oss-20b ./scripts/test-provider.sh

# 3. coleccion vectorial lista (idempotente, se puede repetir)
./scripts/qdrant-init.sh

# 4. verificar en dashboard > Collections > nexo_docs
```

## Extraccion de material (IA-3, `python/`)
Soporta `.docx`, `.pptx`, `.pdf`, fotos (OCR Gemini Vision), carpetas de codigo y `.zip`
(ignora `system/`, `vendor/`, `writable/`...; zip con limites anti zip-bomb, sin path traversal ni symlinks).

```bash
cd ~/Documents/NEXO-UNAC/ia
python3 -m venv python/.venv                              # una sola vez
python/.venv/bin/pip install -r python/requirements.txt

# ejemplos reales (probados 2026-10-19)
python/.venv/bin/python python/extract.py "../samples/Semana 7 Programación entera.pptx"
python/.venv/bin/python python/extract.py "../samples/SEMANA6/ci42" --preview 0
python/.venv/bin/python python/extract.py "../samples/SEMANA 6.zip" --out /tmp/opencode/ia3-out
```
Resultados de la prueba: docx 24K chars · pptx 41 unidades · OCR 2 fotos (formulas a mano -> LaTeX) · codigo 68 archivos (182K chars) · zip igual que la carpeta.

## Chunking + embeddings (IA-4, `python/`)
Divide el texto extraido en chunks de ~500 tokens (solape 50, corte por oracion) con metadata
`curso_id, class_date, topic, file_path, location`, y calcula el vector de 768 dims por chunk.

```bash
cd ~/Documents/NEXO-UNAC/ia
python/.venv/bin/python python/chunk.py "../samples"          # solo chunking (rapido)
python/.venv/bin/python python/chunk.py "../samples/Semana 7 Programación entera.pptx" \
    --embed --query "¿Qué es programación entera?" --out /tmp/opencode/ia4-out
```

Resultados (2026-10-20): silabo 14 chunks · PPT 45 · 2 fotos 2 · carpeta `ci42` 126.
Retrieval de prueba: "¿Cómo se resuelve por compuertas NAND?" -> foto pt2 (0.757) y pt1 (0.677);
"¿Cómo se define una ruta en CodeIgniter 4?" -> `app/Config/Routes.php` (0.756).

Cache local en `.cache/` (gitignored): evita repetir OCR/embeddings y cuida la cuota free.

## Coleccion `nexo_docs`
- Vector: `size=EMBED_DIM`, `distance=Cosine`.
- Payload por chunk (metadata): `chunk_id, doc_id, page_id, curso_id` (integer) + `class_date, topic` (keyword) + `file_path`.
- Dedupe de ingesta: `doc_id + chunk_index`.

## Embedding
| Opcion | Dim | Costo | Nota |
|---|---|---|---|
| Gemini `gemini-embedding-001` | 768 (`outputDimensionality`) | free tier | default actual (probado 2026-10-19); `text-embedding-004` quedo retirado para cuentas nuevas |
| Local `paraphrase-multilingual-MiniLM` | 384 | gratis offline | `EMBED_DIM=384` |

Cambiar de embedding (o de dim) = borrar coleccion en Qdrant y re-ingestar. MariaDB no se toca.

## Anti-alucinacion (reglas del modulo)
- `temperature 0.2`, `max_tokens 800` en produccion.
- Contexto acotado: historial ultimos 6 mensajes + extracto max 2000 chars de la clase actual.
- Serie RAG siempre con `fuentes: [...]` o "Con la informacion disponible no puedo confirmar eso."
- Prohibido inventar eventos: el Tech Radar solo publica `link_original` real (backend).

## Troubleshooting
| Sintoma | Causa | Fix |
|---|---|---|
| `Qdrant no responde` | contenedor apagado | `qdrant-up` |
| `docker.sock no existe` | daemon apagado | `dockerd-up` |
| HTTP 401 Groq | key invalida | revisar `GROQ_API_KEY` |
| HTTP 429 Groq | rate limit free | esperar o `GROQ_MODEL=openai/gpt-oss-20b` |
| HTTP 404 model_not_found | modelo retirado | listar con `curl https://api.groq.com/openai/v1/models -H "Authorization: Bearer $GROQ_API_KEY"` |
| HTTP 404 Gemini "no longer available to new users" | Google retiro el modelo para cuentas nuevas | usar el modelo que sugiere el error (ej. `gemini-3.8-flash`) via `GEMINI_OCR_MODEL` |
| HTTP 429 Gemini (quota/TPM) | limite del free tier | el cliente espera `retryDelay` y reintenta; la cache evita regastar cuota |
| OCR 503 "high demand" o timeout | modelo saturado | reintenta y prueba `gemini-3.7-flash`/`3.6`; el texto queda cacheado |

## Roadmap
Ver `docs/ProgresoIA.md`.
