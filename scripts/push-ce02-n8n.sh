#!/usr/bin/env bash
# CE-02 naar n8n (Brandflocks app).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
export CE_N8N_TARGET="${CE_N8N_TARGET:-brandflocks}"
exec python3 "$ROOT/scripts/push-n8n-workflow.py" \
  "$ROOT/json/n8n/CE-02 - Research & Inspiratie.json" \
  ce02-research
