#!/usr/bin/env bash
# Download WordPress → web/blog/, deploy naar ce.igisnext.com, run eenmalige install.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB="$ROOT/web"
BLOG="$WEB/blog"
ENV="$ROOT/.env"
INSTALL_KEY="${CE_WP_INSTALL_KEY:-ce-wp-$(openssl rand -hex 8)}"
ADMIN_USER="${CE_WP_ADMIN_USER:-erik-ce}"
ADMIN_EMAIL="${CE_WP_ADMIN_EMAIL:-erik@igisnext.com}"
API_USER="${CE_WP_API_USER:-content-engine}"

if [[ ! -f "$ENV" ]]; then
  echo "Missing $ENV" >&2
  exit 1
fi

set -a
# shellcheck disable=SC1091
source "$ENV"
set +a

for var in DB_HOST DB_NAME DB_USER DB_PASS FTP_HOST FTP_USER FTP_PASSWORD; do
  if [[ -z "${!var:-}" ]]; then
    echo "Missing $var in .env" >&2
    exit 1
  fi
done

WP_DB_NAME="${WP_DB_NAME:-$DB_NAME}"
WP_DB_USER="${WP_DB_USER:-$DB_USER}"
WP_DB_PASS="${WP_DB_PASS:-$DB_PASS}"
export WP_DB_NAME WP_DB_USER WP_DB_PASS

echo "==> Download WordPress (latest)"
TMP="$(mktemp -d)"
curl -fsSL "https://wordpress.org/latest.tar.gz" | tar -xz -C "$TMP"
rm -rf "$BLOG"
mkdir -p "$BLOG"
cp -R "$TMP/wordpress/." "$BLOG/"
rm -rf "$TMP"

echo "==> wp-config.php"
SALTS="$(curl -fsSL https://api.wordpress.org/secret-key/1.1/salt/)"
python3 - "$BLOG/wp-config.php" <<PY
from pathlib import Path
import os

out = Path("$BLOG/wp-config.php")
salts = """$SALTS"""

content = f'''<?php
define('DB_NAME', {repr(os.environ['WP_DB_NAME'])});
define('DB_USER', {repr(os.environ['WP_DB_USER'])});
define('DB_PASSWORD', {repr(os.environ['WP_DB_PASS'])});
define('DB_HOST', {repr(os.environ['DB_HOST'])});
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

\$table_prefix = 'wpce_';

{salts}

define('WP_HOME', 'https://ce.igisnext.com/blog');
define('WP_SITEURL', 'https://ce.igisnext.com/blog');
define('DISALLOW_FILE_EDIT', true);

if (!defined('ABSPATH')) {{
    define('ABSPATH', __DIR__ . '/');
}}

require_once ABSPATH . 'wp-settings.php';
'''
out.write_text(content)
print('Wrote', out)
PY

ADMIN_PASS="$(openssl rand -base64 18 | tr -d '/+=' | head -c 20)"

echo "==> ce-once-install.php"
python3 - "$BLOG/ce-once-install.php" "$INSTALL_KEY" "$ADMIN_USER" "$ADMIN_EMAIL" "$ADMIN_PASS" "$API_USER" <<'PY'
from pathlib import Path
import sys

