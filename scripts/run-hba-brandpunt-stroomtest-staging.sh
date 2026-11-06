#!/usr/bin/env bash
# Brandpunt stroomtest (ronde 6 stap 8) op staging via admin API.
# Vereist: deploy van web/api/hba-stroomtest-* + index.php naar staging.
#
# Gebruik:
#   ./scripts/run-hba-brandpunt-stroomtest-staging.sh              # volledige pipeline (25 posts, 5 checkpoints)
#   ./scripts/run-hba-brandpunt-stroomtest-staging.sh onboarding   # alleen poging 1 + 2
#   ./scripts/run-hba-brandpunt-stroomtest-staging.sh checkpoint 15
#
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
STROOM_JSON="$ROOT/briefing/hba-input/Brandpunt_stroomtest_25.json"
KOFFIE_JSON="$ROOT/briefing/hba-input/HBA_testmerken/Brandpunt_Koffie.json"
OUT_DIR="$ROOT/docs/hba-brandpunt-stroomtest-staging-$(date +%Y-%m-%d)"
MODE="${1:-full}"

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi
for f in "$STROOM_JSON" "$KOFFIE_JSON"; do
  if [[ ! -f "$f" ]]; then
    echo "Missing $f" >&2
    exit 1
  fi
done

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
mkdir -p "$OUT_DIR"

post_api() {
  local phase="$1"
  local extra="${2:-\{\}}"
  python3 - "$STROOM_JSON" "$KOFFIE_JSON" "$BASE" "$TOKEN" "$phase" "$extra" <<'PY'
import json, sys, urllib.request

stroom_path, koffie_path, base, token, phase, extra_json = sys.argv[1:7]
bundle = json.load(open(stroom_path, encoding="utf-8"))
koffie = json.load(open(koffie_path, encoding="utf-8"))
markt = koffie.get("markthygiene") or {}
extra = json.loads(extra_json) if extra_json.strip() else {}

payload = {
    "phase": phase,
    "bundle": bundle,
    "markthygiene": markt,
    "reuseExisting": True,
    **extra,
}

req = urllib.request.Request(
    base.rstrip("/") + "/api/admin/hba-stroomtest-brandpunt",
    data=json.dumps(payload).encode("utf-8"),
    headers={
        "Content-Type": "application/json",
        "X-CE-Reset-Token": token,
    },
    method="POST",
)
with urllib.request.urlopen(req, timeout=3600) as resp:
    body = resp.read().decode("utf-8")
print(body)
PY
}

echo "==> Output: $OUT_DIR"

case "$MODE" in
  full)
    echo "==> full_pipeline (onboarding + 25 posts + checkpoints)"
    post_api full_pipeline '{}' | tee "$OUT_DIR/full-pipeline.json" | python3 -m json.tool | head -80
    ;;
  onboarding)
    echo "==> ensure_client + poging 1 + 2"
    ensure="$(post_api ensure_client '{}')"
    echo "$ensure" | tee "$OUT_DIR/ensure-client.json" | python3 -m json.tool
    cid="$(echo "$ensure" | python3 -c "import json,sys; print(json.load(sys.stdin)['clientId'])")"
    post_api onboarding_poging_1 "{\"clientId\":\"$cid\"}" | tee "$OUT_DIR/onboarding-poging-1.json" | python3 -m json.tool
    post_api onboarding_poging_2 "{\"clientId\":\"$cid\"}" | tee "$OUT_DIR/onboarding-poging-2.json" | python3 -m json.tool
    ;;
  checkpoint)
    nr="${2:-10}"
    ensure="$(post_api ensure_client '{}')"
    cid="$(echo "$ensure" | python3 -c "import json,sys; print(json.load(sys.stdin)['clientId'])")"
    post_api import_posts "{\"clientId\":\"$cid\",\"throughPost\":$nr}" | tee "$OUT_DIR/import-through-$nr.json" >/dev/null
    post_api checkpoint "{\"clientId\":\"$cid\",\"afterPost\":$nr}" | tee "$OUT_DIR/checkpoint-$nr.json" | python3 -m json.tool | head -60
    ;;
  *)
    echo "Modi: full | onboarding | checkpoint <10|15|20|21|25>" >&2
    exit 1
    ;;
esac

echo "Klaar. Screenshots Overzicht/Rapport: login als Brandpunt Stroomtest-tenant, vastleggen in $OUT_DIR/"
