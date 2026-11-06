#!/usr/bin/env bash
# Tony's Chocolonely referentiemerk: tenant + markthygiëne + 10 testposts (nulmeting).
# Gebruik: ./scripts/provision-tonys-referentiemerk-staging.sh [wachtwoord]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
JSON="$ROOT/briefing/hba-input/Tonys_Chocolonely_referentiemerk-2.json"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi
if [[ ! -f "$JSON" ]]; then
  echo "Missing $JSON" >&2
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
PASSWORD="${1:-}"

python3 - "$JSON" "$BASE" "$TOKEN" "$PASSWORD" <<'PY'
import json, sys, urllib.request

path, base, token, password = sys.argv[1:5]
bundle = json.load(open(path, encoding="utf-8"))
bundle["reuseExisting"] = True
if password:
    bundle["password"] = password


def post(payload):
    req = urllib.request.Request(
        base + "/api/admin/provision-hba-testmerk",
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json", "X-CE-Reset-Token": token},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            return json.load(resp)
    except urllib.error.HTTPError as e:
        print(e.read().decode("utf-8", errors="replace"), file=sys.stderr)
        raise SystemExit(1)


print("==> Provision referentiemerk (tenant + narrative + branche)")
prov = post(bundle)
print(json.dumps(prov, indent=2, ensure_ascii=False))
client_id = prov["clientId"]
count = int(prov.get("testpostsCount") or 0)

print("==> Import testposts (defer score)")
imp = post({**bundle, "clientId": client_id, "deferScore": True})
print(json.dumps(imp, indent=2, ensure_ascii=False))
imported = int(imp.get("importedCount") or count)

print("==> Score per post")
for index in range(1, imported + 1):
    scored = post({"referentiemerk": bundle.get("referentiemerk"), "clientId": client_id, "scoreIndex": index})
    print(f"  post {index}: ok={scored.get('ok')} prompt={scored.get('promptVersion')}")

print("==> Klaar. clientId:", client_id)
PY
