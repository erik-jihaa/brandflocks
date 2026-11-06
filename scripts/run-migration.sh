#!/usr/bin/env bash
# Draai een SQL-migratie via de API (DB_HOST=localhost is niet van buitenaf bereikbaar).
#
#   ./scripts/run-migration.sh                            # status: toegepast + openstaand
#   ./scripts/run-migration.sh staging add_event_log.sql
#   ./scripts/run-migration.sh production --all
#
# Regel uit het architectuurbrief: additieve migraties vóór de code-deploy,
# destructieve migraties pas ná de deploy.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV=""
MIGRATION=""

for arg in "$@"; do
  case "$arg" in
    local|staging|brandflocks|production) TARGET_ENV="$arg" ;;
    --all) MIGRATION="--all" ;;
    *.sql) MIGRATION="$arg" ;;
    *) echo "Onbekend argument: $arg" >&2; exit 1 ;;
  esac
done

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi

BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://ce.igisnext.com}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

echo "==> Omgeving: ${TARGET_ENV:-default}  ($BASE_URL)"

if [[ -z "$MIGRATION" ]]; then
  curl -sS -H "X-CE-Reset-Token: $TOKEN" "$BASE_URL/api/admin/run-migration" | python3 -m json.tool
  exit 0
fi

if [[ "$MIGRATION" == "--all" ]]; then
  BODY='{"all":true}'
  echo "==> Alle openstaande migraties toepassen"
else
  if [[ ! -f "$ROOT/scripts/migrations/$MIGRATION" ]]; then
    echo "Migratie niet gevonden: scripts/migrations/$MIGRATION" >&2
    exit 1
  fi
  BODY="$(python3 -c 'import json,sys; print(json.dumps({"filename": sys.argv[1]}))' "$MIGRATION")"
  echo "==> Migratie: $MIGRATION"
fi

RESPONSE="$(curl -sS -X POST \
  -H "X-CE-Reset-Token: $TOKEN" \
  -H 'Content-Type: application/json' \
  -d "$BODY" \
  "$BASE_URL/api/admin/run-migration")"

echo "$RESPONSE" | python3 -m json.tool

python3 - "$RESPONSE" <<'PY'
import json, sys
try:
    data = json.loads(sys.argv[1])
except Exception:
    sys.exit(1)
sys.exit(0 if data.get('ok') else 1)
PY
