#!/usr/bin/env bash
# Load n8n credentials from project .env (never commit .env)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
if [[ ! -f "$ROOT/.env" ]]; then
  echo "Missing $ROOT/.env" >&2
  exit 1
fi
set -a
# shellcheck disable=SC1091
source "$ROOT/.env"
set +a
if [[ -z "${N8N_API_KEY:-}" || "$N8N_API_KEY" == "PLAK-JE-KEY-HIER" || "$N8N_API_KEY" == "plak-hier-je-n8n-api-key" ]]; then
  echo "N8N_API_KEY in .env is still a placeholder. Save your real key in .env first." >&2
  exit 1
fi
exec curl -s -H "X-N8N-API-KEY: $N8N_API_KEY" "${N8N_API_URL%/}$1" "${@:2}"
