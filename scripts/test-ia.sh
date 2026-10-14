#!/bin/bash
set -e
BASE=${BASE_URL:-http://localhost:8080}
echo "== 1. Health =="
curl -s "$BASE/" -o /dev/null -w "HTTP %{http_code}\n" || echo "WARN: spark no corre? php spark serve"
echo "== 2. POST /api/v1/chat (V1 sin front) =="
curl -s "$BASE/api/v1/chat" -X POST -H "Content-Type: application/json" \
  -d '{"message":"Hola NEXO, soy de FIIS Sistemas, que eventos IA hay?","page_id":null}' | head -c 800
echo ""
echo "== 3. Historial sesión =="
curl -s "$BASE/api/v1/chat/sessions/1/messages" | head -c 500 || echo "(endpoint historial pendiente backend: chat_sessions)"
echo ""
echo "OK: si ves JSON con reply/session_id, IA V1 funciona."
