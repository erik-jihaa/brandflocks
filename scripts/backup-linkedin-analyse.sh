#!/usr/bin/env bash
# Snapshot-backup van linkedin-analyse/ (code + schema + fixtures, geen secrets).
#
#   ./scripts/backup-linkedin-analyse.sh
#
# Overschrijft backups/linkedin-analyse-latest.zip (geen secrets).
# Bevat geen includes/config.local.php (productie-secrets).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/linkedin-analyse"
BACKUP_ROOT="$ROOT/backups"
ARCHIVE="$BACKUP_ROOT/linkedin-analyse-latest.zip"
WORK="$(mktemp -d "${TMPDIR:-/tmp}/li-scan-backup-XXXXXX")"
trap 'rm -rf "$WORK"' EXIT

if [[ ! -d "$TOOL" ]]; then
  echo "linkedin-analyse/ niet gevonden onder $ROOT" >&2
  exit 1
fi

mkdir -p "$BACKUP_ROOT"

echo "==> LinkedIn Scan backup (latest)"

rsync -a \
  --exclude 'includes/config.local.php' \
  --exclude 'output/' \
  --exclude '.DS_Store' \
  "$TOOL/" "$WORK/linkedin-analyse/"

# Identity-design (canonical in repo root docs/)
mkdir -p "$WORK/docs"
rsync -a \
  "$ROOT/docs/igisnext-identity.css" \
  "$ROOT/docs/igisnext-identity.md" \
  "$WORK/docs/"

# Korte manifest voor restore-context
cat > "$WORK/MANIFEST.txt" <<EOF
LinkedIn Scan backup
Created: $(date -Iseconds)
Source:  $TOOL
Archive: $(basename "$ARCHIVE")

Bevat: PHP-tool, SQL-schema, fixtures, deploy-voorbeeldconfig.
Bevat ook: docs/igisnext-identity.{css,md} (designreferentie).
Bevat NIET: includes/config.local.php (secrets), output/ (lokale testoutput).

Restore code:
  unzip $(basename "$ARCHIVE") -d /tmp/li-restore
  rsync -a /tmp/li-restore/linkedin-analyse/ ./linkedin-analyse/

Deploy na restore:
  ./scripts/deploy-linkedin-analyse.sh production

Design:   docs/igisnext-identity.md
Briefing: briefing/cursor-linkedin-scan-tool.md
          briefing/cursor-linkedin-scan-narrative.md
Build doc: linkedin-analyse/BUILD.md
EOF

(
  cd "$WORK"
  zip -rq "$ARCHIVE" MANIFEST.txt docs linkedin-analyse
)

SIZE="$(du -h "$ARCHIVE" | cut -f1)"
FILES="$(zipinfo -t "$ARCHIVE" | tail -1 | awk '{print $1}')"

echo "==> Opgeslagen: $ARCHIVE"
echo "    Grootte: $SIZE ($FILES bestanden)"
echo "==> Klaar."
