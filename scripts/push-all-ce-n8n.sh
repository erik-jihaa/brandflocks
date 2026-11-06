#!/usr/bin/env bash
# Regenerate JSON + push alle CE flows naar n8n
# Usage: ./scripts/push-all-ce-n8n.sh [production|staging|local]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-production}"

"$ROOT/scripts/regenerate-all-ce-workflows.sh" "$TARGET"

# CE-02 en CE-Sources Sync: generators weg in ronde A — wrappers exit 1 tot herbouw.
for script in \
  push-ce01-n8n.sh \
  push-ce01b-n8n.sh \
  push-ce03-n8n.sh \
  push-ce04-n8n.sh \
  push-ce041bis-n8n.sh \
  push-ce07-n8n.sh \
  push-ce-llm-visibility-n8n.sh \
  push-ce-linkedin-sync-n8n.sh \
  push-trello-boards-n8n.sh \
  push-trello-cards-n8n.sh
do
  echo ""
  echo "==> $script ($TARGET)"
  "$ROOT/scripts/$script" "$TARGET"
done

echo ""
echo "All CE workflows pushed to n8n ($TARGET)."
if [[ "$TARGET" != "production" ]]; then
  echo ""
  echo "Webhook-URL's voor .env.$TARGET:"
  CE_N8N_TARGET="$TARGET" python3 "$ROOT/scripts/print-n8n-webhook-urls.py"
fi
