#!/usr/bin/env bash
# Build React app + upload web/ via FTP.
#
#   ./scripts/deploy-ftp.sh              # .env (bestaand gedrag)
#   ./scripts/deploy-ftp.sh staging      # .env.staging
#   ./scripts/deploy-ftp.sh brandflocks  # .env.brandflocks (Antagonist tests/training)
#   ./scripts/deploy-ftp.sh production   # .env.production, vraagt bevestiging
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB="$ROOT/web"
FRONTEND="$ROOT/frontend"

TARGET_ENV="${1:-}"
if [[ -n "$TARGET_ENV" && ! "$TARGET_ENV" =~ ^(local|staging|brandflocks|production)$ ]]; then
  echo "Onbekende omgeving: $TARGET_ENV (kies local, staging, brandflocks of production)" >&2
  exit 1
fi

# Geen terugval op .env bij een expliciete omgeving. Zonder .env.staging zou
# `deploy-ftp.sh staging` met productie-FTP en productie-DB deployen, de
# bevestigingsprompt overslaan én ce_env='staging' op productie schrijven — waarmee
# ce_is_production() false wordt en de destructieve guard daar juist uitgaat.
if [[ -n "$TARGET_ENV" ]]; then
  ENV_FILE="$ROOT/.env.$TARGET_ENV"
  if [[ ! -f "$ENV_FILE" ]]; then
    echo "Geen .env.$TARGET_ENV gevonden in $ROOT" >&2
    echo >&2
    echo "Terugvallen op .env zou naar PRODUCTIE deployen met een $TARGET_ENV-vlag." >&2
    echo "Maak eerst het env-bestand aan:" >&2
    echo "  cp .env.staging.example .env.$TARGET_ENV   # en vul de $TARGET_ENV-waarden in" >&2
    echo "  ./scripts/check-env-isolation.sh $TARGET_ENV" >&2
    exit 1
  fi
else
  ENV_FILE="$ROOT/.env"
  if [[ ! -f "$ENV_FILE" ]]; then
    echo "Missing $ENV_FILE" >&2
    exit 1
  fi
fi

# CE_ENV uit het env-bestand, tenzij expliciet als argument meegegeven.
DEPLOY_ENV="$(python3 - "$ENV_FILE" <<'PY'
import sys
from pathlib import Path
value = ''
for line in Path(sys.argv[1]).read_text().splitlines():
    line = line.strip()
    if line.startswith('CE_ENV=') and '=' in line:
        value = line.split('=', 1)[1].strip().strip('"').strip("'").split(' #')[0].strip()
print(value or 'production')
PY
)"
if [[ -n "$TARGET_ENV" ]]; then
  DEPLOY_ENV="$TARGET_ENV"
fi

echo "==> Omgeving: $DEPLOY_ENV  (env-bestand: $(basename "$ENV_FILE"))"

# Een staging-deploy met productie-FTP en productie-DB is de duurste fout die dit script
# kan maken, en die configuratie ziet er in het bestand normaal uit. Daarom hier en niet
# alleen in de documentatie.
if [[ "$DEPLOY_ENV" != "production" && "$ENV_FILE" != "$ROOT/.env" ]]; then
  if ! "$ROOT/scripts/check-env-isolation.sh" "$DEPLOY_ENV"; then
    echo >&2
    echo "Deploy afgebroken: .env.$DEPLOY_ENV is niet losgekoppeld van productie." >&2
    exit 1
  fi
  echo
fi

if [[ "$DEPLOY_ENV" == "production" ]]; then
  if ! git -C "$ROOT" diff --quiet || ! git -C "$ROOT" diff --cached --quiet; then
    echo
    echo "WAARSCHUWING: er zijn niet-gecommitte wijzigingen."
    echo "Productie hoort vanaf een gecommitte staat te draaien, zodat je achteraf weet wat er live is."
    git -C "$ROOT" status --short | head -n 20
    echo
    read -r -p "Toch doorgaan naar PRODUCTIE? typ 'ja': " confirm_dirty
    if [[ "$confirm_dirty" != "ja" ]]; then
      echo "Afgebroken."
      exit 1
    fi
  fi

  COMMIT="$(git -C "$ROOT" rev-parse --short HEAD 2>/dev/null || echo 'onbekend')"
  echo
  echo "Je gaat deployen naar PRODUCTIE (commit $COMMIT). Betalende tenants werken hier."
  read -r -p "Typ 'production' om te bevestigen: " confirm_env
  if [[ "$confirm_env" != "production" ]]; then
    echo "Afgebroken."
    exit 1
  fi
