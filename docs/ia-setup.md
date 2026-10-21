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
Divide el texto extraido en chunks de **<=500 tokens** (solape 50, corte por oracion) con metadata
`curso_id, class_date, topic, file_path, location`, y calcula el vector de 768 dims por chunk.
Cada fuente recibe un `doc_id` estable por hash de su etiqueta de fuente; en produccion pasar
`--doc-id N` con el `documents.id` de MySQL (IA-5).

```bash
cd ~/Documents/NEXO-UNAC/ia
python/.venv/bin/python python/chunk.py "../samples"          # solo chunking (rapido)
python/.venv/bin/python python/chunk.py "../samples/Semana 7 Programación entera.pptx" \
    --embed --query "¿Qué es programación entera?" --out /tmp/opencode/ia4-out

# tests de chunking (sin gastar cuota)
python/.venv/bin/python -m unittest discover -s python/tests -v
```

Resultados (2026-10-20): silabo 15 chunks · PPT 46 · 2 fotos 2 · carpeta `ci42` 146 (max 500 tokens).
Retrieval de prueba: "¿Cómo se resuelve por compuertas NAND?" -> foto pt2 (0.757) y pt1 (0.677);
"¿Cómo se define una ruta en CodeIgniter 4?" -> `app/Config/Routes.php` (0.756).

Cache local en `.cache/` (gitignored): evita repetir OCR/embeddings y cuida la cuota free.
La `GEMINI_API_KEY` viaja en el header `x-goog-api-key` (nunca en la URL ni en logs).

## Ingest + retrieval (IA-5, `python/`)
`ingest.py` extrae, chunkea (con `doc_id` estable o `--doc-id` de MySQL), dedupe por
`(doc_id, chunk_index)`, asegura la coleccion/indices, calcula embeddings (cache) y hace
upsert por lotes. Es **idempotente**: el id de punto es
`uuid5(NAMESPACE_URL, "nexo:{doc_id}:{chunk_index}")`, asi que repetir el ingest actualiza
los mismos puntos y `count()` no crece.

```bash
cd ~/Documents/NEXO-UNAC/ia

# grupo A: silabo + carpeta de codigo (curso 2)
python/.venv/bin/python python/ingest.py \
    "../samples/SILABUS  Programacion Web II 2026B.docx" "../samples/SEMANA6/ci42" \
    --curso-id 2 --class-date 2026-10-20 --topic "Programación Web II"

# grupo B: PPT (curso 3) y grupo C: fotos OCR (curso 4)
python/.venv/bin/python python/ingest.py "../samples/Semana 7 Programación entera.pptx" \
    --curso-id 3 --class-date 2026-10-20 --topic "Investigación de Operaciones"
python/.venv/bin/python python/ingest.py \
    "../samples/Resolucion PC1 Sistemas Digitales pt1.jpeg" \
    "../samples/Resolucion PC1 Sistemas Digitales pt2.jpeg" \
    --curso-id 4 --class-date 2026-10-19 --topic "Sistemas Digitales"

# plan sin embeddings ni escritura
python/.venv/bin/python python/ingest.py "../samples" --dry-run

# retrieval top-5, filtros opcionales y salida JSON
python/.venv/bin/python python/retrieve.py "¿Cómo se resuelve por compuertas NAND?"
python/.venv/bin/python python/retrieve.py "¿Qué es la programación entera?" \
    --topic "Investigación de Operaciones" --top 3
python/.venv/bin/python python/retrieve.py "¿Cómo se define una ruta en CodeIgniter 4?" \
    --curso-id 2 --json
```

Resultado de la prueba (2026-10-21): 161 + 46 + 2 = **209 puntos**; reingestar el grupo A
dejo `count()` en 209. "NAND" sin filtro -> fotos 0.757/0.677; con `--class-date 2026-10-19`
solo fotos; con `2026-10-20` no aparecen. "Ruta en CodeIgniter 4" + `--curso-id 2` -> top-1
`app/Config/Routes.php` (0.756); con `--curso-id 4` no aparece.

### Payload y filtros
| Campo | Tipo Qdrant | Uso |
|---|---|---|
| `chunk_id` | integer | indice del chunk dentro del documento (NO es el id global) |
| `doc_id` | integer | `documents.id` de MySQL (`--doc-id`) o hash estable de la fuente |
| `page_id` | integer | 0 por ahora; pagina/slide real cuando MySQL lo asigne |
| `curso_id` | integer | filtro por curso |
| `class_date` | keyword | filtro por fecha `YYYY-MM-DD` |
| `topic` | keyword | filtro por tema |
| `file_path`, `source`, `location`, `kind` | keyword/texto | cita de la fuente |
| `text` | texto | retrieval/demo; la fuente de verdad sigue en MySQL |
| `tokens`, `chunk_key` | integer/keyword | diagnostico y trazabilidad `"doc_id-chunk_index"` |

`build_filter()` usa `match.value` en `must`: `curso_id`, `doc_id` (integer) y
`class_date`, `topic` (keyword). Sin filtros devuelve `None` (busqueda global).
`--min-score > 0` se traduce a `score_threshold`.

## Coleccion `nexo_docs`
- Vector: `size=EMBED_DIM`, `distance=Cosine`.
- Los embeddings de Gemini no vienen normalizados: usar `Cosine`, no `Dot`.
- Indices de payload: `chunk_id, doc_id, page_id, curso_id` (integer) y
  `class_date, topic` (keyword); `ensure_payload_indexes()` es idempotente.
- Dedupe de ingesta: `doc_id + chunk_index` (ademas del UUID5 determinista del punto).

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
| `Qdrant no responde` | contenedor apagado | `qdrant-up`; `ingest.py`/`retrieve.py` muestran la URL y el comando |
| `size=... y se esperaba ...` | `EMBED_DIM` distinto al de la coleccion | volver al `EMBED_DIM` original o usar otra `--collection` (no borrar `nexo_docs`) |
| `distance=... y se esperaba Cosine` | coleccion creada con otra metrica | recrear en otra coleccion con `Cosine` y reingestar |
| `count()` no sube tras reingestar | idempotencia UUID5 (esperado) | verificar `points_count` en `/collections/nexo_docs` |
| `docker.sock no existe` | daemon apagado | `dockerd-up` |
| HTTP 401 Groq | key invalida | revisar `GROQ_API_KEY` |
| HTTP 429 Groq | rate limit free | esperar o `GROQ_MODEL=openai/gpt-oss-20b` |
| HTTP 404 model_not_found | modelo retirado | listar con `curl https://api.groq.com/openai/v1/models -H "Authorization: Bearer $GROQ_API_KEY"` |
| HTTP 404 Gemini "no longer available to new users" | Google retiro el modelo para cuentas nuevas | usar el modelo que sugiere el error (ej. `gemini-3.8-flash`) via `GEMINI_OCR_MODEL` |
| HTTP 429 Gemini (quota/TPM) | limite del free tier | el cliente espera `retryDelay` y reintenta; la cache evita regastar cuota |
| OCR 503 "high demand" o timeout | modelo saturado | reintenta y prueba `gemini-3.7-flash`/`3.6`; el texto queda cacheado |

## Roadmap
Ver `docs/ProgresoIA.md`.
