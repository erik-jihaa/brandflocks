#!/usr/bin/env bash
# Werk een bestaande tenant bij via POST /api/admin/update-client
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${3:-}"
ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://app.brandflocks.nl}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"
CLIENT_ID="${1:-}"
JSON="${2:-}"

if [[ -z "$TOKEN" || -z "$CLIENT_ID" || -z "$JSON" ]]; then
  echo "Usage: $0 <clientId> '<json body>'" >&2
  exit 1
fi

curl -sS -X POST "$BASE_URL/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "$JSON" | python3 -m json.tool
