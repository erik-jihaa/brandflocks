#!/usr/bin/env bash
# Kopieer sources + strategy (en optioneel wachtwoord) van bron- naar doel-tenant.
# Brandflocks: admin/copy-client-sources is verwijderd (ronde B). Gebruik CE-Cursor of
# update-client copyStrategyFromClientId + handmatige bronnen tot er een vervanger is.
# Gebruik:
#   ./scripts/copy-sources-strategy.sh "iGiSNext Sandbox 2"
#   ./scripts/copy-sources-strategy.sh <targetClientId> [sourceCompanyOrId]
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
SOURCE_DEFAULT="${CE_COPY_SOURCE_CLIENT:-iGiSNext}"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in .env" >&2
  exit 1
fi

resolve_client_id() {
  local query="$1"
  QUERY="$query" python3 - <<'PY'
import json, os, sys, urllib.parse, urllib.request
query = os.environ["QUERY"]
base = os.environ.get("CE_PUBLIC_BASE_URL", "https://ce.igisnext.com")
token = os.environ["CE_RESET_TOKEN"]
# Exact name match first via admin create-client dup logic isn't available — use account search needs login.
# Resolve via wp-diagnose company slug or list from SQL-like filter: try update-client path with known IDs.
# Fallback: query clients table through a small admin helper — use copy endpoint error messages.
# Use admin/wp-diagnose only returns one client by company fragment.
req = urllib.request.Request(
    f"{base}/api/admin/wp-diagnose?company={urllib.parse.quote(query)}",
    headers={"X-CE-Reset-Token": token},
)
with urllib.request.urlopen(req) as resp:
    data = json.load(resp)
client_id = (data.get("clientId") or "").strip()
if not client_id:
    sys.exit(f"Geen client gevonden voor: {query}")
print(client_id)
PY
}

TARGET_RAW="${1:-}"
SOURCE_RAW="${2:-$SOURCE_DEFAULT}"

if [[ -z "$TARGET_RAW" ]]; then
  echo "Usage: $0 <targetCompany|clientId> [sourceCompany|clientId]" >&2
  exit 1
fi

if [[ "$TARGET_RAW" == *-*-*-*-* ]]; then
  TARGET_ID="$TARGET_RAW"
else
  TARGET_ID="$(resolve_client_id "$TARGET_RAW")"
fi

if [[ "$SOURCE_RAW" == *-*-*-*-* ]]; then
  SOURCE_ID="$SOURCE_RAW"
else
  SOURCE_ID="$(resolve_client_id "$SOURCE_RAW")"
fi

echo "==> Strategy: $SOURCE_ID -> $TARGET_ID"
curl -sS -X POST "$BASE_URL/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "{\"clientId\":\"$TARGET_ID\",\"copyStrategyFromClientId\":\"$SOURCE_ID\"}" | python3 -m json.tool

echo "==> Login password (indien bron heeft hash): $SOURCE_ID -> $TARGET_ID"
curl -sS -X POST "$BASE_URL/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "{\"clientId\":\"$TARGET_ID\",\"copyPasswordFromClientId\":\"$SOURCE_ID\"}" | python3 -m json.tool 2>/dev/null || echo "(wachtwoord niet gekopieerd — bron heeft geen hash)"

echo "Done."
