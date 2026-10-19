# IA Setup - NEXO-UNAC (chatbot + RAG)

Runbook del modulo IA (integrante 3). Todo se prueba en terminal, sin frontend.

## Prerrequisitos
- Docker global en `~/docker-infra` (contenedores `mariadb` y `qdrant`).
- `GROQ_API_KEY` free: https://console.groq.com -> API Keys.

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
| `GROQ_MODEL` | `llama-3.3-70b-versatile` | modelo (fallback: `llama-3.1-8b-instant`) |
| `QDRANT_URL` | `http://localhost:6333` | vector DB |
| `EMBED_DIM` | `768` | dimension del embedding de la coleccion |

## Verificacion (terminal)
```bash
# 1. provider responde y no alucina
./scripts/test-provider.sh

# 2. comparar modelo rapido (menos alucinacion-control, mas velocidad)
GROQ_MODEL=llama-3.1-8b-instant ./scripts/test-provider.sh

# 3. coleccion vectorial lista (idempotente, se puede repetir)
./scripts/qdrant-init.sh

# 4. verificar en dashboard > Collections > nexo_docs
```

## Coleccion `nexo_docs`
- Vector: `size=EMBED_DIM`, `distance=Cosine`.
- Payload por chunk (metadata): `chunk_id, doc_id, page_id, curso_id` (integer) + `class_date, topic` (keyword) + `file_path`.
- Dedupe de ingesta: `doc_id + chunk_index`.

## Embedding
| Opcion | Dim | Costo | Nota |
|---|---|---|---|
| Gemini `text-embedding-004` | 768 | free tier | default actual |
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
| HTTP 429 Groq | rate limit free | esperar o `GROQ_MODEL=llama-3.1-8b-instant` |

## Roadmap
Ver `docs/ProgresoIA.md`.
