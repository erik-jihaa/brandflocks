#!/usr/bin/env bash
# Import HBA-testposts per testmerk (JSON) naar bestaande staging-tenant (19 totaal: 6+6+7).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
INPUT_DIR="$ROOT/briefing/hba-input/HBA_testmerken"

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

for file in Brandpunt_Koffie.json Stelwerk.json Duinroos_Thuiszorg.json; do
  echo "==> Import testposts from $file"
  python3 - "$INPUT_DIR/$file" "$BASE" "$TOKEN" <<'PY'
import json, sys, urllib.request

path, base, token = sys.argv[1:4]
bundle = json.load(open(path, encoding="utf-8"))


def post(payload):
    req = urllib.request.Request(
        base + "/api/admin/import-hba-testposts",
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json", "X-CE-Reset-Token": token},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=600) as resp:
        return json.load(resp)


# Server-timeout ~90 s: eerst importeren zonder score, dan één post per verzoek scoren.
imported = post({**bundle, "deferScore": True})
print(f"  geïmporteerd: {imported.get('importedCount')} (verwijderd: {imported.get('removed')})")
for index in range(1, int(imported.get("importedCount") or 0) + 1):
    result = post({"testmerk": bundle.get("testmerk"), "clientId": imported["clientId"], "scoreIndex": index})
    print(f"  post {index} gescoord ({result.get('promptVersion')})")
PY
done

echo "==> Klaar. Posts = nulmeting (gescoord) + Archief. Agenda toont ze niet."
