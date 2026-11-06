#!/usr/bin/env bash
# Upload alleen gewijzigde CE-bestanden via SFTP/FTP (zelfde als FileZilla).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WEB="$ROOT/web"
ENV="$ROOT/.env"

if [[ ! -f "$ENV" ]]; then
  echo "Missing $ENV" >&2
  exit 1
fi

echo "==> Quick upload (SFTP/FTP via curl)"
python3 "$ROOT/scripts/deploy_ftp_upload.py" "$ENV" "$WEB" quick

echo "==> Live: https://ce.igisnext.com/"
echo "    Test Theme-pagina + Genereer theme-voorstellen"
