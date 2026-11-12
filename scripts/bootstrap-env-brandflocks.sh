#!/usr/bin/env bash
# Bouwt .env.brandflocks uit:
#   - .env.brandflocks.example (structuur + Brandflocks-host defaults)
#   - .env.staging (platform-API's — geen FTP/DB/URL van staging)
#   - optioneel .env.brandflocks.antagonist-secrets (FTP/DB-wachtwoorden uit DirectAdmin)
#
#   cp .env.brandflocks.antagonist-secrets.example .env.brandflocks.antagonist-secrets
#   # vul FTP_PASSWORD, DB_PASS, eventueel DB_NAME/USER af
#   ./scripts/bootstrap-env-brandflocks.sh
#   ./scripts/check-env-isolation.sh brandflocks
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

python3 << 'PY'
from __future__ import annotations

import os
import secrets
from pathlib import Path

ROOT = Path(".")

def parse_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    if not path.is_file():
        return out
    for raw in path.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        key = key.strip()
        val = val.strip()
        if len(val) >= 2 and val[0] == val[-1] == '"':
            val = val[1:-1]
        out[key] = val
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

example = parse_env(ROOT / ".env.brandflocks.example")
staging = parse_env(ROOT / ".env.staging")
antagonist = parse_env(ROOT / ".env.brandflocks.antagonist-secrets")

# Host-profiel Antagonist Plus (brandflocks.nl)
HOST_DEFAULTS = {
    "FTP_HOST": "ftp.brandflocks.nl",
    "FTP_PORT": "21",
    "FTP_PROTOCOL": "ftp",
    "FTP_REMOTE_PATH": "/",
    "FTP_USER": "erik@brandflocks.nl",
    "CE_PUBLIC_BASE_URL": "https://app.brandflocks.nl",
    "CE_ALLOWED_ORIGINS": "https://app.brandflocks.nl,http://localhost:5173",
    "CE_MAIL_FROM": "Brandflocks <erik@brandflocks.nl>",
    "CE_MAIL_ENVELOPE": "erik@brandflocks.nl",
    "CE_SUPPORT_EMAIL": "support@brandflocks.nl",
    "CE_REVIEW_EMAIL": "erik@brandflocks.nl",
    "DB_HOST": "localhost",
    "DB_PORT": "3306",
    "DB_NAME": "brandflocks_ce",
    "DB_USER": "brandflocks_ce",
    "N8N_WEBHOOK_SUFFIX": "-brandflocks",
    "N8N_MYSQL_CREDENTIAL_NAME": "Brandflocks [brandflocks]",
}

STAGING_BLOCK = {
    "FTP_HOST", "FTP_PORT", "FTP_USER", "FTP_PASSWORD", "FTP_PASS", "FTP_PROTOCOL", "FTP_REMOTE_PATH",
    "DB_HOST", "DB_PORT", "DB_NAME", "DB_USER", "DB_PASS",
    "CE_PUBLIC_BASE_URL", "CE_API_BASE_URL", "CE_HEALTH_EXPECT_ENV", "CE_ALLOWED_ORIGINS",
    "CE_ENV", "CE_RESET_TOKEN", "CE_CREDENTIAL_KEY", "N8N_WEBHOOK_SUFFIX",
    "CE_SUPPORT_EMAIL", "CE_REVIEW_EMAIL", "CE_PILOT_ALERT_EMAILS", "CE_MAIL_FROM", "CE_MAIL_ENVELOPE", "CE_DOCS_URL",
}

out = dict(example)
out.update(HOST_DEFAULTS)
out.update(antagonist)

for key, val in staging.items():
    if key in STAGING_BLOCK:
        continue
    if val:
        out[key] = val

out["CE_ENV"] = "brandflocks"
out["CE_PRODUCT_NAME"] = "Brandflocks"
out["CE_STAGING_LABEL"] = "Brandflocks"
out["CE_PLATFORM_LABEL"] = "Brandflocks platform"

for token_key in ("CE_RESET_TOKEN", "CE_CREDENTIAL_KEY"):
    if not out.get(token_key):
        out[token_key] = secrets.token_urlsafe(32)

out.pop("CE_API_BASE_URL", None)
out.pop("CE_HEALTH_EXPECT_ENV", None)

header = [
    "# .env.brandflocks — gegenereerd door scripts/bootstrap-env-brandflocks.sh",
    "# Niet committen. Host: Antagonist Plus (brandflocks.nl).",
    "#",
    "#   ./scripts/check-env-isolation.sh brandflocks",
    "#   ./scripts/deploy-ftp-probe.sh brandflocks",
    "#   ./scripts/deploy-ftp.sh brandflocks",
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

target = ROOT / ".env.brandflocks"
write_env(target, header, out, key_order)

missing = [k for k in ("FTP_PASSWORD", "DB_PASS") if not out.get(k)]
if missing:
    print("Geschreven:", target)
    print("LET OP: nog leeg — vul .env.brandflocks.antagonist-secrets en draai opnieuw:", ", ".join(missing))
else:
    print("Geschreven:", target, "(FTP + DB compleet)")
PY
