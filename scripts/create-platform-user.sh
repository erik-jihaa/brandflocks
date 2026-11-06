#!/usr/bin/env bash
# Maak een platform-beheerder aan via POST /api/admin/create-platform-user
#
#   ./scripts/create-platform-user.sh "erik@igisnext.com" "Erik" "wachtwoord" [admin|support] [production]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

EMAIL="${1:-}"
NAME="${2:-}"
PASSWORD="${3:-}"
ROLE="${4:-admin}"
TARGET_ENV="${5:-production}"

if [[ -z "$EMAIL" || -z "$PASSWORD" ]]; then
  echo "Usage: $0 <email> [name] <password> [admin|support] [staging|production]" >&2
  exit 1
fi

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://ce.igisnext.com}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

if [[ -z "$NAME" ]]; then
  NAME="${EMAIL%%@*}"
fi

JSON="$(python3 - "$EMAIL" "$NAME" "$PASSWORD" "$ROLE" <<'PY'
import json, sys
email, name, password, role = sys.argv[1:5]
print(json.dumps({"email": email, "name": name, "password": password, "role": role}))
PY
)"

echo "==> Platform-user: $EMAIL ($ROLE) op $TARGET_ENV"
curl -sS -X POST "$BASE_URL/api/admin/create-platform-user" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "$JSON" | python3 -m json.tool
