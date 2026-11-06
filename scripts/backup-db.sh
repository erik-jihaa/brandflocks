#!/usr/bin/env bash
# Dagelijkse database-backup met retentie.
#
#   ./scripts/backup-db.sh                  # .env (productie)
#   ./scripts/backup-db.sh staging
#
# Retentie: 14 dagelijkse + 8 wekelijkse (maandag) dumps.
# Bestemming staat buiten het cPanel-account: een backup op dezelfde server helpt
# niet bij accountverlies of ransomware. Standaard de Dropbox-map naast het project;
# overschrijf met CE_BACKUP_DIR in .env.
#
# Bedoeld voor cron, bijvoorbeeld dagelijks 10:00:
#   0 10 * * * cd /pad/naar/CE-Cursor && ./scripts/cron-daily.sh production >> ~/ce-cron-logs/ce-cron-production.log 2>&1
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV="${1:-}"
ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi

BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
BASE_URL="${BASE_URL:-https://ce.igisnext.com}"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"
CE_ENV_NAME="$(ce_env_value "$ENV_FILE" CE_ENV)"
CE_ENV_NAME="${TARGET_ENV:-${CE_ENV_NAME:-production}}"

BACKUP_DIR="$(ce_env_value "$ENV_FILE" CE_BACKUP_DIR)"
if [[ -z "$BACKUP_DIR" ]]; then
  BACKUP_DIR="$(cd "$ROOT/.." && pwd)/ce-db-backups"
fi

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

DAILY_KEEP=14
WEEKLY_KEEP=8

mkdir -p "$BACKUP_DIR/daily" "$BACKUP_DIR/weekly"

STAMP="$(date +%Y%m%d-%H%M%S)"
TMP="$(mktemp "${TMPDIR:-/tmp}/ce-dump-XXXXXX.sql")"
trap 'rm -f "$TMP"' EXIT

echo "==> Dump ophalen ($CE_ENV_NAME) van $BASE_URL"
if ! curl -fsS --max-time 900 \
  -H "X-CE-Reset-Token: $TOKEN" \
  "$BASE_URL/api/admin/db-dump" \
  -o "$TMP"; then
  echo "FOUT: dump ophalen mislukt" >&2
  exit 1
fi

# Een afgebroken download levert een half bestand op. De dump eindigt altijd met
# een samenvattingsregel, dus dat is de goedkoopste integriteitscheck.
if ! grep -q '^-- Samenvatting:' "$TMP"; then
  echo "FOUT: dump lijkt incompleet (geen samenvattingsregel) — niet opgeslagen" >&2
  head -n 5 "$TMP" >&2
  exit 1
fi

TABLES="$(grep -c '^CREATE TABLE' "$TMP" || true)"
if [[ "$TABLES" -lt 10 ]]; then
  echo "FOUT: dump bevat maar $TABLES tabellen — verwacht 20+ — niet opgeslagen" >&2
  exit 1
fi

DAILY_FILE="$BACKUP_DIR/daily/ce-${CE_ENV_NAME}-${STAMP}.sql.gz"
gzip -c "$TMP" > "$DAILY_FILE"
SIZE="$(du -h "$DAILY_FILE" | cut -f1)"
echo "==> Opgeslagen: $DAILY_FILE ($SIZE, $TABLES tabellen)"

# Maandag ook als weekbackup bewaren.
if [[ "$(date +%u)" == "1" ]]; then
  WEEKLY_FILE="$BACKUP_DIR/weekly/ce-${CE_ENV_NAME}-$(date +%Y%m%d).sql.gz"
  cp "$DAILY_FILE" "$WEEKLY_FILE"
  echo "==> Weekbackup: $WEEKLY_FILE"
fi

prune() {
  local dir="$1" keep="$2" label="$3"
  local removed=0
  # shellcheck disable=SC2012
  while IFS= read -r old; do
    [[ -z "$old" ]] && continue
    rm -f "$dir/$old"
    removed=$((removed + 1))
  done < <(ls -1t "$dir" 2>/dev/null | tail -n "+$((keep + 1))")
  if [[ "$removed" -gt 0 ]]; then
    echo "==> Retentie $label: $removed verwijderd (bewaar laatste $keep)"
  fi
}

prune "$BACKUP_DIR/daily" "$DAILY_KEEP" "dagelijks"
prune "$BACKUP_DIR/weekly" "$WEEKLY_KEEP" "wekelijks"

echo "==> Klaar. Test één keer een restore: ./scripts/restore-db.sh $DAILY_FILE"
