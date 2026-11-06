#!/usr/bin/env bash
# Support-chat vragen retentie — POST /api/admin/prune-support-service (12 maanden).
#
#   ./scripts/prune-support-service.sh [staging|production]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-production}"
ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://ce.igisnext.com}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi
if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

echo "==> Support-chat retentie ($TARGET_ENV → $BASE_URL)"
curl -fsS -X POST \
  -H "X-CE-Reset-Token: $TOKEN" \
  "$BASE_URL/api/admin/prune-support-service" \
  | python3 -m json.tool
