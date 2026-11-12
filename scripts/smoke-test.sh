#!/usr/bin/env bash
# Controleer na een deploy of de omgeving echt werkt.
#
#   ./scripts/smoke-test.sh              # .env
#   ./scripts/smoke-test.sh staging
#
# Waarom de asset-hash-check: de FTP-upload controleert zelf niet of alle bestanden
# aankwamen. Een half gelukte upload geeft een witte pagina zonder foutmelding.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-}"
# Geen `set -e` hier (de checks tellen zelf fouten), dus de exitcode van ce_env_file
# expliciet afhandelen. Bij een leeg resultaat heeft die functie de reden al gemeld.
ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")" || exit 1
if [[ -z "$ENV_FILE" || ! -f "$ENV_FILE" ]]; then
  echo "Missing ${ENV_FILE:-.env.$TARGET_ENV}" >&2
  exit 1
fi

BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://ce.igisnext.com}"
BASE_URL="${BASE_URL%/}"
# Optioneel: API op host-root (/api) terwijl de SPA onder een subpad staat (bv. /brandflocks/).
API_BASE="$(ce_env_value "$ENV_FILE" CE_API_BASE_URL)"
API_BASE="${API_BASE:-$BASE_URL}"
API_BASE="${API_BASE%/}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

PASS=0
FAIL=0
SKIP=0

ok()      { printf '  \033[32mPASS\033[0m  %s\n' "$1"; PASS=$((PASS + 1)); }
bad()     { printf '  \033[31mFAIL\033[0m  %s\n' "$1"; FAIL=$((FAIL + 1)); }
skipped() { printf '  \033[33mSKIP\033[0m  %s\n' "$1"; SKIP=$((SKIP + 1)); }

echo "==> Smoke-test ${TARGET_ENV:-default} — app: $BASE_URL  api: $API_BASE"

HEALTH_ENV_EXPECT="$TARGET_ENV"
HEALTH_OVERRIDE="$(ce_env_value "$ENV_FILE" CE_HEALTH_EXPECT_ENV)"
if [[ -n "$HEALTH_OVERRIDE" ]]; then
  HEALTH_ENV_EXPECT="$HEALTH_OVERRIDE"
elif [[ "$TARGET_ENV" == "brandflocks-target" ]]; then
  HEALTH_ENV_EXPECT="$(ce_env_value "$ENV_FILE" CE_ENV)"
  HEALTH_ENV_EXPECT="${HEALTH_ENV_EXPECT:-brandflocks}"
fi

# 1. Health: DB-connectie
HEALTH="$(curl -sS --max-time 20 "$API_BASE/api/health" 2>/dev/null || echo '')"
if python3 -c "
import json,sys
d = json.loads(sys.argv[1])
sys.exit(0 if d.get('ok') and d.get('db') == 'connected' else 1)
" "$HEALTH" 2>/dev/null; then
  ENV_REPORTED="$(python3 -c "import json,sys; print(json.loads(sys.argv[1]).get('environment','?'))" "$HEALTH" 2>/dev/null)"
  ok "GET /api/health — db connected (omgeving: $ENV_REPORTED)"
  if [[ -n "$TARGET_ENV" && "$ENV_REPORTED" != "$HEALTH_ENV_EXPECT" ]]; then
    bad "CE_ENV op de server is '$ENV_REPORTED', verwacht '$HEALTH_ENV_EXPECT' — verkeerd env-bestand gedeployed?"
  fi
else
  bad "GET /api/health — geen ok/db=connected (antwoord: ${HEALTH:0:200})"
fi

# 2. Frontend asset-hash uit de build terugvinden in de live index.html
LOCAL_INDEX="$ROOT/web/index.html"
if [[ -f "$LOCAL_INDEX" ]]; then
  EXPECTED_ASSET="$(python3 - "$LOCAL_INDEX" <<'PY'
import re, sys
from pathlib import Path
html = Path(sys.argv[1]).read_text(errors='ignore')
match = re.search(r'/assets/(index-[A-Za-z0-9_-]+\.js)', html)
print(match.group(1) if match else '')
PY
)"
  if [[ -n "$EXPECTED_ASSET" ]]; then
    LIVE_INDEX="$(curl -sS --max-time 20 "$BASE_URL/index.html" 2>/dev/null || echo '')"
    if [[ "$LIVE_INDEX" == *"$EXPECTED_ASSET"* ]]; then
      ok "index.html bevat verwachte asset ($EXPECTED_ASSET)"
    else
      bad "index.html mist $EXPECTED_ASSET — upload mogelijk half gelukt"
    fi
  else
    skipped "asset-hash niet gevonden in lokale web/index.html"
  fi
