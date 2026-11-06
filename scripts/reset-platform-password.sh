#!/usr/bin/env bash
# Stel platform-login-wachtwoord in (via CE_RESET_TOKEN in .env)
#
#   bash scripts/reset-platform-password.sh production 'erik@igisnext.com' 'nieuw-wachtwoord'
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-production}"
EMAIL="${2:-}"
PASS="${3:-}"

if [[ "$TARGET_ENV" != "staging" && "$TARGET_ENV" != "production" ]]; then
  EMAIL="${1:-}"
  PASS="${2:-}"
  TARGET_ENV="production"
fi

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")" || exit 1
BASE="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE="${BASE:-https://ce.igisnext.com}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

if [[ -z "$EMAIL" ]]; then
  read -r -p "Platform e-mail: " EMAIL
fi

if [[ -z "$PASS" ]]; then
  echo "Nieuw wachtwoord (min. 8 tekens, typen is onzichtbaar):"
  read -r -s -p "> " PASS
  echo ""
fi

if [[ ${#PASS} -lt 8 ]]; then
  echo "Wachtwoord moet minimaal 8 tekens zijn." >&2
  exit 1
fi

JSON="$(python3 - "$EMAIL" "$PASS" <<'PY'
import json, sys
print(json.dumps({"email": sys.argv[1], "password": sys.argv[2]}))
PY
)"

echo "==> Platform-wachtwoord reset: $EMAIL op $TARGET_ENV ($BASE)"
curl -sS -X POST "$BASE/api/admin/set-platform-password" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "$JSON" | python3 -m json.tool
