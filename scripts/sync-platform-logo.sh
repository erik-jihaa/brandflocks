#!/usr/bin/env bash
# Sync canonical platform-logo (beige/offwhite) naar alle iGiS-subdomeinen.
# Bron: SVG/iGiSNext_Blue-main.svg — zelfde als ce.igisnext.com sidebar/login.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="$ROOT/SVG/iGiSNext_Blue-main.svg"

if [[ ! -f "$SRC" ]]; then
  echo "Bronlogo ontbreekt: $SRC" >&2
  exit 1
fi

DESTS=(
  "$ROOT/frontend/public/svg/iGiSNext_Blue-main.svg"
  "$ROOT/web/svg/iGiSNext_Blue-main.svg"
  "$ROOT/linkedin-analyse/assets/iGiSNext_Blue-main.svg"
  "$ROOT/linkedin-analyse/assets/logo.svg"
)

for dest in "${DESTS[@]}"; do
  mkdir -p "$(dirname "$dest")"
  cp "$SRC" "$dest"
  echo "  → $dest"
done

echo "Platform-logo gesynchroniseerd ($(wc -c < "$SRC" | tr -d ' ') bytes)"
