#!/usr/bin/env bash
# Shared env bootstrap for n8n push/regenerate scripts.
# Usage (from another script):
#   source "$ROOT/scripts/n8n-push-common.sh" "${1:-production}"
set -euo pipefail

CE_N8N_TARGET="${1:-production}"
export CE_N8N_TARGET

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [[ "$CE_N8N_TARGET" == "production" ]]; then
  ENV_FILE="$ROOT/.env"
else
  ENV_FILE="$ROOT/.env.$CE_N8N_TARGET"
fi

if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  if [[ "$CE_N8N_TARGET" != "production" ]]; then
    echo "  cp .env.staging.example .env.$CE_N8N_TARGET" >&2
  fi
  exit 1
fi

echo "==> n8n target: $CE_N8N_TARGET ($ENV_FILE)"
