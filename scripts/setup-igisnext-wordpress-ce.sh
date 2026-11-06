#!/usr/bin/env bash
# Koppel Content Engine Publish WP aan https://igisnext.com (hoofdsite, niet ce.igisnext.com/blog)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV="$ROOT/.env"
FRONTEND_ENV="$ROOT/../../Cursor_iGiSNext_frontend/.env.prod"

if [[ -f "$ENV" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV"
  set +a
fi

SETUP_KEY="${CE_WP_SETUP_KEY:-${CE_RESET_TOKEN:-}}"

if [[ ! -f "$ENV" ]]; then
  echo "Missing $ENV" >&2
  exit 1
fi
if [[ ! -f "$FRONTEND_ENV" ]]; then
  echo "Missing $FRONTEND_ENV (FTP igisnext.com)" >&2
  exit 1
fi
if [[ -z "$SETUP_KEY" ]]; then
  echo "Zet CE_RESET_TOKEN of CE_WP_SETUP_KEY in .env" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1090
source "$FRONTEND_ENV"
set +a
FTP_PASS="${FTP_PASS%\'}"
FTP_PASS="${FTP_PASS#\'}"
FTP_PASS="${FTP_PASS%\"}"
FTP_PASS="${FTP_PASS#\"}"

FTP_REMOTE="${FTP_REMOTE_THEMES:-wp-content/themes}"
# Serverpad (alleen ter info): ${FTP_HOME:-/home/igisnext}/public_html
LOCAL_SETUP="$ROOT/scripts/ce-wp-once-setup.php"
REMOTE_NAME="ce-wp-ce-setup.php"

echo "==> Upload setup script naar igisnext.com public_html ($REMOTE_NAME)"
python3 - "$LOCAL_SETUP" "$FTP_HOST" "$FTP_PORT" "$FTP_USER" "$FTP_PASS" "$REMOTE_NAME" "$SETUP_KEY" <<'PY'
import sys
from ftplib import FTP, error_perm
from pathlib import Path
from io import BytesIO

local, host, port, user, password, remote_name, setup_key = sys.argv[1:8]
data = Path(local).read_text().replace('__CE_WP_SETUP_KEY__', setup_key).encode()
ftp = FTP()
ftp.connect(host, int(port), timeout=60)
ftp.login(user, password)

def cwd_public_html() -> None:
    for path in ('.', 'public_html', '../public_html', '../../public_html'):
        try:
            ftp.cwd('/')
            for part in path.split('/'):
                if part in ('', '.'):
                    continue
                ftp.cwd(part)
            names = set(ftp.nlst())
            if {'wp-config.php', 'wp-content'} & names or 'index.php' in names:
                return
        except error_perm:
            continue
    raise SystemExit(
        'public_html niet bereikbaar via FTP. Zet in cPanel het FTP-account op map public_html.'
    )

cwd_public_html()
ftp.storbinary(f'STOR {remote_name}', BytesIO(data))
ftp.quit()
print(f'Uploaded {remote_name} -> {ftp.pwd() if False else "public_html"}')
PY

SETUP_URL="https://igisnext.com/${REMOTE_NAME}?key=${SETUP_KEY}"
echo "==> Run setup on server"
RESULT="$(curl -fsS "$SETUP_URL")"
echo "$RESULT"

python3 - "$ENV" "$RESULT" <<'PY'
import json, sys
from pathlib import Path

env_path = Path(sys.argv[1])
data = json.loads(sys.argv[2])
if not data.get('ok'):
    raise SystemExit(f"Setup failed: {data}")

app_pass = str(data.get('applicationPassword', '')).replace(' ', '')
updates = {
    'WORDPRESS_SITE_URL': str(data['siteUrl']).rstrip('/'),
    'WORDPRESS_USER': str(data['apiUser']),
    'WORDPRESS_APP_PASSWORD': app_pass,
}
lines = env_path.read_text().splitlines()
out, seen = [], set()
for line in lines:
    matched = False
    for key, val in updates.items():
        if line.startswith(f'{key}='):
            out.append(f'{key}={val}')
            seen.add(key)
            matched = True
            break
    if not matched:
        out.append(line)
for key, val in updates.items():
    if key not in seen:
        out.append(f'{key}={val}')
env_path.write_text('\n'.join(out) + '\n')
print('Updated CE .env WORDPRESS_* → igisnext.com')
PY

echo "==> Remove setup script from server"
python3 - "$FTP_HOST" "$FTP_PORT" "$FTP_USER" "$FTP_PASS" "$REMOTE_NAME" <<'PY'
import sys
from ftplib import FTP, error_perm
host, port, user, password, remote_name = sys.argv[1:6]
ftp = FTP()
ftp.connect(host, int(port), timeout=60)
ftp.login(user, password)
for path in ('.', 'public_html', '../public_html', '../../public_html'):
    try:
        ftp.cwd('/')
        for part in path.split('/'):
            if part in ('', '.'):
                continue
            ftp.cwd(part)
        names = set(ftp.nlst())
        if {'wp-config.php', 'wp-content'} & names or 'index.php' in names:
            break
    except error_perm:
        continue
else:
    print('Could not find public_html for cleanup')
    ftp.quit()
    raise SystemExit(0)
try:
    ftp.delete(remote_name)
    print(f"Deleted {remote_name}")
except error_perm as e:
    print("Could not delete setup script:", e)
ftp.quit()
PY

echo "==> Deploy Content Engine (ce.local.php krijgt nieuwe WORDPRESS_*)"
"$ROOT/scripts/deploy-ftp.sh"

echo ""
echo "==> Klaar — Publish WP post nu naar https://igisnext.com/blog/"
echo "    REST base: https://igisnext.com/wp-json/wp/v2/posts"
