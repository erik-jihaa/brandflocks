#!/usr/bin/env bash
# Maak de 3 Rogier HBA-testmerken aan op staging (tenant + brand_narrative).
# Gebruik: ./scripts/provision-hba-testmerken-staging.sh [optioneel-wachtwoord]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
INPUT_DIR="$ROOT/briefing/hba-input/HBA_testmerken"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
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

if [[ -z "$BASE" || -z "$TOKEN" ]]; then
  echo "CE_PUBLIC_BASE_URL en CE_RESET_TOKEN verplicht in $ENV_FILE" >&2
  exit 1
fi

for file in Brandpunt_Koffie.json Stelwerk.json Duinroos_Thuiszorg.json; do
  path="$INPUT_DIR/$file"
  if [[ ! -f "$path" ]]; then
    echo "Ontbreekt: $path" >&2
    exit 1
  fi
  echo "==> Provision $file"
  python3 - "$path" "$BASE" "$TOKEN" "$PASSWORD" <<'PY'
import json, sys, urllib.request

path, base, token, password = sys.argv[1:5]
bundle = json.load(open(path, encoding="utf-8"))
bundle["reuseExisting"] = True
if password:
    bundle["password"] = password

req = urllib.request.Request(
    base + "/api/admin/provision-hba-testmerk",
    data=json.dumps(bundle).encode("utf-8"),
    headers={
        "Content-Type": "application/json",
        "X-CE-Reset-Token": token,
    },
    method="POST",
)
try:
    with urllib.request.urlopen(req, timeout=120) as resp:
        out = json.load(resp)
except urllib.error.HTTPError as e:
    body = e.read().decode("utf-8", errors="replace")
    print(body, file=sys.stderr)
    raise SystemExit(1)

print(json.dumps(out, indent=2, ensure_ascii=False))
if not out.get("narrativeComplete"):
    print("WAARSCHUWING: narrative niet compleet:", out.get("missingNarrativeFields"), file=sys.stderr)
PY
done

echo "==> Klaar. Controleer /brandarchitecture per tenant op staging."
