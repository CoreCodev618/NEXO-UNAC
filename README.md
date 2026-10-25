# NEXO-UNAC
Knowledge Workspace estilo Notion para FIIS-UNAC + Chatbot IA (RAG como V2). CodeIgniter 4.

Piloto solo FIIS. Acceso solo `@unac.edu.pe`. 17 tablas (15 V1 + 2 V2). Eventos reales con fuente verificable.

## PilotoV1 (demo académica)

La demo lista para el profesor está en la rama `feature/piloto-v1` (PR #12). Usa
datos **sintéticos**, roles `viewer < editor < owner` y chat con fallback RAG
local cuando no hay claves externas.

```bash
docker compose up --build -d
# abrir http://localhost:8083/login
```

La pantalla de login muestra las cuentas demo. La guía completa (Docker,
túnel y Render Free) está en [`docs/despliegue.md`](docs/despliegue.md).

## Equipo x3
- `feature/backend-bd` — Migraciones, Models, Api/V1, Auth dominio, Eventos + vista `events/` en exclusiva (libre: manual / cron / Cloudflare Worker).
- `feature/frontend-ux` — Views, layout Notion, editor bloques, Kanban, widget chat UI. No SQL, no toca `events/`.
- `feature/ia-chatbot` — `AIService + GeminiProvider + AIChatController`, `chat_sessions` obligatorio. Único dueño Qdrant (V2). No toca migraciones.

## Quickstart de desarrollo (sin Docker)
```bash
composer install
cp .env.example .env
# configurar database + ALLOWED_EMAIL_DOMAINS=unac.edu.pe
php spark migrate --all
php spark db:seed DemoSeeder
php spark serve --port 8080
```

## Test IA en terminal (sin front)
```bash
BASE_URL=http://localhost:8080 ./scripts/test-ia.sh
```
El script hace login con una cuenta demo, obtiene el CSRF y consulta el chat.
Sin `GEMINI_API_KEY`/Qdrant el resultado debe traer `retrieval: "local"` y
`degraded: true`; con esas claves configuradas debe usar Qdrant.

## Flujo Git
Ver `docs/workflow-git.md`. Todo a `main` vía PR. Nada directo a `main`.
