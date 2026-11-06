#!/usr/bin/env bash
# Generate + push CE-Sources Sync workflow naar n8n
# Usage: ./scripts/push-ce-sources-sync-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-Sources Sync.json"

python3 "$ROOT/scripts/generate-ce-sources-sync-workflow.py"
python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON"

echo "Done."
