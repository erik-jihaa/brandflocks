#!/usr/bin/env bash
# Werk een bestaande tenant bij via POST /api/admin/update-client
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
JSON="${2:-}"

if [[ -z "$TOKEN" || -z "$CLIENT_ID" || -z "$JSON" ]]; then
  echo "Usage: $0 <clientId> '<json body>'" >&2
  exit 1
fi

curl -sS -X POST "$BASE_URL/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "$JSON" | python3 -m json.tool
