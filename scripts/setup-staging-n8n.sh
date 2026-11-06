#!/usr/bin/env bash
# Eenmalige setup: staging n8n-workflows pushen + webhook-URL's tonen voor .env.staging
#
#   ./scripts/setup-staging-n8n.sh
#
# Vereist:
#   - .env.staging met N8N_API_URL, N8N_API_KEY, N8N_WEBHOOK_SUFFIX=-staging
#   - optioneel N8N_CE_FOLDER_ID voor een DEV-map in n8n
#   - ./scripts/check-env-isolation.sh staging (geen gedeelde webhooks met productie)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

if [[ ! -f "$ROOT/.env.staging" ]]; then
  echo "Geen .env.staging — maak die eerst aan:" >&2
  echo "  cp .env.staging.example .env.staging" >&2
  exit 1
fi

echo "==> Isolatiecheck staging"
"$ROOT/scripts/check-env-isolation.sh" staging

source "$ROOT/scripts/ce-env.sh"
SUFFIX="$(CE_N8N_TARGET=staging python3 <<PY
import os, sys
sys.path.insert(0, "$ROOT/scripts")
os.environ["CE_N8N_TARGET"] = "staging"
from n8n_env import load_env, webhook_suffix
load_env("staging", override=True)
print(webhook_suffix())
PY
)"

if [[ -z "$SUFFIX" ]]; then
  echo "LET OP: N8N_WEBHOOK_SUFFIX is leeg in .env.staging." >&2
  echo "Zet N8N_WEBHOOK_SUFFIX=-staging zodat staging-workflows niet botsen met productie." >&2
  exit 1
fi

echo ""
echo "==> Push alle CE-workflows naar n8n (staging, suffix: $SUFFIX)"
"$ROOT/scripts/push-all-ce-n8n.sh" staging

echo ""
echo "==> Webhook-URL's in .env.staging zetten"
python3 "$ROOT/scripts/update-env-n8n-webhooks.py" staging

echo ""
echo "==> Volgende stap"
echo "Deploy staging zodat ce.local.php de nieuwe webhooks krijgt:"
echo "  ./scripts/deploy-ftp.sh staging"
