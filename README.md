# NEXO-UNAC
Knowledge Workspace estilo Notion para FIIS-UNAC + Chatbot IA (RAG como V2). CodeIgniter 4.

Piloto solo FIIS. Acceso solo `@unac.edu.pe`. 17 tablas (15 V1 + 2 V2). Eventos reales con fuente verificable.

## Equipo x3
- `feature/backend-bd` — Migraciones, Models, Api/V1, Auth dominio, Eventos + vista `events/` en exclusiva (libre: manual / cron / Cloudflare Worker).
- `feature/frontend-ux` — Views, layout Notion, editor bloques, Kanban, widget chat UI. No SQL, no toca `events/`.
- `feature/ia-chatbot` — `AIService + GeminiProvider + AIChatController`, `chat_sessions` obligatorio. Único dueño Qdrant (V2). No toca migraciones.

## Quickstart
```bash
composer install
cp .env.example .env
# configurar database + ALLOWED_EMAIL_DOMAINS=unac.edu.pe + GEMINI_API_KEY
php spark migrate --all
php spark db:seed WorkspaceSeeder   # también siembra FacultySeeder (FIIS)
php spark serve
```

## Test IA en terminal (sin front)
```bash
chmod +x scripts/test-ia.sh
./scripts/test-ia.sh
# o directo:
curl -s http://localhost:8080/api/v1/chat -X POST -H "Content-Type: application/json" -d '{"session_id":1,"message":"Hola NEXO"}' | jq
```

## Flujo Git
Ver `docs/workflow-git.md`. Todo a `main` vía PR. Nada directo a `main`.
