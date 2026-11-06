#!/usr/bin/env bash
# Controleer of een niet-productie-omgeving echt losstaat van productie.
#
#   ./scripts/check-env-isolation.sh staging
#   ./scripts/check-env-isolation.sh brandflocks   # ook versus .env.staging
#   ./scripts/check-env-isolation.sh local
#
# Waarom dit een script is en geen instructie in de documentatie: de gevaarlijke fout is
# een configuratie die er goed uitziet. Een .env.staging met CE_ENV=staging maar de
# productie-DB erin geeft een app met een staging-banner die live data wegschrijft, en
# de destructieve guard staat dan óók uit omdat ce_is_production() false is.
#
# Botsing = beide waarden gevuld én gelijk. Een leeg staging-veld botst nooit: de app
# slaat die integratie dan over, wat veilig is.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-staging}"
if [[ "$TARGET_ENV" == "production" ]]; then
  echo "Productie vergelijken met zichzelf heeft geen zin." >&2
  exit 1
fi
if [[ ! "$TARGET_ENV" =~ ^(local|staging|brandflocks)$ ]]; then
  echo "Onbekende omgeving: $TARGET_ENV (kies local, staging of brandflocks)" >&2
  exit 1
fi

ENV_FILE="$ROOT/.env.$TARGET_ENV"
PROD_FILE="$ROOT/.env"
STAGING_FILE="$ROOT/.env.staging"
COMPARE_TO_FILE="$PROD_FILE"
COMPARE_TO_LABEL="productie"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Geen .env.$TARGET_ENV gevonden." >&2
  echo "  cp .env.staging.example .env.$TARGET_ENV" >&2
  exit 1
fi

if [[ ! -f "$PROD_FILE" ]]; then
  echo "Geen .env gevonden — zonder productie-referentie valt er niets te vergelijken." >&2
  exit 1
fi

RED=$'\033[31m'; GREEN=$'\033[32m'; YELLOW=$'\033[33m'; RESET=$'\033[0m'
FAILURES=0
WARNINGS=0

pass() { printf '  %sOK%s    %s\n' "$GREEN" "$RESET" "$1"; }
fail() { printf '  %sFOUT%s  %s\n' "$RED" "$RESET" "$1"; FAILURES=$((FAILURES + 1)); }
warn() { printf '  %sLET OP%s %s\n' "$YELLOW" "$RESET" "$1"; WARNINGS=$((WARNINGS + 1)); }

value_of() { ce_env_value "$1" "$2"; }

# Tokens en wachtwoorden nooit voluit tonen: deze uitvoer belandt in terminallogs en in
# de deploy-uitvoer.
is_secret() {
  case "$1" in
    *TOKEN*|*KEY*|*PASSWORD*|*SECRET*|*PASS) return 0 ;;
    *) return 1 ;;
  esac
}

show_value() {
  if is_secret "$1"; then
    printf '(waarde verborgen)'
  else
    printf '%s' "$2"
  fi
}

# Weigert wanneer staging en productie dezelfde waarde hebben.
must_differ() {
  local key="$1" reason="$2"
  local mine theirs
  mine="$(value_of "$ENV_FILE" "$key")"
  theirs="$(value_of "$COMPARE_TO_FILE" "$key")"

  if [[ -z "$mine" ]]; then
    pass "$key is leeg in $TARGET_ENV — geen botsing ($COMPARE_TO_LABEL)"
    return
  fi

  if [[ -n "$theirs" && "$mine" == "$theirs" ]]; then
    fail "$key is gelijk aan $COMPARE_TO_LABEL ($(show_value "$key" "$mine")) — $reason"
    return
  fi

  pass "$key wijkt af van $COMPARE_TO_LABEL"
}

# Zelfde als must_differ, maar meldt alleen; gebruikt waar gelijk zijn soms verdedigbaar is.
should_differ() {
  local key="$1" reason="$2"
  local mine theirs
  mine="$(value_of "$ENV_FILE" "$key")"
  theirs="$(value_of "$COMPARE_TO_FILE" "$key")"

  if [[ -z "$mine" ]]; then
    pass "$key is leeg in $TARGET_ENV — geen botsing ($COMPARE_TO_LABEL)"
    return
  fi

  if [[ -n "$theirs" && "$mine" == "$theirs" ]]; then
    warn "$key is gelijk aan $COMPARE_TO_LABEL — $reason"
    return
  fi

  pass "$key wijkt af van $COMPARE_TO_LABEL"
}

require_value() {
  local key="$1" expected="${2:-}"
  local mine
  mine="$(value_of "$ENV_FILE" "$key")"

  if [[ -z "$mine" ]]; then
    fail "$key is niet gezet in .env.$TARGET_ENV"
    return
  fi

  if [[ -n "$expected" && "$mine" != "$expected" ]]; then
    fail "$key is '$mine', verwacht '$expected'"
    return
  fi

  pass "$key = $(show_value "$key" "$mine")"
}

