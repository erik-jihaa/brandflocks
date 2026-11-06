#!/usr/bin/env bash
# Maak een nieuwe tenant (clients-rij) aan via POST /api/admin/create-client
# Gebruik: ./scripts/create-client.sh "Bedrijfsnaam" "contact@email.nl" [wachtwoord]
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

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in .env" >&2
  exit 1
fi

COMPANY="${1:-Demo BV}"
EMAIL="${2:-demo@igisnext.com}"
PASSWORD="${3:-}"

JSON=$(COMPANY="$COMPANY" EMAIL="$EMAIL" PASSWORD="$PASSWORD" python3 - <<'PY'
import json, os
body = {
    "company": os.environ["COMPANY"],
    "contactEmail": os.environ["EMAIL"],
    "contactName": os.environ["COMPANY"].split()[0],
    "contactRole": "both",
    "planningHorizonWeeks": 4,
    "postsPerWeek": 1,
    "archetype": "",
    "adversary": "",
    "brandstory": "",
    "writingGuide": "",
}
password = os.environ.get("PASSWORD", "")
if password:
    body["password"] = password
print(json.dumps(body))
PY
)

echo "==> Creating tenant: $COMPANY ($EMAIL)"
curl -sS -X POST "$BASE_URL/api/admin/create-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "$JSON" | python3 -m json.tool
