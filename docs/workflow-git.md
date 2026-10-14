# Workflow Git — NEXO-UNAC (PRs sin conflictos)

## Ramas
- `main` — protegida, solo entra por PR aprobado. Demo siempre verde.
- `feature/backend-bd` — Int.1, dueño `app/Database, app/Models, app/Controllers/Api, app/Filters, app/Views/events, workers/`.
- `feature/frontend-ux` — Int.2, dueño `app/Views (menos events/), public/css, public/js (menos events.js)`.
- `feature/ia-chatbot` — Int.3 (tú), dueño `app/Services/AIService*, app/Controllers/Api/V1/AIChatController.php, python/, scripts/test-ia.sh`.

Regla anti-conflictos: nadie toca carpeta de otro. Contratos en `docs/arquitectura.md` sección 8.

## Flujo PR
```bash
git checkout feature/ia-chatbot
git pull origin feature/ia-chatbot
# trabajar + test terminal
git add <solo tus carpetas>
git commit -m "ia: <qué hace> (ej. ia: chat_sessions + GeminiProvider)"
git push origin feature/ia-chatbot
gh pr create --base main --head feature/ia-chatbot --title "IA: ..." --body "Test: ./scripts/test-ia.sh / curl ... Sin conflictos con: ..."
```
- Merge solo si CI verde + 1 approve + sin conflictos.
- Si hay conflicto: `git fetch origin main && git rebase origin/main`, resolver solo tu archivo, `git push --force-with-lease`.
- PRs chicos (<300 líneas), 1 tema por PR.

## Test IA en terminal (tu flujo hasta que haya front)
```bash
php spark serve # terminal 1
# terminal 2:
./scripts/test-ia.sh
# casos: crear sesión, enviar mensaje, historial, contexto page_id, rate-limit
php spark migrate:status
tail -f writable/logs/log-*.log
```
No necesitas Views. Todo por `curl` a `/api/v1/chat`.

## Convenciones commit
`backend: ...` / `frontend: ...` / `ia: ...` / `events: ...` / `docs: ...`
