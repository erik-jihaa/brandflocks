#!/usr/bin/env bash
# Ronde 5 stap 1 — 6 verificatiegevallen × N runs (default 5).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
RUNS="${1:-5}"

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
URL="${BASE%/}/api/admin/hba-constraint-breach-verificatie-test?runs=${RUNS}"

echo "==> constraint_breach_verificatie test (${RUNS}×6): ${URL}"
curl -sS -m 900 -H "X-CE-Reset-Token: $TOKEN" "$URL" | python3 -m json.tool
