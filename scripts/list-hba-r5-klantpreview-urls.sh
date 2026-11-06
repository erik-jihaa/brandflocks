#!/usr/bin/env bash
# R5: URL-lijst voor klantpreview (?hbaKlant=1) per HBA-testpost (17).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"

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
APP="${BASE%/}/api/admin/hba-testmerken-matrix"

python3 - "$APP" "$TOKEN" "$BASE" <<'PY'
import json, sys, urllib.request
app, token, base = sys.argv[1:4]
req = urllib.request.Request(app, headers={"X-CE-Reset-Token": token})
with urllib.request.urlopen(req, timeout=120) as resp:
    data = json.load(resp)
for row in data.get("rows") or []:
    aid = row.get("articleId") or row.get("article_id")
    if not aid:
        continue
    merk = row.get("merk", "")
    label = row.get("label", "")
    url = f"{base.rstrip('/')}/article/{aid}?hbaKlant=1"
    print(f"{merk}\t{label}\t{url}")
PY
