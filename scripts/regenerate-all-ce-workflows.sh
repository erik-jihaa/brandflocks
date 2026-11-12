#!/usr/bin/env bash
# Regenerate all CE n8n workflow JSON from generators + Claude briefing fixes
# Usage: ./scripts/regenerate-all-ce-workflows.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

python3 "$ROOT/scripts/generate-ce01-workflow.py"
python3 "$ROOT/scripts/generate-ce01b-workflow.py"
python3 "$ROOT/scripts/generate-ce07-workflow.py"
python3 "$ROOT/scripts/generate-ce-linkedin-sync-workflow.py"
python3 "$ROOT/scripts/generate-ce03-workflow.py"
python3 "$ROOT/scripts/generate-ce04-workflow.py"
python3 "$ROOT/scripts/generate-ce-llm-visibility-workflow.py"

echo "CE workflow JSON regenerated ($CE_N8N_TARGET)."
echo "Overgeslagen (ronde A, generators weg): CE-02, CE-Sources Sync — zie CE-Cursor."
