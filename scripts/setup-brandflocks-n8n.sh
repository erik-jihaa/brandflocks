#!/usr/bin/env bash
# BrandFlocks n8n: kopie CE-workflows met eigen webhooks voor app + Bf-staging.
#
#   ./scripts/setup-brandflocks-n8n.sh              # beide omgevingen
#   ./scripts/setup-brandflocks-n8n.sh app          # alleen .env.brandflocks → app.brandflocks.nl
#   ./scripts/setup-brandflocks-n8n.sh staging      # alleen .env.brandflocks-target
#
# Vereist per env: N8N_API_URL, N8N_API_KEY, N8N_WEBHOOK_SUFFIX, CE_PUBLIC_BASE_URL
# Optioneel: N8N_MYSQL_CREDENTIAL_ID + N8N_MYSQL_CREDENTIAL_NAME (eigen DB per domein)
#
# n8n Cloud: geen extra team-project mogelijk — workflows komen in project JiHaa-flow
# met naam-label [Bf app] / [Bf staging] en webhook-pad …-brandflocks / …-brandflocks-staging.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

python3 "$ROOT/scripts/ensure-n8n-bf-folder.py" brandflocks brandflocks-target

push_target() {
  local name="$1"
  if [[ ! -f "$ROOT/.env.$name" ]]; then
    echo "Ontbreekt .env.$name" >&2
    return 1
  fi
  echo ""
  echo "########################################"
  echo "# n8n → $name"
  echo "########################################"
  "$ROOT/scripts/push-all-ce-n8n.sh" "$name"
  python3 "$ROOT/scripts/update-env-n8n-webhooks.py" "$name"
}

MODE="${1:-both}"

case "$MODE" in
  both)
    push_target brandflocks-target
    push_target brandflocks
    ;;
  app|brandflocks)
    push_target brandflocks
    ;;
  staging|brandflocks-target)
    push_target brandflocks-target
    ;;
  *)
    echo "Usage: $0 [both|app|staging]" >&2
    exit 1
    ;;
esac

echo ""
echo "==> Klaar. Sync webhooks naar de server:"
echo "  ./scripts/deploy-ftp.sh brandflocks          # app.brandflocks.nl"
echo "  ./scripts/deploy-ftp.sh brandflocks-target   # staging.brandflocks.nl"
echo ""
echo "MySQL in n8n: maak per omgeving een credential (Antagonist app-DB vs Bf-staging-DB)."
echo "Zet N8N_MYSQL_CREDENTIAL_ID in .env.brandflocks en .env.brandflocks-target."
