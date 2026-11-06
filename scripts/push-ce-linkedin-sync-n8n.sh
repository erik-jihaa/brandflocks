#!/usr/bin/env bash
# Generate + push CE-LinkedIn Sync workflow naar n8n
# Usage: ./scripts/push-ce-linkedin-sync-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-LinkedIn Sync.json"

python3 "$ROOT/scripts/generate-ce-linkedin-sync-workflow.py"
python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON"

echo "Done."