out, key, admin_user, admin_email, admin_pass, api_user = sys.argv[1:7]
php = f'''<?php
declare(strict_types=1);

define('CE_INSTALL_KEY', {key!r});

if (($_GET['key'] ?? '') !== CE_INSTALL_KEY) {{
    http_response_code(403);
    exit('Forbidden');
}}

define('WP_INSTALLING', true);
require __DIR__ . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-includes/class-wp-application-passwords.php';

header('Content-Type: application/json; charset=utf-8');

$adminUser = {admin_user!r};
$adminEmail = {admin_email!r};
$adminPass = {admin_pass!r};
$apiUser = {api_user!r};

if (!get_option('siteurl')) {{
    wp_install('iGiSNext Blog', $adminUser, $adminEmail, true, '', $adminPass);
}}

$admin = get_user_by('login', $adminUser);
if (!$admin) {{
    $id = wp_create_user($adminUser, $adminPass, $adminEmail);
    if (is_wp_error($id)) {{
        echo json_encode(['ok' => false, 'error' => $id->get_error_message()]);
        exit;
    }}
    $admin = get_user_by('id', $id);
    $admin->set_role('administrator');
}}

$api = get_user_by('login', $apiUser);
if (!$api) {{
    $apiId = wp_create_user($apiUser, wp_generate_password(32, true, true), $adminEmail);
    if (is_wp_error($apiId)) {{
        echo json_encode(['ok' => false, 'error' => $apiId->get_error_message()]);
        exit;
    }}
    $api = get_user_by('id', $apiId);
    $api->set_role('editor');
}}

WP_Application_Passwords::delete_all_application_passwords($api->ID);
$app = WP_Application_Passwords::create_new_application_password($api->ID, ['name' => 'Content Engine']);

echo json_encode([
    'ok' => true,
    'siteUrl' => home_url('/'),
    'adminLogin' => $adminUser,
    'adminPassword' => $adminPass,
    'apiUser' => $apiUser,
    'applicationPassword' => $app[0],
    'wpAdmin' => admin_url(),
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
'''
Path(out).write_text(php)
print('Wrote', out)
PY

echo "==> Deploy blog/ (kan enkele minuten duren)"
"$ROOT/scripts/deploy-ftp.sh"

echo "==> Run install on server"
INSTALL_URL="https://ce.igisnext.com/blog/ce-once-install.php?key=${INSTALL_KEY}"
RESULT="$(curl -fsS "$INSTALL_URL")"
echo "$RESULT"

python3 - "$ENV" "$RESULT" <<'PY'
import json, re, sys
from pathlib import Path

env_path = Path(sys.argv[1])
data = json.loads(sys.argv[2])
if not data.get('ok'):
    print('Install failed:', data, file=sys.stderr)
    sys.exit(1)

app_pass = data['applicationPassword']
# WP app passwords are shown with spaces — REST expects without spaces
app_pass_clean = app_pass.replace(' ', '')

lines = env_path.read_text().splitlines()
keys = {
    'WORDPRESS_SITE_URL': data['siteUrl'].rstrip('/'),
    'WORDPRESS_USER': data['apiUser'],
    'WORDPRESS_APP_PASSWORD': app_pass_clean,
    'CE_WP_INSTALL_KEY': '',
}
out = []
seen = set()
for line in lines:
    matched = False
    for key, val in keys.items():
        if line.startswith(f'{key}='):
            if val:
                out.append(f'{key}={val}')
            seen.add(key)
            matched = True
            break
    if not matched:
        out.append(line)
for key, val in keys.items():
    if key not in seen and val:
        out.append(f'{key}={val}')
env_path.write_text('\n'.join(out) + '\n')
print('Updated .env with WORDPRESS_*')
PY

CREDS="$ROOT/.ce-wp-admin-credentials"
cat > "$CREDS" <<EOF
# Eenmalig gegenereerd — bewaar veilig, niet in git
Site:     https://ce.igisnext.com/blog
WP Admin: https://ce.igisnext.com/blog/wp-admin/
Login:    $ADMIN_USER
Password: $ADMIN_PASS
EOF
chmod 600 "$CREDS"

echo "==> Remove install script from server"
python3 - "$FTP_HOST" "$FTP_PORT" "$FTP_USER" "$FTP_PASSWORD" "${FTP_REMOTE_PATH:-/}" <<'PY'
import sys
from ftplib import FTP, error_perm
host, port, user, password, remote_root = sys.argv[1:6]
ftp = FTP()
ftp.connect(host, int(port), timeout=60)
ftp.login(user, password)
ftp.cwd(remote_root.rstrip('/') or '/')
try:
    ftp.delete('blog/ce-once-install.php')
    print('Deleted blog/ce-once-install.php')
except error_perm as e:
    print('Could not delete install script:', e)
ftp.quit()
PY

rm -f "$BLOG/ce-once-install.php"

echo ""
echo "==> Klaar"
echo "    Blog:      https://ce.igisnext.com/blog/"
echo "    WP Admin:  https://ce.igisnext.com/blog/wp-admin/"
echo "    Login:     $ADMIN_USER (wachtwoord in $CREDS)"
echo "    API user:  $API_USER (application password in .env)"
echo ""
echo "    Redeploy voor ce.local.php: ./scripts/deploy-ftp.sh"
