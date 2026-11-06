#!/usr/bin/env bash
# Patch + push CE-03 Rationale & Article Writer workflow naar n8n
# Usage: ./scripts/push-ce03-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# shellcheck disable=SC1091
source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"

JSON="$ROOT/../JSON/CE-03 - Rationale & Article Writer (FIXED v2 - MySQL params).json"

python3 "$ROOT/scripts/generate-ce03-workflow.py"

if [[ ! -f "$JSON" ]]; then
  echo "Missing workflow JSON: $JSON" >&2
  exit 1
fi

python3 "$ROOT/scripts/push-n8n-workflow.py" "$JSON" "ce03-write-article"

echo "Done."
