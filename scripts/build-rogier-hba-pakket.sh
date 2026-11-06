#!/usr/bin/env bash
# Kopieer testmateriaal + meetresultaten naar docs/rogier-hba-testmerken-pakket/
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/docs/rogier-hba-testmerken-pakket"
SRC="$ROOT/briefing/hba-input/HBA_testmerken"
DATE_TAG="$(date +%Y-%m-%d)"
ZIP="$ROOT/docs/rogier-hba-testmerken-pakket-${DATE_TAG}.zip"

mkdir -p "$OUT"
cp "$SRC/LEESMIJ.md" "$OUT/LEESMIJ-testmerken.md" 2>/dev/null || true
cp "$SRC/Brandpunt_Koffie.json" "$SRC/Stelwerk.json" "$SRC/Duinroos_Thuiszorg.json" "$SRC/alle_drie_testmerken.json" "$OUT/" 2>/dev/null || true

cp "$ROOT/docs/hba-v1.8-verslag-roger.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v1.9-verslag-roger.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v1.10-verslag-roger.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/stabiliteit-v1.9.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/stabiliteit-v1.9-5runs.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/stabiliteit-v1.10.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-stability-v1.10-5runs.json" "$OUT/stabiliteit-v1.10-5runs.json" 2>/dev/null || true
cp "$ROOT/docs/hba-v1.10-stap1-ce04-post3-staging-run.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v1.10-stap2-v0-verleiding.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v1.10-stap3-v0-hints.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/briefing/hba-input/hba-ronde-5-v1.10.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-matrix-v1.9.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-resultaat.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/stabiliteit-v1.8.1.md" "$OUT/stabiliteit-v1.8.1.md" 2>/dev/null || true
cp "$ROOT/briefing/hba-input/hba-ronde-4-v1.9.md" "$OUT/hba-ronde-4-v1.9.md" 2>/dev/null || true
cp "$ROOT/docs/hba-v0-klantpreview-urls-staging.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/rogier-hba-testmerken-pakket/LEESMIJ-ROGIER-v1.9.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/rogier-hba-testmerken-pakket/LEESMIJ-ROGIER-v1.10.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-stability-v1.8.1-5runs.json" "$OUT/stabiliteit-v1.8.1-5runs.json" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-stability-v1.8-5runs.json" "$OUT/stabiliteit-v1.8-5runs.json" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-stability-v1.7-5runs.json" "$OUT/stabiliteit-v1.7-5runs.json" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-ronde3-rapport-roger.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-kalibratie-beslispunten-roger-v1.8.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-r5-klantpreview-urls-staging.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/a6-klanttaal-inventaris-voorstel.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-a8-contentrollen-relatie.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-ronde3-afronding-briefing.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v0-testresultaat.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v0-testresultaat.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v5-composer-scope.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-v181-rescore-staging.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-matrix-v1.8.1.json" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-ronde3-r0.md" "$OUT/" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-matrix-v1.3.json" "$OUT/matrix-v1.3-nulmeting.json" 2>/dev/null || true
cp "$ROOT/docs/hba-testmerken-matrix-v1.6-runB.json" "$OUT/matrix-v1.6-runB.json" 2>/dev/null || true
cp "$ROOT/web/api/prompts/hba-regelbreuk-definitie.txt" "$OUT/" 2>/dev/null || true
cp "$ROOT/web/api/prompts/hba-flags-definitie.txt" "$OUT/" 2>/dev/null || true
cp "$ROOT/web/api/prompts/hba-hoofdoordeel-definitie.txt" "$OUT/" 2>/dev/null || true
cp "$ROOT/web/api/prompts/hba-verbeteradvies-definitie.txt" "$OUT/" 2>/dev/null || true
cp "$ROOT/briefing/hba-input/cursor-briefing-ronde-3-hba-hoofdoordeel.md" "$OUT/" 2>/dev/null || true

(cd "$ROOT/docs" && rm -f "rogier-hba-testmerken-pakket-${DATE_TAG}.zip" && zip -rq "rogier-hba-testmerken-pakket-${DATE_TAG}.zip" rogier-hba-testmerken-pakket)
echo "Zip: $ZIP"
echo "Pakket bijgewerkt in $OUT"
ls -la "$OUT"
