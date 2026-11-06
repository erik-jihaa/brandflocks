#!/usr/bin/env bash
# Zip CE-Cursor snapshot to ../ce-backup-YYYYMMDD-HHMMSS.zip (Dropbox Content Engine/)
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PARENT="$(cd "$ROOT/.." && pwd)"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$PARENT/ce-backup-${STAMP}.zip"

echo "==> Creating backup"
echo "    Source: $ROOT"
echo "    Target: $OUT"

(
  cd "$PARENT"
  zip -rq "$OUT" "$(basename "$ROOT")" \
    -x "$(basename "$ROOT")/frontend/node_modules/*" \
    -x "$(basename "$ROOT")/**/node_modules/*" \
    -x "$(basename "$ROOT")/web/blog/*" \
    -x "$(basename "$ROOT")/**/.DS_Store"
)

SIZE="$(du -h "$OUT" | cut -f1)"
echo "==> Done — $SIZE"
echo "    $OUT"
