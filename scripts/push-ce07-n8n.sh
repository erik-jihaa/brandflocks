#!/usr/bin/env bash
# Generate + push CE-07 Performance Rapportage workflow naar n8n
# Usage: ./scripts/push-ce07-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-07 - Performance Rapportage.json"

python3 "$ROOT/scripts/generate-ce07-workflow.py"
python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON" "ce07-performance-report"

echo "Done."
