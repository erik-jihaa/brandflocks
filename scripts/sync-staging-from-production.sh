#!/usr/bin/env bash
# Zet de staging-database op: productie-SCHEMA + een geanonimiseerde demo-tenant.
#
#   ./scripts/sync-staging-from-production.sh
#
# Bewust GEEN kopie van productiedata. Zodra klant-tokens, e-mailadressen en artikelen
# op een tweede plek staan, is het datalek-oppervlak verdubbeld. Het schema komt uit
# productie (docs/schema.sql), de inhoud is verzonnen.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

STAGING_ENV="$ROOT/.env.staging"
if [[ ! -f "$STAGING_ENV" ]]; then
  echo "Missing $STAGING_ENV" >&2
  echo "  cp .env.staging.example .env.staging   # en vul de staging-waarden in" >&2
  exit 1
fi

# Dit script maakt een tenant aan en past migraties toe; op de verkeerde omgeving is dat
# schadelijk. De volledige vergelijking met productie staat in één script, zodat er niet
# twee lijsten met "wat mag botsen" naast elkaar bestaan.
if ! "$ROOT/scripts/check-env-isolation.sh" staging; then
  echo >&2
  echo "Afgebroken: .env.staging is niet losgekoppeld van productie." >&2
  exit 1
fi
echo

STAGING_URL="$(ce_env_value "$STAGING_ENV" CE_PUBLIC_BASE_URL)"
STAGING_TOKEN="$(ce_env_value "$STAGING_ENV" CE_RESET_TOKEN)"
PROD_TOKEN="$(ce_env_value "$ROOT/.env" CE_RESET_TOKEN)"
STAGING_DB="$(ce_env_value "$STAGING_ENV" DB_NAME)"

if [[ -z "$STAGING_URL" || -z "$STAGING_TOKEN" ]]; then
  echo "CE_PUBLIC_BASE_URL en CE_RESET_TOKEN zijn verplicht in .env.staging" >&2
  exit 1
fi

echo "==> Staging: $STAGING_URL (db: $STAGING_DB)"

# 1. Schema verversen uit productie (optioneel — docs/schema.sql volstaat ook).
if [[ -n "$PROD_TOKEN" ]]; then
  echo "==> Productie-schema verversen naar docs/schema.sql"
  "$ROOT/scripts/export-production-schema.sh" >/dev/null
fi

SCHEMA="$ROOT/docs/schema.sql"
if [[ ! -f "$SCHEMA" ]]; then
  echo "Missing $SCHEMA" >&2
  exit 1
fi

TABLES="$(grep -c '^CREATE TABLE' "$SCHEMA" || true)"
echo "==> Schema: $TABLES tabellen uit docs/schema.sql"
echo
echo "Het schema moet één keer handmatig op de staging-database worden toegepast:"
echo "  phpMyAdmin op de staging-hosting → database $STAGING_DB → Import → docs/schema.sql"
echo
read -r -p "Is het schema toegepast op $STAGING_DB? (ja/nee): " schema_done
if [[ "$schema_done" != "ja" ]]; then
  echo "Pas eerst het schema toe, draai daarna dit script opnieuw."
  exit 0
fi

# 2. Migratiestand registreren, zodat run-migration.sh niet alles opnieuw probeert.
echo "==> schema_migrations initialiseren op staging"
curl -fsS -X POST \
  -H "X-CE-Reset-Token: $STAGING_TOKEN" \
  -H 'Content-Type: application/json' \
  "$STAGING_URL/api/admin/schema-migrations-init" | python3 -m json.tool

echo "==> Openstaande migraties toepassen op staging"
"$ROOT/scripts/run-migration.sh" staging --all || true

# 3. Geanonimiseerde demo-tenant. Geen echte e-mailadressen, geen echte tokens.
DEMO_EMAIL="demo@staging.invalid"
DEMO_PASSWORD="staging-$(LC_ALL=C tr -dc 'A-Za-z0-9' </dev/urandom | head -c 16)"

echo "==> Demo-tenant aanmaken ($DEMO_EMAIL)"
PAYLOAD="$(python3 - "$DEMO_EMAIL" "$DEMO_PASSWORD" <<'PY'
import json, sys
print(json.dumps({
    'company': 'Demo Tenant (staging)',
    'contactEmail': sys.argv[1],
    'loginEmail': sys.argv[1],
    'password': sys.argv[2],
    'postsPerWeek': 2,
    'publishMode': 'manual_with_review',
    'brandGoal': 'Voorbeelddoel voor staging — geen klantdata.',
    'writingGuide': 'Korte, concrete zinnen. Nederlands. Voorbeeldtekst voor staging.',
}))
PY
)"

RESPONSE="$(curl -sS -X POST \
  -H "X-CE-Reset-Token: $STAGING_TOKEN" \
  -H 'Content-Type: application/json' \
  -d "$PAYLOAD" \
  "$STAGING_URL/api/admin/create-client")"

echo "$RESPONSE" | python3 -m json.tool || echo "$RESPONSE"

echo
echo "==> Klaar"
echo "    Staging login: $DEMO_EMAIL / $DEMO_PASSWORD"
echo "    Bewaar dit wachtwoord in je wachtwoordmanager — het staat nergens anders."
echo
echo "Let op: n8n-webhooks in .env.staging moeten naar DEV-workflows wijzen."
echo "Anders schrijft een staging-run in de productie-pipeline."
