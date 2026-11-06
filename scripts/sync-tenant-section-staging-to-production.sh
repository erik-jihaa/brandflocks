#!/usr/bin/env bash
# Sync geselecteerde tenant-secties van staging naar productie (bronnen blijven ongemoeid).
#
#   ./scripts/sync-tenant-section-staging-to-production.sh
#   ./scripts/sync-tenant-section-staging-to-production.sh agenda findability
#
# Brandflocks: admin/import-tenant-section verwijderd (ronde B) — gebruik CE-Cursor voor sectie-sync.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if [[ ! -f "$ROOT/web/api/admin-import-tenant-section.php" ]]; then
  echo "admin/import-tenant-section ontbreekt (Brandflocks ronde B). Gebruik CE-Cursor." >&2
  exit 1
fi
source "$ROOT/scripts/ce-env.sh"

SOURCE_ID="${CE_STAGING_IGISNEXT_CLIENT_ID:-016384af-b26e-4415-aa9c-abc46b804798}"
TARGET_ID="${CE_PRODUCTION_IGISNEXT_CLIENT_ID:-7f2c4927-1395-a2f2-55cf-03d92383f546}"
SECTIONS=("$@")
if [[ ${#SECTIONS[@]} -eq 0 ]]; then
  SECTIONS=(agenda findability)
fi

STAGING_ENV="$(ce_env_file "$ROOT" staging)"
PROD_ENV="$(ce_env_file "$ROOT" production)"
STAGING_URL="$(ce_env_value "$STAGING_ENV" CE_PUBLIC_BASE_URL)"
PROD_URL="$(ce_env_value "$PROD_ENV" CE_PUBLIC_BASE_URL)"
STAGING_TOKEN="$(ce_env_value "$STAGING_ENV" CE_RESET_TOKEN)"
PROD_TOKEN="$(ce_env_value "$PROD_ENV" CE_RESET_TOKEN)"

if [[ -z "$STAGING_URL" || -z "$STAGING_TOKEN" || -z "$PROD_URL" || -z "$PROD_TOKEN" ]]; then
  echo "CE_PUBLIC_BASE_URL en CE_RESET_TOKEN zijn verplicht in .env.staging en .env.production" >&2
  exit 1
fi

TMP="$(mktemp "${TMPDIR:-/tmp}/ce-tenant-section-XXXXXX.json")"
PAYLOAD="$(mktemp "${TMPDIR:-/tmp}/ce-tenant-section-payload-XXXXXX.json")"
trap 'rm -f "$TMP" "$PAYLOAD"' EXIT

echo "==> Export staging tenant $SOURCE_ID"
curl -fsS \
  -H "X-CE-Reset-Token: $STAGING_TOKEN" \
  "$STAGING_URL/api/admin/export-tenant?clientId=$SOURCE_ID" \
  -o "$TMP"

SECTIONS_JSON="$(printf '%s\n' "${SECTIONS[@]}" | python3 -c 'import json,sys; print(json.dumps([l.strip() for l in sys.stdin if l.strip()]))')"

python3 - "$SOURCE_ID" "$TARGET_ID" "$TMP" "$PAYLOAD" "$SECTIONS_JSON" <<'PY'
import json, sys
source, target, export_path, payload_path, sections_json = sys.argv[1:6]
sections = json.loads(sections_json)
with open(export_path) as f:
    bundle = json.load(f)

def remap(obj):
    if isinstance(obj, dict):
        return {k: remap(v) for k, v in obj.items()}
    if isinstance(obj, list):
        return [remap(x) for x in obj]
    if isinstance(obj, str) and obj == source:
        return target
    if isinstance(obj, str) and source in obj:
        return obj.replace(source, target)
    return obj

bundle = remap(bundle)
if isinstance(bundle.get('client'), dict):
    bundle['client']['id'] = target
bundle['clientId'] = target
with open(payload_path, 'w') as f:
    json.dump({"clientId": target, "sections": sections, "bundle": bundle}, f)
PY

echo "==> Import sections ${SECTIONS[*]} on production ($TARGET_ID)"
HTTP_CODE="$(curl -sS -o /tmp/ce-section-import-response.json -w '%{http_code}' -X POST \
  -H "Content-Type: application/json" \
  -H "X-CE-Reset-Token: $PROD_TOKEN" \
  --data-binary @"$PAYLOAD" \
  "$PROD_URL/api/admin/import-tenant-section")"

if [[ "$HTTP_CODE" != "200" ]]; then
  echo "Import mislukt (HTTP $HTTP_CODE):" >&2
  cat /tmp/ce-section-import-response.json >&2
  exit 1
fi

python3 -m json.tool /tmp/ce-section-import-response.json
echo "==> Klaar — bronnen op productie niet aangeraakt."