echo "==> Isolatiecheck $TARGET_ENV — .env.$TARGET_ENV"
echo

echo "Omgevingsvlag"
require_value CE_ENV "$TARGET_ENV"

COMPARE_SPECS=("productie:$PROD_FILE")
if [[ "$TARGET_ENV" == "brandflocks" && -f "$STAGING_FILE" ]]; then
  COMPARE_SPECS+=("staging:$STAGING_FILE")
fi

run_compare_pass() {
  local spec="$1"
  COMPARE_TO_LABEL="${spec%%:*}"
  COMPARE_TO_FILE="${spec#*:}"

  echo
  echo "── versus $COMPARE_TO_LABEL ($(basename "$COMPARE_TO_FILE")) ──"

  echo "Database"
  must_differ DB_NAME "dezelfde database zou live data raken"
  must_differ DB_USER "dezelfde gebruiker houdt rechten op de andere database"

  echo
  echo "Deploy-doel"
  local mine_ftp other_ftp
  mine_ftp="$(value_of "$ENV_FILE" FTP_HOST)|$(value_of "$ENV_FILE" FTP_USER)|$(value_of "$ENV_FILE" FTP_REMOTE_PATH)"
  other_ftp="$(value_of "$COMPARE_TO_FILE" FTP_HOST)|$(value_of "$COMPARE_TO_FILE" FTP_USER)|$(value_of "$COMPARE_TO_FILE" FTP_REMOTE_PATH)"
  if [[ "$mine_ftp" == "|||" || "$mine_ftp" == "||" ]]; then
    pass "Geen FTP-doel in $TARGET_ENV — deploy niet mogelijk, dus geen risico"
  elif [[ "$mine_ftp" == "$other_ftp" ]]; then
    fail "FTP host+gebruiker+pad zijn identiek aan $COMPARE_TO_LABEL — deploy overschrijft die site"
  else
    pass "FTP-doel wijkt af van $COMPARE_TO_LABEL"
  fi
  must_differ FTP_USER "een aparte FTP-gebruiker is het punt van de scheiding"

  echo
  echo "URL's en tokens"
  must_differ CE_PUBLIC_BASE_URL "scripts zouden de verkeerde URL aanspreken"
  must_differ CE_RESET_TOKEN "één gelekt token opent dan beide omgevingen"
  should_differ CE_CREDENTIAL_KEY "met dezelfde key kan een dump credentials van de andere omgeving ontsleutelen"

  echo
  echo "Uitgaande koppelingen"
  must_differ WORDPRESS_SITE_URL "een test publiceert dan naar de verkeerde site"
  for key in $(grep -oE '^N8N_CE[A-Z_0-9]*_WEBHOOK_URL' "$ENV_FILE" | sort -u); do
    must_differ "$key" "een run start dan de pipeline van $COMPARE_TO_LABEL"
  done

  echo
  echo "n8n suffix"
  local mine_suffix ref_suffix
  mine_suffix="$(value_of "$ENV_FILE" N8N_WEBHOOK_SUFFIX)"
  ref_suffix="$(value_of "$COMPARE_TO_FILE" N8N_WEBHOOK_SUFFIX)"
  if [[ -n "$ref_suffix" && -n "$mine_suffix" && "$mine_suffix" == "$ref_suffix" ]]; then
    fail "N8N_WEBHOOK_SUFFIX is gelijk aan $COMPARE_TO_LABEL ($mine_suffix)"
  elif [[ -n "$mine_suffix" ]]; then
    pass "N8N_WEBHOOK_SUFFIX = $mine_suffix (wijkt af van $COMPARE_TO_LABEL)"
  else
    pass "N8N_WEBHOOK_SUFFIX leeg in $TARGET_ENV — geen botsing met suffix van $COMPARE_TO_LABEL"
  fi
}

for spec in "${COMPARE_SPECS[@]}"; do
  run_compare_pass "$spec"
done

echo
if [[ "$FAILURES" -gt 0 ]]; then
  printf '%sAfgekeurd: %d probleem(en), %d waarschuwing(en).%s\n' "$RED" "$FAILURES" "$WARNINGS" "$RESET"
  echo "Los deze op voordat je naar $TARGET_ENV deployt — zolang ze bestaan is $TARGET_ENV"
  echo "geen tweede omgeving maar een tweede weg naar productie."
  exit 1
fi

printf '%sGoedgekeurd%s — %d waarschuwing(en).\n' "$GREEN" "$RESET" "$WARNINGS"
