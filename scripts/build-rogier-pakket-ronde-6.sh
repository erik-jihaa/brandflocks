#!/usr/bin/env bash
# Rogier-pakket HBA ronde 6 (pilot v1.11 + eindmeting + besluiten).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PKG="rogier-pakket-ronde-6-pilot-2026-10-19"
OUT="$ROOT/docs/$PKG"
ZIP="$ROOT/docs/${PKG}.zip"

rm -rf "$OUT"
mkdir -p "$OUT/08-stappen-1-9-samenvatting" "$OUT/06-stroomtest-screenshots" "$OUT/screenshots-a6"

cp "$ROOT/docs/rogier-pakket-ronde-6-00-LEESMIJ.md" "$OUT/00-LEESMIJ.md"
cp "$ROOT/docs/rogier-pakket-ronde-6-00-MAIL.md" "$OUT/00-MAIL.md"

cp "$ROOT/docs/hba-v1.11-stap9-pilotversie-eindmeting.md" "$OUT/01-pilotversie-eindmeting.md"
cp "$ROOT/docs/terugkoppeling-rogier-ronde-6.md" "$OUT/02-terugkoppeling-besluiten.md"
cp "$ROOT/docs/hba-v1.10-bouwrapport-2026-10-17.md" "$OUT/03-bouwrapport-2026-10-17.md"

cp "$ROOT/docs/hba-testmerken-stabiliteit.md" "$OUT/04-stabiliteit-v1.11.md"
cp "$ROOT/docs/hba-testmerken-stability-v1.11-5runs.json" "$OUT/04-stabiliteit-v1.11-5runs.json"

cp "$ROOT/docs/hba-v1.11-stap6-ijkrun-uitsplitsing.md" "$OUT/05-ijkrun-uitsplitsing.md"
cp "$ROOT/docs/hba-ijkrun-nulmeting-v1.11-samenvatting.md" "$OUT/05-ijkrun-samenvatting.md"
cp "$ROOT/docs/hba-ijkrun-nulmeting-v1.11.json" "$OUT/05-ijkrun-v1.11.json"

cp "$ROOT/docs/hba-v1.11-stap5-klanttaal.md" "$OUT/06-klanttaal-stap5.md"
cp "$ROOT/docs/hba-v1.11-stap8-stroomtest-brandpunt.md" "$OUT/06-stroomtest-brandpunt.md"
cp "$ROOT/docs/hba-brandpunt-stroomtest-staging-2026-10-18/samenvatting.md" "$OUT/06-stroomtest-samenvatting.md"
cp "$ROOT/docs/hba-brandpunt-stroomtest-staging-2026-10-18/"*.png "$OUT/06-stroomtest-screenshots/" 2>/dev/null || true

cp "$ROOT/docs/hba-v1.11-stap9-ce04-post3.json" "$OUT/07-ce04-post3-v1.11.json"
cp "$ROOT/docs/hba-v1.11-stap9-v0-testresultaat.json" "$OUT/07-v0-eindmeting.json"

for f in "$ROOT"/docs/hba-v1.11-stap*.md; do
  [[ -f "$f" ]] && cp "$f" "$OUT/08-stappen-1-9-samenvatting/"
done

cp "$ROOT/docs/hba-ijkset-koppeling-v0.3.md" "$OUT/09-ijkset-koppeling-v0.3.md"
cp "$ROOT/briefing/hba-input/hba-ronde-6-pilotklaar.md" "$OUT/10-opdracht-ronde-6.md"

cp "$ROOT/docs/a6-screenshots-staging-2026-10-18/"*.png "$OUT/screenshots-a6/" 2>/dev/null || true
cp "$ROOT/docs/a6-screenshots-staging-2026-10-18/00-LEESMIJ.md" "$OUT/screenshots-a6/" 2>/dev/null || true

(cd "$ROOT/docs" && rm -f "${PKG}.zip" && zip -rq "${PKG}.zip" "$PKG")
echo "Zip: $ZIP"
du -sh "$ZIP" "$OUT"
