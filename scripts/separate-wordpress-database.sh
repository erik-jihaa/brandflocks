#!/usr/bin/env bash
# Fase 1b — WordPress CE-blog (ce.igisnext.com/blog) naar eigen database.
#
#   ./scripts/separate-wordpress-database.sh status [staging|production]
#   ./scripts/separate-wordpress-database.sh dry-run [staging|production]
#   ./scripts/separate-wordpress-database.sh migrate [staging|production]
#   ./scripts/separate-wordpress-database.sh patch-wp-config [staging|production]
#
# CE praat met WordPress via REST — alleen wp-config.php wijzigt. Klant-WP-sites
# (per tenant) raken deze database nooit.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

ACTION="${1:-status}"
TARGET_ENV="${2:-production}"
ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://ce.igisnext.com}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi
if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

api_get() {
  curl -sS -H "X-CE-Reset-Token: $TOKEN" "$BASE_URL/api/admin/wordpress-db"
}

api_post() {
  local body="$1"
  curl -sS -X POST \
    -H "X-CE-Reset-Token: $TOKEN" \
    -H 'Content-Type: application/json' \
    -d "$body" \
    "$BASE_URL/api/admin/wordpress-db"
}

echo "==> WordPress DB-scheiding — $ACTION ($TARGET_ENV → $BASE_URL)"

case "$ACTION" in
  status)
    api_get | python3 -m json.tool
    ;;

  dry-run)
    TARGET_DB="$(ce_env_value "$ENV_FILE" WP_DB_NAME)"
    if [[ -z "$TARGET_DB" ]]; then
      echo "WP_DB_NAME ontbreekt in $(basename "$ENV_FILE")." >&2
      echo "Stappen in cPanel:" >&2
      echo "  1. MySQL-databases → nieuwe database (bijv. igisnext_wp)" >&2
      echo "  2. Nieuwe gebruiker + ALL PRIVILEGES op die database" >&2
      echo "  3. Voeg toe aan .env.$TARGET_ENV:" >&2
      echo "       WP_DB_NAME=..." >&2
      echo "       WP_DB_USER=..." >&2
      echo "       WP_DB_PASS=..." >&2
      echo "  4. Deploy: ./scripts/deploy-ftp.sh $TARGET_ENV" >&2
      exit 1
    fi
    export TARGET_DB
    api_post "$(python3 - <<'PY'
import json, os
print(json.dumps({'targetDatabase': os.environ['TARGET_DB'], 'dryRun': True}))
PY
)" | python3 -m json.tool
    ;;

  migrate)
    TARGET_DB="$(ce_env_value "$ENV_FILE" WP_DB_NAME)"
    WP_USER="$(ce_env_value "$ENV_FILE" WP_DB_USER)"
    WP_PASS="$(ce_env_value "$ENV_FILE" WP_DB_PASS)"
    if [[ -z "$TARGET_DB" || -z "$WP_USER" || -z "$WP_PASS" ]]; then
      echo "WP_DB_NAME, WP_DB_USER en WP_DB_PASS zijn verplicht in $(basename "$ENV_FILE")." >&2
      exit 1
    fi
    echo "Verplaatst wpce_* van CE-database naar $TARGET_DB."
    if [[ "$TARGET_ENV" == "production" ]]; then
      read -r -p "Typ 'production' om te bevestigen: " confirm
      if [[ "$confirm" != "production" ]]; then
        echo "Afgebroken."
        exit 1
      fi
    fi
    export TARGET_DB TARGET_ENV
    api_post "$(python3 - <<'PY'
import json, os
body = {'targetDatabase': os.environ['TARGET_DB'], 'dryRun': False}
if os.environ.get('TARGET_ENV') == 'production':
    body['confirmEnvironment'] = 'production'
print(json.dumps(body))
PY
)" | python3 -m json.tool
    echo
    echo "==> Volgende stap: ./scripts/separate-wordpress-database.sh patch-wp-config $TARGET_ENV"
    ;;

  patch-wp-config)
    WP_DB="$(ce_env_value "$ENV_FILE" WP_DB_NAME)"
    WP_USER="$(ce_env_value "$ENV_FILE" WP_DB_USER)"
    WP_PASS="$(ce_env_value "$ENV_FILE" WP_DB_PASS)"
    DB_HOST="$(ce_env_value "$ENV_FILE" DB_HOST)"
    DB_HOST="${DB_HOST:-localhost}"
    for var in WP_DB WP_USER WP_PASS; do
      if [[ -z "${!var}" ]]; then
        echo "$var leeg in $(basename "$ENV_FILE")" >&2
        exit 1
      fi
    done
    python3 - "$ENV_FILE" <<'PY'
import re, sys, tempfile, os
from pathlib import Path
from ftplib import FTP

env_path = Path(sys.argv[1])
env = {}
for line in env_path.read_text().splitlines():
    line = line.strip()
    if not line or line.startswith('#') or '=' not in line:
        continue
    k, v = line.split('=', 1)
    env[k.strip()] = v.strip().strip('"').strip("'")

host = env.get('FTP_HOST', '')
port = int(env.get('FTP_PORT', '21') or 21)
user = env.get('FTP_USER', '')
password = env.get('FTP_PASSWORD', '')
remote = (env.get('FTP_REMOTE_PATH') or '/').rstrip('/') or '/'
wp_db = env['WP_DB_NAME']
wp_user = env['WP_DB_USER']
wp_pass = env['WP_DB_PASS']
db_host = env.get('DB_HOST', 'localhost')

ftp = FTP()
ftp.connect(host, port, timeout=120)
ftp.login(user, password)
ftp.cwd(remote)

buf = bytearray()
def collect(data):
    buf.extend(data)

try:
    ftp.retrbinary('RETR blog/wp-config.php', collect)
except Exception as e:
    print('Kon blog/wp-config.php niet ophalen:', e, file=sys.stderr)
    raise SystemExit(1)

text = buf.decode('utf-8', errors='replace')
replacements = {
    'DB_NAME': wp_db,
    'DB_USER': wp_user,
    'DB_PASSWORD': wp_pass,
    'DB_HOST': db_host,
}
for key, val in replacements.items():
    text, n = re.subn(
        rf"define\(\s*'{key}'\s*,\s*'[^']*'\s*\)",
        f"define('{key}', {repr(val)})",
        text,
        count=1,
    )
    if n != 1:
        print(f'Waarschuwing: kon {key} niet patchen ({n} matches)', file=sys.stderr)

tmp = tempfile.NamedTemporaryFile(delete=False)
tmp.write(text.encode('utf-8'))
tmp.close()
with open(tmp.name, 'rb') as fh:
    ftp.storbinary('STOR blog/wp-config.php', fh)
os.unlink(tmp.name)
ftp.quit()
print('blog/wp-config.php bijgewerkt op de server.')
PY
    echo "Test: ${BASE_URL%/}/blog/"
    ;;

  *)
    echo "Onbekende actie: $ACTION (status|dry-run|migrate|patch-wp-config)" >&2
    exit 1
    ;;
esac
