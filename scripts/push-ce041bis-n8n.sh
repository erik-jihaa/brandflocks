#!/usr/bin/env bash
# Push CE-04.1bis workflow naar n8n (create or update) + activate
# Usage: ./scripts/push-ce041bis-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/json/n8n/CE-04.1bis - Human Review Mail.json"

if [[ ! -f "$JSON" ]]; then
  echo "Missing workflow JSON: $JSON" >&2
  exit 1
fi

python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON" "ce04-human-review"

echo "Done."
