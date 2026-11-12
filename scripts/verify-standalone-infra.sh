#!/usr/bin/env bash
# Controleer of een Bf-env niet per ongeluk naar CE/staging.igisnext wijst.
#
#   ./scripts/verify-standalone-infra.sh brandflocks
#   ./scripts/verify-standalone-infra.sh brandflocks-target
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-brandflocks}"
if [[ ! "$TARGET_ENV" =~ ^(brandflocks|brandflocks-target)$ ]]; then
  echo "Gebruik: $0 brandflocks|brandflocks-target" >&2
  exit 1
fi

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; RESET=$'\033[0m'
FAIL=0
WARN=0

fail() { printf '  %sFOUT%s  %s\n' "$RED" "$RESET" "$1"; FAIL=$((FAIL + 1)); }
warn() { printf '  %sLET OP%s %s\n' "$YELLOW" "$RESET" "$1"; WARN=$((WARN + 1)); }
pass() { printf '  %sOK%s    %s\n' "$GREEN" "$RESET" "$1"; }

value() { ce_env_value "$ENV_FILE" "$1"; }

BASE="$(value CE_PUBLIC_BASE_URL)"
ORIGINS="$(value CE_ALLOWED_ORIGINS)"

echo "==> Standalone check: $(basename "$ENV_FILE")"
echo ""

if [[ "$BASE" != *"brandflocks.nl"* ]]; then
  fail "CE_PUBLIC_BASE_URL moet op brandflocks.nl draaien (nu: ${BASE:-leeg})"
else
  pass "CE_PUBLIC_BASE_URL op brandflocks-domein"
fi

for host in igisnext.com staging.igisnext.com ce.igisnext.com; do
  if [[ "$BASE" == *"$host"* ]] || [[ "$ORIGINS" == *"$host"* ]]; then
    fail "CE-host verwijst nog naar $host"
  fi
done

FTP_HOST="$(value FTP_HOST)"
if [[ "$FTP_HOST" == *"igisnext"* ]]; then
  fail "FTP_HOST wijst naar CE (${FTP_HOST})"
else
  pass "FTP_HOST niet igisnext"
fi

DB_NAME="$(value DB_NAME)"
if [[ "$DB_NAME" == *"igisnext"* ]]; then
  fail "DB_NAME lijkt CE-database (${DB_NAME})"
else
  pass "DB_NAME niet igisnext"
fi

WH_SUFFIX="$(value N8N_WEBHOOK_SUFFIX)"
if [[ -n "$WH_SUFFIX" && "$WH_SUFFIX" != *"brandflocks"* ]]; then
  warn "N8N_WEBHOOK_SUFFIX=${WH_SUFFIX} — verwacht typisch -brandflocks"
else
  pass "N8N-webhook suffix OK"
fi

MAIL_FROM="$(value CE_MAIL_FROM)"
if [[ "$MAIL_FROM" == *"igisnext"* ]] || [[ "$MAIL_FROM" == *"@noreply@staging.igisnext"* ]]; then
  fail "CE_MAIL_FROM nog CE/staging (${MAIL_FROM})"
else
  pass "CE_MAIL_FROM Brandflocks"
fi

if grep -qE '^(CE_PUBLIC_BASE_URL|FTP_HOST|DB_NAME)=' "$ENV_FILE" 2>/dev/null; then
  if grep -E 'igisnext\.com' "$ENV_FILE" | grep -vE '^\s*#' | grep -qvE 'BUILD-NOTES|naslag|niet meer'; then
    # Alleen actieve regels (geen comment) met igisnext
    if grep -E '^[^#].*igisnext\.com' "$ENV_FILE" >/dev/null 2>&1; then
      warn "Er staan nog niet-gecommentarieerde igisnext.com-regels in $(basename "$ENV_FILE")"
    fi
  fi
fi

# Dubbele parent env
PARENT="$ROOT/../.env.brandflocks"
if [[ -f "$PARENT" && ! -L "$PARENT" ]] && [[ -f "$ROOT/.env.brandflocks" ]]; then
  if ! cmp -s "$PARENT" "$ROOT/.env.brandflocks" 2>/dev/null; then
    warn "Parent .env.brandflocks wijkt af van brandflocks/.env.brandflocks — gebruik één bestand of symlink"
  fi
fi

echo ""
if [[ "$FAIL" -gt 0 ]]; then
  echo "Mislukt: $FAIL fout(en), $WARN waarschuwing(en)." >&2
  exit 1
fi
echo "Klaar — $FAIL fout(en), $WARN waarschuwing(en)."
exit 0
