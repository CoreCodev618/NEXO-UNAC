#!/usr/bin/env bash
# Prueba mínima del chat autenticado del PilotoV1.
#
# Uso:
#   BASE_URL=http://localhost:8080 ./scripts/test-ia.sh
#   BASE_URL=http://localhost:8083 ./scripts/test-ia.sh
#
# Usa una cuenta sintética del DemoSeeder. No contiene datos reales.
set -euo pipefail

BASE="${BASE_URL:-http://localhost:8080}"
EMAIL="${NEXO_DEMO_EMAIL:-demo@unac.edu.pe}"
PASSWORD="${NEXO_DEMO_PASSWORD:-NexoDemo2026!}"
COOKIE="$(mktemp)"
trap 'rm -f "$COOKIE"' EXIT

echo "== 1. Login =="
LOGIN_HTML="$(curl -sS -c "$COOKIE" "$BASE/login")"
CSRF_LOGIN="$(printf '%s' "$LOGIN_HTML" | sed -n 's/.*name="csrf_test_name" value="\([^"]*\)".*/\1/p' | head -1)"
if [ -z "$CSRF_LOGIN" ]; then
    echo "ERROR: no se encontró el CSRF del login." >&2
    exit 1
fi

STATUS="$(curl -sS -b "$COOKIE" -c "$COOKIE" -o /dev/null -w '%{http_code}' \
    -X POST "$BASE/login" \
    --data-urlencode "csrf_test_name=$CSRF_LOGIN" \
    --data-urlencode "email=$EMAIL" \
    --data-urlencode "password=$PASSWORD")"
if [ "$STATUS" != "303" ] && [ "$STATUS" != "302" ]; then
    echo "ERROR: login devolvió HTTP $STATUS (se esperaba redirección)." >&2
    exit 1
fi
echo "OK: sesión iniciada como $EMAIL"

echo "== 2. Token CSRF de la página =="
PAGE_HTML="$(curl -sS -b "$COOKIE" "$BASE/pages/1")"
CSRF="$(printf '%s' "$PAGE_HTML" | sed -n 's/.*<meta name="csrf-token" content="\([^"]*\)".*/\1/p' | head -1)"
if [ -z "$CSRF" ]; then
    echo "ERROR: no se encontró el meta csrf-token." >&2
    exit 1
fi
echo "OK: token obtenido"

echo "== 3. POST /api/v1/chat (página 1) =="
RESPONSE="$(curl -sS -b "$COOKIE" \
    -X POST "$BASE/api/v1/chat" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -H "X-CSRF-TOKEN: $CSRF" \
    -d '{"page_id":1,"message":"¿Qué es la relajación lineal y cómo se usa en ramificación y acotación?"}')"

if command -v jq >/dev/null 2>&1; then
    printf '%s' "$RESPONSE" | jq '.data | {retrieval, degraded, sources: [.sources[]? | {page_id, workspace_id, title}], reply: (.reply[0:240])}'
else
    printf '%s\n' "$RESPONSE" | head -c 1200
    echo
fi
