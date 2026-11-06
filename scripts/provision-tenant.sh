#!/usr/bin/env bash
# Tenant inrichten: blank slate + sources kopiëren + optioneel wachtwoord kopieren
# Gebruik:
#   ./scripts/provision-tenant.sh <targetClientId> <sourceClientId>
#   ./scripts/provision-tenant.sh eva@igisnext.com erik@igisnext.com
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

resolve_client_id() {
  local query="$1"
  QUERY="$query" python3 - <<'PY'
import json, os, sys, urllib.parse, urllib.request
query = os.environ["QUERY"]
base = os.environ.get("CE_PUBLIC_BASE_URL", "https://ce.igisnext.com")
token = os.environ["CE_RESET_TOKEN"]
req = urllib.request.Request(
    f"{base}/api/clients?q={urllib.parse.quote(query)}",
    headers={"X-CE-Reset-Token": token},
)
with urllib.request.urlopen(req) as resp:
    data = json.load(resp)
clients = data.get("clients") or []
if not clients:
    sys.exit(f"Geen client gevonden voor: {query}")
if len(clients) > 1:
    print("Meerdere clients — kies clientId expliciet:", file=sys.stderr)
    for c in clients:
        print(f"  {c.get('clientId')}  {c.get('company')}  {c.get('contactEmail')}", file=sys.stderr)
    sys.exit(1)
print(clients[0]["clientId"])
PY
}

TARGET_RAW="${1:-}"
SOURCE_RAW="${2:-erik@igisnext.com}"

if [[ -z "$TARGET_RAW" ]]; then
  echo "Usage: $0 <targetClientId|email> [sourceClientId|email]" >&2
  exit 1
fi

if [[ "$TARGET_RAW" == *@* ]]; then
  TARGET_ID="$(resolve_client_id "$TARGET_RAW")"
else
  TARGET_ID="$TARGET_RAW"
fi

if [[ "$SOURCE_RAW" == *@* ]]; then
  SOURCE_ID="$(resolve_client_id "$SOURCE_RAW")"
else
  SOURCE_ID="$SOURCE_RAW"
fi

echo "==> Blank tenant: $TARGET_ID"
curl -sS -X POST "$BASE_URL/api/admin/reset-client-content" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "{\"clientId\":\"$TARGET_ID\",\"blankStrategy\":true,\"confirmEnvironment\":\"${CE_ENV:-production}\"}" \
  | python3 -m json.tool

echo "==> Copy login password from source tenant (sources: kopieer handmatig of via CE-Cursor admin/copy-client-sources)"
curl -sS -X POST "$BASE_URL/api/admin/update-client" \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $TOKEN" \
  -d "{\"clientId\":\"$TARGET_ID\",\"copyPasswordFromClientId\":\"$SOURCE_ID\"}" | python3 -m json.tool

echo "Done — tenant $TARGET_ID is schoon met sources van $SOURCE_ID"
