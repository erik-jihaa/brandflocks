#!/usr/bin/env bash
# Generate + push CE-02 Research workflow naar n8n
# Usage: ./scripts/push-ce02-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-02 - Research & Inspiratie.json"

python3 "$ROOT/scripts/generate-ce02-workflow.py"
python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON" "ce02-research"

echo "Done."
