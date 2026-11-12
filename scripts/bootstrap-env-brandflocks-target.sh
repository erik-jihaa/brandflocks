#!/usr/bin/env bash
# Bouwt .env.brandflocks-target (Bf-staging op Antagonist, naast app.brandflocks.nl).
#
#   cp .env.brandflocks-target.antagonist-secrets.example .env.brandflocks-target.antagonist-secrets
#   ./scripts/bootstrap-env-brandflocks-target.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

if [[ ! -f .env.brandflocks ]]; then
  echo "Eerst .env.brandflocks invullen (app)." >&2
  exit 1
fi

python3 << 'PY'
from __future__ import annotations

import secrets
from pathlib import Path

ROOT = Path(".")

def parse(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    if not path.is_file():
        return out
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        val = val.strip()
        if len(val) >= 2 and val[0] == val[-1] == '"':
            val = val[1:-1]
        out[key.strip()] = val
    return out

def write_env(path: Path, header: list[str], values: dict[str, str], key_order: list[str]) -> None:
    lines = header + [""]
    seen: set[str] = set()
    for key in key_order:
        if key in values:
            lines.append(f"{key}={values[key]}")
            seen.add(key)
    for key in sorted(values):
        if key not in seen:
            lines.append(f"{key}={values[key]}")
    path.write_text("\n".join(lines) + "\n", encoding="utf-8")

app = parse(ROOT / ".env.brandflocks")
secrets_file = parse(ROOT / ".env.brandflocks-target.antagonist-secrets")
base = parse(ROOT / ".env.brandflocks-target.example")

STAGING_DEFAULTS = {
    "CE_ENV": "staging",
    "CE_PRODUCT_NAME": "Brandflocks",
    "CE_STAGING_LABEL": "Brandflocks staging",
    "CE_PLATFORM_LABEL": "Brandflocks platform",
    "FTP_HOST": "ftp.brandflocks.nl",
    "FTP_PORT": "21",
    "FTP_PROTOCOL": "ftp",
    "FTP_REMOTE_PATH": "/",
    "FTP_USER": "staging@brandflocks.nl",
    "CE_PUBLIC_BASE_URL": "https://staging.brandflocks.nl",
    "CE_ALLOWED_ORIGINS": "https://staging.brandflocks.nl,http://localhost:5173",
    "CE_MAIL_FROM": "Brandflocks staging <noreply@brandflocks.nl>",
    "DB_HOST": "localhost",
    "DB_PORT": "3306",
    "DB_NAME": "deb2003839n1_brandflocks_staging",
    "DB_USER": "deb2003839n1_brandflocks_staging",
    "N8N_WEBHOOK_SUFFIX": "-brandflocks-staging",
    "N8N_MYSQL_CREDENTIAL_NAME": "Brandflocks [Bf staging]",
}

APP_BLOCK = {
    "FTP_HOST", "FTP_PORT", "FTP_USER", "FTP_PASSWORD", "FTP_PASS", "FTP_PROTOCOL", "FTP_REMOTE_PATH",
    "DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS",
    "CE_PUBLIC_BASE_URL", "CE_API_BASE_URL", "CE_HEALTH_EXPECT_ENV", "CE_ALLOWED_ORIGINS",
    "CE_ENV", "CE_RESET_TOKEN", "CE_CREDENTIAL_KEY", "CE_MAIL_FROM",
    "N8N_WEBHOOK_SUFFIX", "N8N_MYSQL_CREDENTIAL_ID", "N8N_MYSQL_CREDENTIAL_NAME",
}

out = dict(base)
out.update(STAGING_DEFAULTS)
for key, val in app.items():
    if key in APP_BLOCK:
        continue
    if val:
        out[key] = val
out.update(secrets_file)

out.pop("CE_API_BASE_URL", None)
out.pop("CE_HEALTH_EXPECT_ENV", None)
if not secrets_file.get("N8N_MYSQL_CREDENTIAL_ID"):
    out.pop("N8N_MYSQL_CREDENTIAL_ID", None)

for key in ("CE_RESET_TOKEN", "CE_CREDENTIAL_KEY"):
    if not secrets_file.get(key):
        out[key] = secrets.token_urlsafe(32)

header = [
    "# .env.brandflocks-target — Bf-staging op Antagonist (staging.brandflocks.nl)",
    "# Gegenereerd door scripts/bootstrap-env-brandflocks-target.sh",
    "#",
    "#   ./scripts/check-env-isolation.sh brandflocks-target",
    "#   ./scripts/deploy-ftp-probe.sh brandflocks-target",
    "#   ./scripts/deploy-ftp.sh brandflocks-target",
]

key_order = [
    "CE_ENV", "CE_PRODUCT_NAME", "CE_STAGING_LABEL", "CE_PLATFORM_LABEL",
    "FTP_HOST", "FTP_PORT", "FTP_USER", "FTP_PASSWORD", "FTP_PROTOCOL", "FTP_REMOTE_PATH",
    "DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS",
    "CE_PUBLIC_BASE_URL", "CE_ALLOWED_ORIGINS",
    "CE_SUPPORT_EMAIL", "CE_REVIEW_EMAIL", "CE_PILOT_ALERT_EMAILS", "CE_MAIL_FROM",
    "CE_RESET_TOKEN", "CE_CREDENTIAL_KEY",
    "N8N_API_URL", "N8N_API_KEY", "N8N_WEBHOOK_SUFFIX",
]

target = ROOT / ".env.brandflocks-target"
write_env(target, header, out, key_order)

missing = [k for k in ("FTP_PASSWORD", "DB_PASS") if not out.get(k)]
print("Geschreven:", target)
if missing:
    print("LET OP: vul .env.brandflocks-target.antagonist-secrets:", ", ".join(missing))
PY
