#!/usr/bin/env bash
# Dagelijkse onderhoudstaken voor één omgeving (backup + event_log retentie).
# Bedoeld voor cron — zie scripts/install-cron.sh.
#
#   ./scripts/cron-daily.sh [staging|production]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET_ENV="${1:-production}"
LOG_DIR="${CE_CRON_LOG_DIR:-$HOME/ce-cron-logs}"
STAMP="$(date +%Y%m%d-%H%M%S)"

mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/ce-cron-${TARGET_ENV}-${STAMP}.log"

{
  echo "==> CE cron daily — $TARGET_ENV — $(date '+%Y-%m-%d %H:%M:%S %Z')"
  echo
  "$ROOT/scripts/backup-db.sh" "$TARGET_ENV"
  echo
  "$ROOT/scripts/prune-event-log.sh" "$TARGET_ENV"
  echo
  "$ROOT/scripts/prune-support-service.sh" "$TARGET_ENV" || echo "WARN: support-chat retentie mislukt (niet fataal)"
  echo
  "$ROOT/scripts/poll-n8n-failures.sh" "$TARGET_ENV" || echo "WARN: n8n poll mislukt (niet fataal)"
  echo
  echo "==> Klaar — $(date '+%Y-%m-%d %H:%M:%S %Z')"
} 2>&1 | tee "$LOG_FILE"

# Bewaar laatste 30 logbestanden per omgeving.
ls -1t "$LOG_DIR"/ce-cron-"${TARGET_ENV}"-*.log 2>/dev/null | tail -n +31 | while read -r old; do
  [[ -n "$old" ]] && rm -f "$old"
done
