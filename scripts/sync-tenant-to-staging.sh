#!/usr/bin/env bash
# Export tenant bundle from production and import on staging.
#
#   ./scripts/sync-tenant-to-staging.sh
#   ./scripts/sync-tenant-to-staging.sh 016384af-b26e-4415-aa9c-abc46b804798
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

CLIENT_ID="${1:-016384af-b26e-4415-aa9c-abc46b804798}"
PROD_ENV="$ROOT/.env.production"
STAGING_ENV="$ROOT/.env.staging"

PROD_URL="$(ce_env_value "$PROD_ENV" CE_PUBLIC_BASE_URL)"
PROD_TOKEN="$(ce_env_value "$PROD_ENV" CE_RESET_TOKEN)"
STAGING_URL="$(ce_env_value "$STAGING_ENV" CE_PUBLIC_BASE_URL)"
STAGING_TOKEN="$(ce_env_value "$STAGING_ENV" CE_RESET_TOKEN)"

if [[ -z "$PROD_URL" || -z "$PROD_TOKEN" || -z "$STAGING_URL" || -z "$STAGING_TOKEN" ]]; then
  echo "CE_PUBLIC_BASE_URL en CE_RESET_TOKEN zijn verplicht in .env.production en .env.staging" >&2
  exit 1
fi

TMP="$(mktemp "${TMPDIR:-/tmp}/ce-tenant-bundle-XXXXXX.json")"
trap 'rm -f "$TMP"' EXIT

echo "==> Export production tenant $CLIENT_ID"
curl -fsS \
  -H "X-CE-Reset-Token: $PROD_TOKEN" \
  "$PROD_URL/api/admin/export-tenant?clientId=$CLIENT_ID" \
  -o "$TMP"

echo "==> Import on staging"
HTTP_CODE="$(curl -sS -o /tmp/ce-import-response.json -w '%{http_code}' -X POST \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $STAGING_TOKEN" \
  --data-binary @"$TMP" \
  "$STAGING_URL/api/admin/import-tenant")"

if [[ "$HTTP_CODE" != "200" ]]; then
  echo "Import mislukt (HTTP $HTTP_CODE):" >&2
  cat /tmp/ce-import-response.json >&2
  exit 1
fi

python3 -m json.tool /tmp/ce-import-response.json

echo "==> Klaar — login op staging met dezelfde tenant-credentials als productie."
