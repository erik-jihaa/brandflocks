#!/usr/bin/env bash
# Deploy linkedin-analyse/ naar linkedin-analyse.igisnext.com via FTP/SFTP.
#
#   ./scripts/deploy-linkedin-analyse.sh              # .env.production
#   ./scripts/deploy-linkedin-analyse.sh production   # idem
#   ./scripts/deploy-linkedin-analyse.sh probe        # alleen FTP-test
#
# Vereist in .env.production (of .env):
#   LINKEDIN_ANALYSE_FTP_*  — FTP naar subdomein (of hoofdaccount + remote path)
#   LINKEDIN_ANALYSE_DB_*   — aparte MySQL-database
#   LINKEDIN_ANALYSE_APP_PASSWORD — tool-wachtwoord Erik (plain; wordt gehasht)
#   LINKEDIN_ANALYSE_APP_EMAIL — optioneel, default erik@igisnext.com
#   LINKEDIN_ANALYSE_EVA_APP_PASSWORD — apart wachtwoord Eva (plain)
#   LINKEDIN_ANALYSE_EVA_EMAIL — optioneel, default eva@igisnext.com
#   APIFY_API_TOKEN         — platform-token (zelfde als CE)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/linkedin-analyse"
TARGET_ENV="${1:-production}"

if [[ "$TARGET_ENV" == "probe" ]]; then
  ENV_FILE="$ROOT/.env.production"
  [[ -f "$ENV_FILE" ]] || ENV_FILE="$ROOT/.env"
  PROBE_ONLY=true
else
  PROBE_ONLY=false
fi

if [[ "$TARGET_ENV" != "production" && "$TARGET_ENV" != "probe" ]]; then
  echo "Alleen 'production' of 'probe' ondersteund." >&2
  exit 1
fi

ENV_FILE="$ROOT/.env.production"
if [[ ! -f "$ENV_FILE" ]]; then
  ENV_FILE="$ROOT/.env"
fi
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Geen .env.production of .env gevonden." >&2
  exit 1
fi

echo "==> LinkedIn Scan deploy ($TARGET_ENV)"
echo "    env: $(basename "$ENV_FILE")"

if [[ "$PROBE_ONLY" != true ]]; then
python3 - "$ENV_FILE" "$TOOL/includes/config.local.php" <<'PY'
import sys
from pathlib import Path

def parse_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        k, v = line.split('=', 1)
        v = v.strip()
        if len(v) >= 2 and v[0] == v[-1] and v[0] in '"\'':
            v = v[1:-1]
        elif ' #' in v:
            v = v.split(' #', 1)[0].strip()
        out[k.strip()] = v
    return out

def pick(env: dict[str, str], key: str, fallback_key: str | None = None, default: str = '') -> str:
    if key in env and env[key].strip():
        return env[key].strip()
    if fallback_key and env.get(fallback_key, '').strip():
        return env[fallback_key].strip()
    return default

env_path = Path(sys.argv[1])
out_path = Path(sys.argv[2])
env = parse_env(env_path)

apify = pick(env, 'APIFY_API_TOKEN')
if not apify:
    raise SystemExit('APIFY_API_TOKEN ontbreekt in env-bestand.')

db_host = pick(env, 'LINKEDIN_ANALYSE_DB_HOST', 'DB_HOST', 'localhost')
db_name = pick(env, 'LINKEDIN_ANALYSE_DB_NAME')
db_user = pick(env, 'LINKEDIN_ANALYSE_DB_USER')
db_pass = pick(env, 'LINKEDIN_ANALYSE_DB_PASS', 'DB_PASS')

missing_db = [name for name, val in [('LINKEDIN_ANALYSE_DB_NAME', db_name), ('LINKEDIN_ANALYSE_DB_USER', db_user), ('LINKEDIN_ANALYSE_DB_PASS', db_pass)] if not val]
if missing_db:
    raise SystemExit(
        'Database-vars ontbreken: ' + ', '.join(missing_db) + '\n'
        'Voeg LINKEDIN_ANALYSE_DB_NAME, LINKEDIN_ANALYSE_DB_USER, LINKEDIN_ANALYSE_DB_PASS toe aan ' + env_path.name
    )

app_password = pick(env, 'LINKEDIN_ANALYSE_APP_PASSWORD')
if not app_password:
    raise SystemExit('LINKEDIN_ANALYSE_APP_PASSWORD ontbreekt (tool-login wachtwoord).')

app_email = pick(env, 'LINKEDIN_ANALYSE_APP_EMAIL', default='erik@igisnext.com').lower()
eva_email = pick(env, 'LINKEDIN_ANALYSE_EVA_EMAIL', default='eva@igisnext.com').lower()
eva_password = pick(env, 'LINKEDIN_ANALYSE_EVA_APP_PASSWORD')

import subprocess
import hashlib

def hash_password(plain: str) -> str:
    proc = subprocess.run(
        ['php', '-r', f"echo password_hash({plain!r}, PASSWORD_DEFAULT);"],
        capture_output=True,
        text=True,
        check=True,
    )
    return proc.stdout.strip()

