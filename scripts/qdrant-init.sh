#!/usr/bin/env bash
# IA-0: crea la coleccion nexo_docs en Qdrant local (idempotente).
# Uso: ./scripts/qdrant-init.sh
# EMBED_DIM: 768 = Gemini gemini-embedding-001 (default) | 384 = MiniLM local
set -euo pipefail

QDRANT_URL="${QDRANT_URL:-http://localhost:6333}"
DIM="${EMBED_DIM:-768}"
COL="nexo_docs"
AUTH=()
if [ -n "${QDRANT_API_KEY:-}" ]; then
  AUTH=(-H "api-key: $QDRANT_API_KEY")
fi

echo "== qdrant-init | $QDRANT_URL | dim=$DIM | coleccion=$COL =="

if ! curl -sS --max-time 5 "${AUTH[@]}" "$QDRANT_URL/collections" > /dev/null 2>&1; then
  echo "ERROR: Qdrant no responde. Levanta el contenedor: qdrant-up"
  exit 1
fi

CODE="$(curl -sS -o /dev/null -w '%{http_code}' "${AUTH[@]}" "$QDRANT_URL/collections/$COL")"
if [ "$CODE" = "200" ]; then
  echo "-> la coleccion ya existe (no se toca)"
else
  echo "-> creando coleccion..."
  curl -sS -X PUT "${AUTH[@]}" "$QDRANT_URL/collections/$COL" \
    -H 'Content-Type: application/json' \
    -d "{\"vectors\": {\"size\": $DIM, \"distance\": \"Cosine\"}}" | python3 -m json.tool
fi

echo "-> indices de payload (filtros curso/fecha/tema):"
for field in chunk_id doc_id page_id curso_id; do
  curl -sS -X PUT "${AUTH[@]}" "$QDRANT_URL/collections/$COL/index" \
    -H 'Content-Type: application/json' \
    -d "{\"field_name\": \"$field\", \"field_schema\": \"integer\"}" > /dev/null
  echo "   $field (integer)"
done
for field in class_date topic topic_norm; do
  curl -sS -X PUT "${AUTH[@]}" "$QDRANT_URL/collections/$COL/index" \
    -H 'Content-Type: application/json' \
    -d "{\"field_name\": \"$field\", \"field_schema\": \"keyword\"}" > /dev/null
  echo "   $field (keyword)"
done

echo "== OK =="
echo "Dashboard: $QDRANT_URL/dashboard -> Collections > $COL"
