#!/usr/bin/env bash
# IA-0: prueba el proveedor LLM free (Groq) desde terminal, sin CI4.
# Uso:
#   ./scripts/test-provider.sh
#   GROQ_MODEL=openai/gpt-oss-20b ./scripts/test-provider.sh   # comparar modelo rapido
# Requiere: GROQ_API_KEY en el entorno o en .env del repo.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ -z "${GROQ_API_KEY:-}" ] && [ -f "$ROOT/.env" ]; then
  GROQ_API_KEY="$(grep -E '^GROQ_API_KEY[[:space:]]*=' "$ROOT/.env" | head -1 | cut -d= -f2- | tr -d '[:space:]')"
fi
: "${GROQ_API_KEY:?Falta GROQ_API_KEY: exportala o ponla en .env}"

MODEL="${GROQ_MODEL:-openai/gpt-oss-120b}"
API="https://api.groq.com/openai/v1/chat/completions"

SYSTEM_PROMPT="Eres el tutor academico de NEXO UNAC (FIIS - Universidad Nacional del Callao).
Responde en espanol claro y breve. Reglas:
1. Si no tienes un dato institucional verificado, responde exactamente: 'Con la informacion disponible no puedo confirmar eso.'
2. Nunca inventes notas, fechas, articulos de reglamento, nombres de docentes ni eventos.
3. Cuando uses contexto entregado, cita la fuente al final como 'FUENTE: <titulo>'."

USER_PROMPT="Prueba anti-alucinacion (maximo 2 lineas):
1) Cual es la nota minima aprobatoria exacta en FIIS-UNAC?
2) Quien es el decano actual de la FIIS?
Si no tienes el dato verificado, usa la frase de la regla 1."

PAYLOAD="$(python3 - "$MODEL" "$SYSTEM_PROMPT" "$USER_PROMPT" << 'PY'
import json, sys
model, system, user = sys.argv[1], sys.argv[2], sys.argv[3]
print(json.dumps({
    "model": model,
    "temperature": 0.2,
    "max_tokens": 300,
    "messages": [
        {"role": "system", "content": system},
        {"role": "user", "content": user},
    ],
}))
PY
)"

TMP="$(mktemp)"
echo "== provider: groq | modelo: $MODEL =="
read -r CODE TIME < <(curl -sS -o "$TMP" -w '%{http_code} %{time_total}\n' "$API" \
  -H "Authorization: Bearer $GROQ_API_KEY" \
  -H "Content-Type: application/json" \
  -d "$PAYLOAD") || { echo "ERROR: no se pudo consultar la API (revisa conexion)"; rm -f "$TMP"; exit 1; }

if [ "$CODE" != "200" ]; then
  echo "FALLO HTTP $CODE (revisa key, limite o modelo)"
  python3 -m json.tool "$TMP" 2>/dev/null | head -20 || cat "$TMP" | head -c 400
  rm -f "$TMP"
  exit 1
fi

python3 - "$TMP" << 'PY'
import json, sys
d = json.load(open(sys.argv[1]))
print(d["choices"][0]["message"]["content"])
print("---")
print("tokens: prompt", d["usage"]["prompt_tokens"], "| completion", d["usage"]["completion_tokens"])
PY
echo "latencia: ${TIME}s (HTTP $CODE)"
rm -f "$TMP"
