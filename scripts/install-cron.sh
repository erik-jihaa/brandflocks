#!/usr/bin/env bash
# Installeer dagelijkse cron voor CE backup + event_log retentie.
#
#   ./scripts/install-cron.sh [staging|production] [--schedule "30 3 * * *"] [--yes]
#
# Draait op deze machine (Mac): backup haalt dump via HTTPS op; Dropbox-backupmap
# staat buiten cPanel. Mac moet 's nachts aan staan of cron mist een run.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

TARGET_ENV="${1:-production}"
SCHEDULE="0 10 * * *"
ASSUME_YES=0

shift 2>/dev/null || true
while [[ $# -gt 0 ]]; do
  case "$1" in
    --schedule) SCHEDULE="$2"; shift 2 ;;
    --yes|-y) ASSUME_YES=1; shift ;;
    staging|production) TARGET_ENV="$1"; shift ;;
    *) echo "Onbekende optie: $1" >&2; exit 1 ;;
  esac
done

if [[ "$TARGET_ENV" != "staging" && "$TARGET_ENV" != "production" ]]; then
  echo "Usage: $0 [staging|production] [--schedule \"30 3 * * *\"] [--yes]" >&2
  exit 1
fi

CRON_CMD="cd $(printf '%q' "$ROOT") && ./scripts/cron-daily.sh $(printf '%q' "$TARGET_ENV") >> $(printf '%q' "${CE_CRON_LOG_DIR:-$HOME/ce-cron-logs}")/ce-cron-${TARGET_ENV}.log 2>&1"
CRON_LINE="$SCHEDULE $CRON_CMD"
MARK_BEGIN="# BEGIN CE-Cursor ($TARGET_ENV)"
MARK_END="# END CE-Cursor ($TARGET_ENV)"

echo "Cron-regel ($TARGET_ENV):"
echo "  $CRON_LINE"
echo
echo "Log: ${CE_CRON_LOG_DIR:-$HOME/ce-cron-logs}/ce-cron-${TARGET_ENV}.log"
echo

if [[ "$ASSUME_YES" != "1" ]]; then
  read -r -p "Installeren in crontab van $(whoami)? [y/N] " confirm
  if [[ "$confirm" != "y" && "$confirm" != "Y" && "$confirm" != "ja" ]]; then
    echo "Geannuleerd."
    exit 0
  fi
fi

mkdir -p "${CE_CRON_LOG_DIR:-$HOME/ce-cron-logs}"

TMP="$(mktemp)"
{
  crontab -l 2>/dev/null || true
} | awk -v begin="$MARK_BEGIN" -v end="$MARK_END" '
  $0 == begin { skip=1; next }
  $0 == end { skip=0; next }
  skip { next }
  { print }
' > "$TMP"

{
  echo "$MARK_BEGIN"
  echo "$CRON_LINE"
  echo "$MARK_END"
} >> "$TMP"

crontab "$TMP"
rm -f "$TMP"

echo "Geïnstalleerd. Controleer met: crontab -l"