app_users = {app_email: hash_password(app_password)}
if eva_password:
    app_users[eva_email] = hash_password(eva_password)
else:
    print('Let op: LINKEDIN_ANALYSE_EVA_APP_PASSWORD ontbreekt — alleen', app_email, 'kan inloggen.', file=sys.stderr)

app_users_php = ',\n        '.join(
    f"{email!r} => {hash_val!r}" for email, hash_val in sorted(app_users.items())
)

base_url = pick(env, 'LINKEDIN_ANALYSE_BASE_URL', default='https://linkedin-analyse.igisnext.com').rstrip('/')
logo_url = pick(env, 'LINKEDIN_ANALYSE_REPORT_LOGO_URL', default=f'{base_url}/assets/iGiSNext_Blue.png')

usd_to_eur = pick(env, 'LINKEDIN_ANALYSE_USD_TO_EUR', default='0.92')
max_posts = pick(env, 'LINKEDIN_ANALYSE_MAX_POSTS', default='30')
cache_days = pick(env, 'LINKEDIN_ANALYSE_CACHE_DAYS', default='7')
daily_limit = pick(env, 'LINKEDIN_ANALYSE_DAILY_SCAN_LIMIT', default='20')
daily_cost = pick(env, 'LINKEDIN_ANALYSE_DAILY_COST_LIMIT_USD', default='5.0')
timeout = pick(env, 'LINKEDIN_ANALYSE_APIFY_TIMEOUT_SEC', default='120')
retention = pick(env, 'LINKEDIN_ANALYSE_RAW_DATA_RETENTION_DAYS', default='90')
school_actor = pick(env, 'LINKEDIN_ANALYSE_SCHOOL_ACTOR_ID', default='')
school_poll_wait = pick(env, 'LINKEDIN_ANALYSE_SCHOOL_POLL_WAIT_SEC', default='20')

anthropic_key = pick(env, 'LINKEDIN_ANALYSE_ANTHROPIC_API_KEY', 'ANTHROPIC_API_KEY')
ai_enabled_raw = pick(env, 'LINKEDIN_ANALYSE_AI_SUMMARY_ENABLED', default='true').lower()
ai_enabled = ai_enabled_raw in ('1', 'true', 'yes', 'on')
anthropic_model = pick(env, 'LINKEDIN_ANALYSE_ANTHROPIC_MODEL', default='claude-haiku-4-5-20251001')
narrative_max_cost = pick(env, 'LINKEDIN_ANALYSE_NARRATIVE_MAX_COST_USD', default='0.02')
mail_from = pick(env, 'LINKEDIN_ANALYSE_MAIL_FROM', 'CE_MAIL_FROM', default='LinkedIn Scan <support@igisnext.com>')
migration_key = hashlib.sha256(app_password.encode()).hexdigest()

php = f"""<?php

declare(strict_types=1);

/** Auto-generated by scripts/deploy-linkedin-analyse.sh — niet committen */

if (
    PHP_SAPI !== 'cli'
    && isset($_SERVER['SCRIPT_FILENAME'])
    && realpath((string) $_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__)
) {{
    http_response_code(404);
    exit;
}}

return [
    'db_host' => {db_host!r},
    'db_name' => {db_name!r},
    'db_user' => {db_user!r},
    'db_pass' => {db_pass!r},
    'apify_api_token' => {apify!r},
    'app_users' => [
        {app_users_php}
    ],
    'base_url' => {base_url!r},
    'report_logo_url' => {logo_url!r},
    'usd_to_eur' => {float(usd_to_eur)},
    'max_posts' => {int(max_posts)},
    'cache_days' => {int(cache_days)},
    'daily_scan_limit' => {int(daily_limit)},
    'daily_cost_limit_usd' => {float(daily_cost)},
    'apify_timeout_sec' => {int(timeout)},
    'school_actor_id' => {school_actor!r},
    'school_poll_wait_sec' => {int(school_poll_wait)},
    'raw_data_retention_days' => {int(retention)},
    'anthropic_api_key' => {anthropic_key!r},
    'ai_summary_enabled' => {str(ai_enabled).lower()},
    'anthropic_model' => {anthropic_model!r},
    'narrative_max_cost_usd_per_scan' => {float(narrative_max_cost)},
    'mail_from' => {mail_from!r},
    'migration_key' => {migration_key!r},
];
"""

out_path.write_text(php)
print(f'Geschreven: {out_path.relative_to(env_path.parent)}')
PY
fi

# Tijdelijk env voor FTP-upload (eigen subdomein-pad/user)
DEPLOY_ENV_TMP="$(mktemp)"
python3 - "$ENV_FILE" "$DEPLOY_ENV_TMP" <<'PY'
import sys
from pathlib import Path