else
  skipped "web/index.html ontbreekt lokaal"
fi

if [[ -z "$TOKEN" ]]; then
  skipped "admin-checks — CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")"
  echo
  echo "==> $PASS pass, $FAIL fail, $SKIP skip"
  [[ "$FAIL" -eq 0 ]] && exit 0 || exit 1
fi

# 3. Tenants opvraagbaar + eerste tenant met WordPress kiezen voor de wp-check
SLOTS="$(curl -sS --max-time 30 -H "X-CE-Reset-Token: $TOKEN" "$API_BASE/api/admin/list-slots?list_clients=1" 2>/dev/null || echo '')"
if [[ -n "$SLOTS" ]] && python3 -c "import json,sys; d=json.loads(sys.argv[1]); sys.exit(0 if d.get('ok') else 1)" "$SLOTS" 2>/dev/null; then
  ok "GET /api/admin/list-slots — geldig JSON"
else
  bad "GET /api/admin/list-slots — geen geldig JSON (antwoord: ${SLOTS:0:200})"
fi

# 4. Migratiestand
MIGRATIONS="$(curl -sS --max-time 20 -H "X-CE-Reset-Token: $TOKEN" "$API_BASE/api/admin/run-migration" 2>/dev/null || echo '')"
if PENDING="$(python3 -c "
import json,sys
d = json.loads(sys.argv[1])
print(len(d.get('pending', [])))
" "$MIGRATIONS" 2>/dev/null)"; then
  if [[ "$PENDING" == "0" ]]; then
    ok "Migraties — niets openstaand"
  else
    bad "Migraties — $PENDING openstaand (./scripts/run-migration.sh ${TARGET_ENV:-} --all)"
  fi
else
  skipped "migratiestand niet op te halen"
fi

# 5. WordPress-diagnose: alleen voor een tenant die WordPress gebruikt.
#    Een tenant zonder WP levert SKIP, geen FAIL — anders leer je de test negeren.
WP_CLIENT="$(python3 - "$SLOTS" <<'PY'
import json, sys
try:
    data = json.loads(sys.argv[1])
except Exception:
    print('')
    sys.exit()

candidates = data if isinstance(data, list) else data.get('clients') or data.get('tenants') or []
if isinstance(candidates, dict):
    candidates = list(candidates.values())

for entry in candidates if isinstance(candidates, list) else []:
    if not isinstance(entry, dict):
        continue
    site = entry.get('wordpressSiteUrl') or entry.get('wordpress_site_url') or ''
    if str(site).strip():
        print(entry.get('clientId') or entry.get('client_id') or entry.get('id') or '')
        break
else:
    print('')
PY
)"

if [[ -n "$WP_CLIENT" ]]; then
  WP="$(curl -sS --max-time 30 -H "X-CE-Reset-Token: $TOKEN" \
    "$API_BASE/api/admin/wp-diagnose?client_id=$WP_CLIENT" 2>/dev/null || echo '')"
  WP_CHECK="$(python3 -c "
import json,sys
d = json.loads(sys.argv[1])
if d.get('authenticatedAs'):
    print('ok')
elif not d.get('tenantWordPressConfigured'):
    print('skip')
else:
    print('fail')
" "$WP" 2>/dev/null || echo 'fail')"
  case "$WP_CHECK" in
    ok)
      ok "GET /api/admin/wp-diagnose — authenticatie gelukt"
      ;;
    skip)
      skipped "wp-diagnose — tenant heeft site-URL maar WP-credentials nog niet compleet"
      ;;
    *)
      bad "GET /api/admin/wp-diagnose — geen authenticatedAs voor $WP_CLIENT"
      ;;
  esac
else
  skipped "wp-diagnose — geen tenant met wordpress_site_url gevonden"
fi

# 6. Recente fouten in het event log
EVENTS="$(curl -sS --max-time 20 -H "X-CE-Reset-Token: $TOKEN" \
  "$API_BASE/api/admin/events?severity=error&days=1&limit=20" 2>/dev/null || echo '')"
if ERRORS="$(python3 -c "
import json,sys
d = json.loads(sys.argv[1])
print(d.get('count', 0))
" "$EVENTS" 2>/dev/null)"; then
  if [[ "$ERRORS" == "0" ]]; then
    ok "event_log — geen fouten in de laatste 24 uur"
  else
    printf '  \033[33mLET OP\033[0m  event_log — %s fout(en) in de laatste 24 uur\n' "$ERRORS"
  fi
else
  skipped "event_log niet op te halen (tabel bestaat mogelijk nog niet)"
fi

echo
echo "==> $PASS pass, $FAIL fail, $SKIP skip"
[[ "$FAIL" -eq 0 ]] && exit 0 || exit 1
