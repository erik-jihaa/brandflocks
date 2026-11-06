#!/usr/bin/env bash
# R0: CE-04 opnieuw voor HBA-testpost 3 (alle testmerken) op staging.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
ARG="${1:-45}"
DRY=""
WAIT="45"
if [[ "$ARG" == "dry" || "$ARG" == "--dry" ]]; then
  DRY="&dry=1"
  WAIT="0"
elif [[ "$ARG" =~ ^[0-9]+$ ]]; then
  WAIT="$ARG"
else
  echo "Usage: $0 [wait_seconds|dry]" >&2
  echo "  wait_seconds  0–120, default 45 (wacht daarna op feedback in JSON)" >&2
  echo "  dry           alleen slot/article-ids, geen CE-04 trigger" >&2
  exit 1
fi

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
URL="${BASE%/}/api/admin/hba-ce04-post3?wait=${WAIT}${DRY}"

echo "==> CE-04 post 3: ${URL}"
if [[ "$WAIT" == "0" ]]; then
  curl -sS -m 600 -H "X-CE-Reset-Token: $TOKEN" "$URL" | python3 -m json.tool
  exit 0
fi

curl -sS -m 600 -H "X-CE-Reset-Token: $TOKEN" "${BASE%/}/api/admin/hba-ce04-post3?wait=0" > /dev/null
echo "==> wacht ${WAIT}s op regel-feedback (client-side poll)…"
deadline=$((SECONDS + WAIT))
while (( SECONDS < deadline )); do
  out="$(curl -sS -m 120 -H "X-CE-Reset-Token: $TOKEN" "${BASE%/}/api/admin/hba-ce04-post3?pollOnly=1")"
  if python3 - "$out" <<'PY'
import json, sys
data = json.loads(sys.argv[1])
ok = all(
    r.get("regelFeedbackOk")
    for r in data.get("results", [])
    if "slotId" in r
)
sys.exit(0 if ok else 1)
PY
  then
    echo "$out" | python3 -m json.tool
    exit 0
  fi
  sleep 5
done
echo "$out" | python3 -m json.tool
exit 1
