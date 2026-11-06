#!/usr/bin/env bash
# Reset pipeline content on production (requires CE_RESET_TOKEN in .env)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ ! -f "$ROOT/.env" ]]; then
  echo "Missing $ROOT/.env" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source "$ROOT/.env"
set +a

TOKEN="${CE_RESET_TOKEN:-}"
BASE="${CE_PUBLIC_BASE_URL:-https://ce.igisnext.com}"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in .env — zet een geheim token voor remote reset." >&2
  exit 1
fi

CE_ENV_NAME="${CE_ENV:-production}"

# Dit wist de pipeline-content van ÁLLE tenants, niet één. Vandaar de dubbele drempel.
if [[ "$CE_ENV_NAME" == "production" ]]; then
  echo "LET OP: dit wist themes, slots, artikelen en feedback van ALLE tenants op PRODUCTIE."
  echo "Voor één tenant: ./scripts/reset-client-content.sh <clientId>"
  read -r -p "Typ 'alle tenants wissen' om te bevestigen: " confirm
  if [[ "$confirm" != "alle tenants wissen" ]]; then
    echo "Afgebroken."
    exit 1
  fi
fi

echo "==> POST $BASE/api/admin/reset-content"
curl -fsS -X POST "$BASE/api/admin/reset-content" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  -d "{\"confirmEnvironment\":\"$CE_ENV_NAME\"}"
echo