def parse_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith('#') or '=' not in line:
            continue
        k, v = line.split('=', 1)
        v = v.strip()
        if len(v) >= 2 and v[0] == v[-1] and v[0] in '"\'':
            v = v[1:-1]
        elif ' #' in v:
            v = v.split(' #', 1)[0].strip()
        out[k.strip()] = v
    return out

def pick(env: dict[str, str], key: str, fallback_key: str | None = None, default: str = '') -> str:
    if key in env and env[key].strip():
        return env[key].strip()
    if fallback_key and env.get(fallback_key, '').strip():
        return env[fallback_key].strip()
    return default

src = Path(sys.argv[1])
dst = Path(sys.argv[2])
env = parse_env(src)

lines = [
    f"FTP_HOST={pick(env, 'LINKEDIN_ANALYSE_FTP_HOST', 'FTP_HOST')}",
    f"FTP_PORT={pick(env, 'LINKEDIN_ANALYSE_FTP_PORT', 'FTP_PORT', '21')}",
    f"FTP_USER={pick(env, 'LINKEDIN_ANALYSE_FTP_USER', 'FTP_USER')}",
    f"FTP_PASSWORD={pick(env, 'LINKEDIN_ANALYSE_FTP_PASSWORD', 'FTP_PASSWORD')}",
    f"FTP_PROTOCOL={pick(env, 'LINKEDIN_ANALYSE_FTP_PROTOCOL', 'FTP_PROTOCOL', 'ftp')}",
    f"FTP_REMOTE_PATH={pick(env, 'LINKEDIN_ANALYSE_FTP_REMOTE_PATH', default='/')}",
]
dst.write_text('\n'.join(lines) + '\n')
print('FTP remote:', lines[-1].split('=', 1)[1])
print('FTP user:  ', lines[2].split('=', 1)[1])
PY

cleanup() { rm -f "$DEPLOY_ENV_TMP"; }
trap cleanup EXIT

if [[ "$PROBE_ONLY" == true ]]; then
  python3 "$ROOT/scripts/deploy_ftp_upload.py" "$DEPLOY_ENV_TMP" "$TOOL" full --probe-only
  exit 0
fi

echo
read -r -p "Deploy LinkedIn Scan naar productie? typ 'ja': " confirm
if [[ "$confirm" != "ja" ]]; then
  echo "Afgebroken."
  exit 1
fi

echo "==> Sync platform-logo (SVG/iGiSNext_Blue-main.svg)"
"$ROOT/scripts/sync-platform-logo.sh"

# Geen fixtures/scripts/sql/docs meedeployen (briefing beveiliging Ronde A).
# CE-deploy (deploy-ftp.sh) geeft deze flags niet mee — blijft byte-voor-byte ongewijzigd.
python3 "$ROOT/scripts/deploy_ftp_upload.py" "$DEPLOY_ENV_TMP" "$TOOL" full \
  --exclude-glob 'output/*' \
  --exclude-glob 'fixtures/*' \
  --exclude-glob 'scripts/*' \
  --exclude-glob 'sql/*' \
  --exclude-glob '*.md' \
  --exclude .gitignore \
  --exclude .htaccess.example \
  --exclude includes/config.local.php.example

echo
echo "==> Na deploy: oude blootgestelde mappen op de server handmatig verwijderen"
echo "    (mirror verwijdert niets). Lijst eerst aan Erik tonen — zie briefing A6:"
echo "    output/ fixtures/ scripts/ sql/ BUILD.md .gitignore .htaccess.example"
echo "    includes/config.local.php.example"
echo "    Laat includes/config.local.php staan."
echo
BASE_URL=$(python3 - <<PY
from pathlib import Path
for line in Path('$ENV_FILE').read_text().splitlines():
    if line.startswith('LINKEDIN_ANALYSE_BASE_URL='):
        print(line.split('=',1)[1].strip().strip('"').strip("'") or 'https://linkedin-analyse.igisnext.com')
        break
else:
    print('https://linkedin-analyse.igisnext.com')
PY
)

MIGRATION_KEY=$(python3 - <<PY
import hashlib
from pathlib import Path
pw = ''
for line in Path('$ENV_FILE').read_text().splitlines():
    if line.startswith('LINKEDIN_ANALYSE_APP_PASSWORD='):
        pw = line.split('=',1)[1].strip().strip('"').strip("'")
        break
print(hashlib.sha256(pw.encode()).hexdigest())
PY
)

echo
echo "==> Migratie branche_code…"
MIGRATION_OUT=$(curl -sS -f "${BASE_URL}/migrate.php?key=${MIGRATION_KEY}" || true)
if [[ -z "$MIGRATION_OUT" ]]; then
  echo "    Waarschuwing: migrate.php gaf geen response — voer handmatig uit:" >&2
  echo "    mysql … < linkedin-analyse/sql/add_branche_column.sql" >&2
else
  echo "    $MIGRATION_OUT"
fi

echo
echo "==> Deploy klaar."
echo "    URL: $BASE_URL"
echo "    Database: voer sql/schema.sql uit als dat nog niet gebeurd is."
echo "    Test: open index.php en draai een scan."