fi

echo "==> Sync platform-logo (SVG/iGiSNext_Blue-main.svg)"
"$ROOT/scripts/sync-platform-logo.sh"

echo "==> Building frontend"
(cd "$FRONTEND" && npm run build)

echo "==> Merging frontend/dist into web/"
shopt -s dotglob nullglob
for item in "$FRONTEND/dist"/*; do
  base="$(basename "$item")"
  if [[ "$base" == "index.html" ]]; then
    cp "$item" "$WEB/index.html"
  elif [[ -d "$item" ]]; then
    rm -rf "$WEB/$base"
    cp -R "$item" "$WEB/$base"
  else
    cp "$item" "$WEB/$base"
  fi
done

echo "==> Syncing SQL migrations naar web/migrations/"
MIGRATIONS_SRC="$ROOT/scripts/migrations"
MIGRATIONS_DST="$WEB/migrations"
rm -rf "$MIGRATIONS_DST"
mkdir -p "$MIGRATIONS_DST"
shopt -s nullglob
for sql in "$MIGRATIONS_SRC"/*.sql; do
  cp "$sql" "$MIGRATIONS_DST/"
done
MIG_COUNT="$(find "$MIGRATIONS_DST" -maxdepth 1 -name '*.sql' | wc -l | tr -d ' ')"
echo "    $MIG_COUNT migratiebestand(en) klaar voor upload"

echo "==> Writing web/includes/db.local.php from $(basename "$ENV_FILE")"
python3 - "$ENV_FILE" "$WEB/includes/db.local.php" <<'PY'
import sys
from pathlib import Path

def parse_env_value(raw: str) -> str:
    raw = raw.strip()
    if not raw:
        return ''
    if raw[0] in '"\'':
        q = raw[0]
        i = 1
        out = []
        while i < len(raw):
            if raw[i] == '\\' and i + 1 < len(raw):
                out.append(raw[i + 1])
                i += 2
                continue
            if raw[i] == q:
                break
            out.append(raw[i])
            i += 1
        return ''.join(out)
    if ' #' in raw:
        raw = raw.split(' #', 1)[0]
    return raw.strip()

def php_str(value: str) -> str:
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"

env_path = Path(sys.argv[1])
out_path = Path(sys.argv[2])
values = {k: '' for k in ('DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASS')}

for line in env_path.read_text().splitlines():
    line = line.strip()
    if not line or line.startswith('#') or '=' not in line:
        continue
    key, val = line.split('=', 1)
    key = key.strip()
    if key in values:
        values[key] = parse_env_value(val)

content = f"""<?php

declare(strict_types=1);

/** Auto-generated by scripts/deploy-ftp.sh — do not commit */
return [
    'host' => {php_str(values['DB_HOST'])},
    'port' => {php_str(values['DB_PORT'] or '3306')},
    'name' => {php_str(values['DB_NAME'])},
    'user' => {php_str(values['DB_USER'])},
    'pass' => {php_str(values['DB_PASS'])},
];
"""
out_path.write_text(content)
print('Wrote', out_path)
if not values['DB_HOST'] or not values['DB_NAME'] or not values['DB_USER']:
    print('WARNING: DB_* not set in .env — API will return 503 until configured')
PY

echo "==> Writing web/includes/ce.local.php from $(basename "$ENV_FILE")"
python3 - "$ENV_FILE" "$WEB/includes/ce.local.php" "$DEPLOY_ENV" <<'PY'
import sys
from pathlib import Path

def parse_env_value(raw: str) -> str:
    raw = raw.strip()
    if not raw:
        return ''
    if raw[0] in '"\'':
        q = raw[0]
        i = 1
        out = []
        while i < len(raw):
            if raw[i] == '\\' and i + 1 < len(raw):
                out.append(raw[i + 1])
                i += 2
                continue
            if raw[i] == q:
                break
            out.append(raw[i])
            i += 1
        return ''.join(out)
    if ' #' in raw:
        raw = raw.split(' #', 1)[0]
    return raw.strip()

def php_str(value: str) -> str:
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"

env_path = Path(sys.argv[1])
out_path = Path(sys.argv[2])
ce_env = sys.argv[3] if len(sys.argv) > 3 else 'production'
support_email = ''
pilot_alert_emails = ''
webhook = ''
human_webhook = ''
ce01b_webhook = ''
ce01_webhook = ''
ce02_webhook = ''
ce03_webhook = ''
ce07_webhook = ''
ce_llm_visibility_webhook = ''
review_email = 'erik@igisnext.com'
mail_from = ''
public_base_url = 'https://ce.igisnext.com'
allowed_origins = 'https://ce.igisnext.com,http://localhost:5173'
wp_site = ''
wp_user = ''
wp_pass = ''
wp_db_name = ''
wp_db_user = ''
wp_db_pass = ''
wp_author_id = '1'
trello_key = ''
trello_token = ''
google_client_id = ''
google_client_secret = ''
linkedin_client_id = ''
linkedin_client_secret = ''
linkedin_oauth_scopes = ''
credential_key = ''
reset_token = ''
auth_disabled = False
n8n_api_url = ''
n8n_api_key = ''
anthropic_api_key = ''
openai_api_key = ''
perplexity_api_key = ''
google_gemini_api_key = ''
google_custom_search_engine_id = ''
support_chat_budget_eur = '5'
apify_api_token = ''
support_imap_email = ''
support_imap_password = ''
support_imap_host = ''
for line in env_path.read_text().splitlines():
    line = line.strip()
    if not line or line.startswith('#') or '=' not in line:
        continue
    key, val = line.split('=', 1)
    key = key.strip()
    if key == 'N8N_CE04_WEBHOOK_URL':
        webhook = parse_env_value(val)
    elif key == 'N8N_CE04_HUMAN_WEBHOOK_URL':
        human_webhook = parse_env_value(val)
    elif key == 'N8N_CE01B_WEBHOOK_URL':
        ce01b_webhook = parse_env_value(val)
    elif key == 'N8N_CE01_WEBHOOK_URL':
        ce01_webhook = parse_env_value(val)
    elif key == 'N8N_CE02_WEBHOOK_URL':
        ce02_webhook = parse_env_value(val)
    elif key == 'N8N_CE03_WEBHOOK_URL':
        ce03_webhook = parse_env_value(val)
    elif key == 'N8N_CE07_WEBHOOK_URL':
        ce07_webhook = parse_env_value(val)
    elif key == 'N8N_CE_LLM_VISIBILITY_WEBHOOK_URL':
        ce_llm_visibility_webhook = parse_env_value(val)
    elif key == 'CE_REVIEW_EMAIL':
        parsed = parse_env_value(val)
        if parsed:
            review_email = parsed
    elif key == 'CE_PUBLIC_BASE_URL':
        parsed = parse_env_value(val)
        if parsed:
            public_base_url = parsed
    elif key == 'CE_ALLOWED_ORIGINS':
        parsed = parse_env_value(val)
        if parsed:
            allowed_origins = parsed
    elif key == 'CE_MAIL_FROM':
        parsed = parse_env_value(val)
        if parsed:
            mail_from = parsed
    elif key == 'WORDPRESS_SITE_URL':
        parsed = parse_env_value(val)
        if parsed:
            wp_site = parsed
    elif key == 'WORDPRESS_USER':
        parsed = parse_env_value(val)
        if parsed:
            wp_user = parsed
    elif key == 'WORDPRESS_APP_PASSWORD':
        parsed = parse_env_value(val)
        if parsed:
            wp_pass = parsed
    elif key == 'WP_DB_NAME':
        parsed = parse_env_value(val)
        if parsed:
            wp_db_name = parsed
    elif key == 'WP_DB_USER':
        parsed = parse_env_value(val)
        if parsed:
            wp_db_user = parsed
    elif key == 'WP_DB_PASS':
        parsed = parse_env_value(val)
        if parsed:
            wp_db_pass = parsed
    elif key == 'WORDPRESS_AUTHOR_ID':
        parsed = parse_env_value(val)
        if parsed:
            wp_author_id = parsed
    elif key == 'TRELLO_API_KEY':
        parsed = parse_env_value(val)
        if parsed:
            trello_key = parsed
    elif key == 'TRELLO_TOKEN':
        parsed = parse_env_value(val)
        if parsed:
            trello_token = parsed
    elif key == 'GOOGLE_CLIENT_ID':
        parsed = parse_env_value(val)
        if parsed:
            google_client_id = parsed
    elif key == 'GOOGLE_CLIENT_SECRET':
        parsed = parse_env_value(val)
        if parsed:
            google_client_secret = parsed
    elif key == 'LINKEDIN_CLIENT_ID':
        parsed = parse_env_value(val)
        if parsed:
            linkedin_client_id = parsed
    elif key == 'LINKEDIN_CLIENT_SECRET':
        parsed = parse_env_value(val)
        if parsed:
            linkedin_client_secret = parsed
    elif key == 'LINKEDIN_OAUTH_SCOPES':
        parsed = parse_env_value(val)
        if parsed:
            linkedin_oauth_scopes = parsed
    elif key == 'CE_CREDENTIAL_KEY':
        parsed = parse_env_value(val)
        if parsed:
            credential_key = parsed
    elif key == 'CE_RESET_TOKEN':
        parsed = parse_env_value(val)
        if parsed:
            reset_token = parsed
    elif key == 'CE_AUTH_DISABLED':
        parsed = parse_env_value(val).lower()
        auth_disabled = parsed in ('1', 'true', 'yes')
    elif key == 'N8N_API_URL':
        parsed = parse_env_value(val)
        if parsed:
            n8n_api_url = parsed.rstrip('/')
    elif key == 'N8N_API_KEY':
        parsed = parse_env_value(val)
        if parsed:
            n8n_api_key = parsed
    elif key == 'ANTHROPIC_API_KEY':
        parsed = parse_env_value(val)
        if parsed:
            anthropic_api_key = parsed
    elif key == 'OPENAI_API_KEY':
        parsed = parse_env_value(val)
        if parsed:
            openai_api_key = parsed
    elif key == 'PERPLEXITY_API_KEY':
        parsed = parse_env_value(val)
        if parsed:
            perplexity_api_key = parsed
    elif key == 'GOOGLE_GEMINI_API_KEY':
        parsed = parse_env_value(val)
        if parsed:
            google_gemini_api_key = parsed
    elif key == 'GOOGLE_CUSTOM_SEARCH_ENGINE_ID':
        parsed = parse_env_value(val)
        if parsed:
            google_custom_search_engine_id = parsed
    elif key == 'CE_SUPPORT_CHAT_BUDGET_EUR':
        parsed = parse_env_value(val)
        if parsed:
            support_chat_budget_eur = parsed
    elif key == 'APIFY_API_TOKEN':
        parsed = parse_env_value(val)
        if parsed:
            apify_api_token = parsed
    elif key == 'CE_SUPPORT_EMAIL':
        parsed = parse_env_value(val)
        if parsed:
            support_email = parsed
    elif key == 'CE_PILOT_ALERT_EMAILS':
        parsed = parse_env_value(val)
        if parsed:
            pilot_alert_emails = parsed
    elif key == 'CE_SUPPORT_IMAP_EMAIL':
        parsed = parse_env_value(val)
        if parsed:
            support_imap_email = parsed
    elif key == 'CE_SUPPORT_IMAP_PASSWORD':
        parsed = parse_env_value(val)
        if parsed:
            support_imap_password = parsed
    elif key == 'CE_SUPPORT_IMAP_HOST':
        parsed = parse_env_value(val)
        if parsed:
            support_imap_host = parsed

trello_boards_webhook = f"{n8n_api_url}/webhook/ce-trello-boards" if n8n_api_url else ''

content = f"""<?php

