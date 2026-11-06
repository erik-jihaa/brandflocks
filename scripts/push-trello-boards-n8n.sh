#!/usr/bin/env bash
# Push CE-Trello Boards helper workflow naar n8n
# Usage: ./scripts/push-trello-boards-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-Trello Boards.json"

python3 "$ROOT/scripts/generate-trello-boards-workflow.py"
python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON" "ce-trello-boards"

echo "Done."
