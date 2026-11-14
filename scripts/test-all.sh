#!/usr/bin/env bash
# Brandflocks vangnet — draai vóór elke commit tijdens opschonen/herontwerp.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

fail=0

run() {
  local label="$1"
  shift
  echo "==> $label"
  if "$@"; then
    echo "    OK"
  else
    echo "    FAIL ($label)" >&2
    fail=1
  fi
}

run "PHP syntax (web/api)" bash -c 'find web/api -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'
run "PHP syntax (scripts)" bash -c 'find scripts -name "*.php" -print0 | xargs -0 -n1 php -l >/dev/null'

run "v1.13 replay" php tests/php/hba-v113-replay.php
run "campagne plan generator" php tests/php/campagne-plan-generator.php
run "campagne slot titles" php tests/php/campagne-slot-titles.php
run "article schrijfvorm rewrite" php tests/php/article-schrijfvorm-rewrite.php
run "article CTA keuze" php tests/php/article-cta.php
run "copy-modus directive" php tests/php/copy-modus-directive.php
run "channel hard validation" php tests/php/channel-hard-validation.php
run "kanaaleisen addendum §3" php tests/php/kanaaleisen.php
run "theme-onderbouwing drempels" php tests/php/theme-onderbouwing.php
run "branchebibliotheek import" php tests/php/branche-bibliotheek-import.php
run "branche derived log" php tests/php/branche-derived-log.php

if [[ -f scripts/campagne-preview-p2-selftest.php ]]; then
  run "campagne P2 selftest" php scripts/campagne-preview-p2-selftest.php
fi
if [[ -f scripts/campagne-preview-p4-selftest.php ]]; then
  run "campagne P4 selftest" php scripts/campagne-preview-p4-selftest.php
fi

run "frontend build (tsc + vite)" bash -c 'cd frontend && npm run build'

if [[ -f frontend/package.json ]] && grep -q '"test"' frontend/package.json 2>/dev/null; then
  run "frontend unit (vitest)" bash -c 'cd frontend && npm test -- --run'
fi

if [[ -f vendor/bin/phpunit ]]; then
  run "PHPUnit" vendor/bin/phpunit --configuration phpunit.xml.dist
fi

if [[ "$fail" -ne 0 ]]; then
  echo "test-all.sh: één of meer checks gefaald" >&2
  exit 1
fi

echo "test-all.sh: alles groen"
