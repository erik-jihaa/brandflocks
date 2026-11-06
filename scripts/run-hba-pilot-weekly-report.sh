#!/usr/bin/env bash
# Wekelijks pilotoverzicht (A2) — haalt rapport op via staging/local admin-API.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
WEEK="${1:-}"
FORMAT="${2:-md}"
OUT="${3:-$ROOT/docs/pilotstart-a2-weekly-report-$(date +%Y-%m-%d).md}"

read_env() {
  python3 - "$ENV_FILE" "$1" <<'PY'
import sys
from pathlib import Path
key = sys.argv[2]
for line in Path(sys.argv[1]).read_text().splitlines():
    line = line.strip()
    if line.startswith(key + "="):
        print(line.split("=", 1)[1].strip().strip('"').strip("'"))
        break
PY
}

BASE="$(read_env CE_PUBLIC_BASE_URL)"
TOKEN="$(read_env CE_RESET_TOKEN)"
QUERY="format=${FORMAT}"
if [[ -n "$WEEK" ]]; then
  QUERY="${QUERY}&week=${WEEK}"
fi

URL="${BASE}/api/admin/hba-pilot-weekly-report?${QUERY}"
echo "==> GET $URL"
curl -fsS -H "X-CE-Reset-Token: ${TOKEN}" "$URL" -o "$OUT"
echo "Wrote $OUT"
