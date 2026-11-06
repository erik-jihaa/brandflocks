#!/usr/bin/env bash
# Wis alle pipeline-content voor één tenant (clients-strategie blijft staan)
# Gebruik: ./scripts/reset-client-content.sh <clientId>
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ ! -f "$ROOT/.env" ]]; then
  echo "Missing $ROOT/.env" >&2
  exit 1
fi

# shellcheck disable=SC1091
set -a
source "$ROOT/.env"
set +a

CLIENT_ID="${1:-}"
TOKEN="${CE_RESET_TOKEN:-}"
BASE_URL="${CE_PUBLIC_BASE_URL:-https://ce.igisnext.com}"

if [[ -z "$CLIENT_ID" || -z "$TOKEN" ]]; then
  echo "Usage: $0 <clientId>" >&2
  echo "CE_RESET_TOKEN moet in .env staan." >&2
  exit 1
fi

CE_ENV_NAME="${CE_ENV:-production}"

if [[ "$CE_ENV_NAME" == "production" ]]; then
  echo "Dit wist alle content van tenant $CLIENT_ID op PRODUCTIE."
  read -r -p "Typ 'wissen' om te bevestigen: " confirm
  if [[ "$confirm" != "wissen" ]]; then
    echo "Afgebroken."
    exit 1
  fi
fi

echo "==> Blank tenant prep: $CLIENT_ID"
curl -sS -X POST "$BASE_URL/api/admin/reset-client-content" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "{\"clientId\":\"$CLIENT_ID\",\"blankStrategy\":true,\"confirmEnvironment\":\"$CE_ENV_NAME\"}" \
  | python3 -m json.tool
