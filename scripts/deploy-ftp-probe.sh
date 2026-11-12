#!/usr/bin/env bash
# Test welke FTP/SFTP-modus werkt en toon remote map.
#
#   ./scripts/deploy-ftp-probe.sh              # .env (productie)
#   ./scripts/deploy-ftp-probe.sh staging      # .env.staging
#   ./scripts/deploy-ftp-probe.sh brandflocks-target  # .env.brandflocks-target
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-}"
ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")" || exit 1
if [[ -z "$ENV_FILE" || ! -f "$ENV_FILE" ]]; then
  echo "Missing ${ENV_FILE:-.env}" >&2
  exit 1
fi

echo "==> FTP-probe — $(basename "$ENV_FILE")"
python3 "$ROOT/scripts/deploy_ftp_upload.py" "$ENV_FILE" "$ROOT/web" full --probe-only
