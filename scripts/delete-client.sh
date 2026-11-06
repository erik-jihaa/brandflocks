#!/usr/bin/env bash
# Verwijder een tenant (clients-rij + gekoppelde content) via POST /api/admin/delete-client
# Gebruik: ./scripts/delete-client.sh <clientId> "<exact bedrijfsnaam>"
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

BASE_URL="${CE_PUBLIC_BASE_URL:-https://ce.igisnext.com}"
TOKEN="${CE_RESET_TOKEN:-}"
CLIENT_ID="${1:-}"
COMPANY="${2:-}"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in .env" >&2
  exit 1
fi
if [[ -z "$CLIENT_ID" || -z "$COMPANY" ]]; then
  echo "Usage: $0 <clientId> \"<exact company name>\"" >&2
  exit 1
fi

CE_ENV_NAME="${CE_ENV:-production}"

JSON=$(CLIENT_ID="$CLIENT_ID" COMPANY="$COMPANY" CE_ENV_NAME="$CE_ENV_NAME" python3 - <<'PY'
import json, os
print(json.dumps({
    "clientId": os.environ["CLIENT_ID"],
    "confirmCompanyName": os.environ["COMPANY"],
    # De API weigert destructieve acties op productie zonder deze bevestiging.
    "confirmEnvironment": os.environ["CE_ENV_NAME"],
}))
PY
)

if [[ "$CE_ENV_NAME" == "production" ]]; then
  echo "Dit verwijdert '$COMPANY' definitief op PRODUCTIE."
  read -r -p "Typ de bedrijfsnaam om te bevestigen: " confirm
  if [[ "$confirm" != "$COMPANY" ]]; then
    echo "Afgebroken."
    exit 1
  fi
fi

echo "==> Deleting tenant: $COMPANY ($CLIENT_ID)"
curl -sS -X POST "$BASE_URL/api/admin/delete-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "$JSON" | python3 -m json.tool
