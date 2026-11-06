#!/usr/bin/env bash
# Generate + push CE-01b Theme naar Slot workflow naar n8n
# Usage: ./scripts/push-ce01b-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-01b - Theme naar Slot.json"

python3 "$ROOT/scripts/generate-ce01b-workflow.py"
python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON" "ce01b-theme-to-slot"

echo "Done."
