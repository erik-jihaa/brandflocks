#!/usr/bin/env bash
# Cross-tenant isolatietest — Platform fase 3 #14
#
#   ./scripts/test-tenant-isolation.sh staging
#   ./scripts/test-tenant-isolation.sh production
#
# Optioneel in .env.staging:
#   CE_ISOLATION_EMAIL_A / CE_ISOLATION_PASSWORD_A
#   CE_ISOLATION_EMAIL_B / CE_ISOLATION_PASSWORD_B
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-staging}"

exec python3 "$ROOT/scripts/test-tenant-isolation.py" "$TARGET"
