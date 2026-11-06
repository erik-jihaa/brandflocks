#!/usr/bin/env bash
# HBA ronde 6 stap 9: eindmeting op staging (v1.11 pilot).
# Ijkrun: apart draaien (stap 6) — output docs/hba-ijkrun-nulmeting-v1.11.json
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> 5×19 testmerken (lang, ~40+ min)"
./scripts/run-hba-testmerken-matrix.sh --runs 5 2>&1 | tee "$ROOT/docs/hba-v1.11-stap9-5x19-run.log"

echo "==> CE-04 post 3"
./scripts/run-hba-r0-ce04-post3-staging.sh 90 2>&1 | tee "$ROOT/docs/hba-v1.11-stap9-ce04-post3.json"

echo "==> V0 (CE-03 + self_review + hints)"
python3 "$ROOT/scripts/run-hba-v0-staging.py" 2>&1 | tee "$ROOT/docs/hba-v1.11-stap9-v0-run.log"
cp -f "$ROOT/docs/hba-v0-testresultaat.json" "$ROOT/docs/hba-v1.11-stap9-v0-testresultaat.json"

echo "==> Klaar. Samenvatting: docs/hba-v1.11-stap9-pilotversie-eindmeting.md"
echo "    Ijkrun (hergebruik): docs/hba-v1.11-stap6-ijkrun-uitsplitsing.md"