declare(strict_types=1);

/** Auto-generated by scripts/deploy-ftp.sh — do not commit */
return [
    'ce_env' => {php_str(ce_env)},
    'support_email' => {php_str(support_email)},
    'pilot_alert_emails' => {php_str(pilot_alert_emails)},
    'support_imap_email' => {php_str(support_imap_email)},
    'support_imap_password' => {php_str(support_imap_password)},
    'support_imap_host' => {php_str(support_imap_host)},
    'ce04_webhook_url' => {php_str(webhook)},
    'ce04_human_webhook_url' => {php_str(human_webhook)},
    'ce01b_webhook_url' => {php_str(ce01b_webhook)},
    'ce01_webhook_url' => {php_str(ce01_webhook)},
    'ce02_webhook_url' => {php_str(ce02_webhook)},
    'ce03_webhook_url' => {php_str(ce03_webhook)},
    'ce07_webhook_url' => {php_str(ce07_webhook)},
    'ce_llm_visibility_webhook_url' => {php_str(ce_llm_visibility_webhook)},
    'review_email' => {php_str(review_email)},
    'mail_from' => {php_str(mail_from)},
    'public_base_url' => {php_str(public_base_url)},
    'allowed_origins' => {php_str(allowed_origins)},
    'wordpress_site_url' => {php_str(wp_site)},
    'wordpress_user' => {php_str(wp_user)},
    'wordpress_app_password' => {php_str(wp_pass)},
    'wp_db_name' => {php_str(wp_db_name)},
    'wp_db_user' => {php_str(wp_db_user)},
    'wp_db_pass' => {php_str(wp_db_pass)},
    'wordpress_author_id' => {int(wp_author_id) if str(wp_author_id).isdigit() else 1},
    'trello_api_key' => {php_str(trello_key)},
    'trello_token' => {php_str(trello_token)},
    'trello_boards_webhook_url' => {php_str(trello_boards_webhook)},
    'google_client_id' => {php_str(google_client_id)},
    'google_client_secret' => {php_str(google_client_secret)},
    'linkedin_client_id' => {php_str(linkedin_client_id)},
    'linkedin_client_secret' => {php_str(linkedin_client_secret)},
    'linkedin_oauth_scopes' => {php_str(linkedin_oauth_scopes)},
    'credential_key' => {php_str(credential_key)},
    'reset_token' => {php_str(reset_token)},
    'auth_disabled' => {str(auth_disabled).lower()},
    'anthropic_api_key' => {php_str(anthropic_api_key)},
    'openai_api_key' => {php_str(openai_api_key)},
    'perplexity_api_key' => {php_str(perplexity_api_key)},
    'google_gemini_api_key' => {php_str(google_gemini_api_key)},
    'google_custom_search_engine_id' => {php_str(google_custom_search_engine_id)},
    'support_chat_budget_eur' => {php_str(support_chat_budget_eur)},
    'apify_api_token' => {php_str(apify_api_token)},
    'n8n_api_url' => {php_str(n8n_api_url)},
    'n8n_api_key' => {php_str(n8n_api_key)},
];
"""
out_path.write_text(content)
print('Wrote', out_path)
if not webhook:
    print('NOTE: N8N_CE04_WEBHOOK_URL not set — AI review webhook skipped until configured')
if not human_webhook:
    print('NOTE: N8N_CE04_HUMAN_WEBHOOK_URL not set — approval-mail skipped until configured')
PY

echo "==> Uploading via lftp (SFTP/FTPS/FTP — auto-detect)"
python3 "$ROOT/scripts/deploy_ftp_upload.py" "$ENV_FILE" "$WEB" full

BASE_URL="$(python3 - "$ENV_FILE" <<'PY'
import sys
from pathlib import Path
value = ''
for line in Path(sys.argv[1]).read_text().splitlines():
    line = line.strip()
    if line.startswith('CE_PUBLIC_BASE_URL=') and '=' in line:
        value = line.split('=', 1)[1].strip().strip('"').strip("'").split(' #')[0].strip()
print(value or 'https://ce.igisnext.com')
PY
)"

echo "==> Live: $BASE_URL/"
echo "    API health: $BASE_URL/api/health"

if [[ -x "$ROOT/scripts/smoke-test.sh" ]]; then
  echo "==> Smoke-test"
  "$ROOT/scripts/smoke-test.sh" "$DEPLOY_ENV" || echo "WAARSCHUWING: smoke-test meldde problemen — controleer bovenstaande regels."
fi
