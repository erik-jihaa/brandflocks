#!/usr/bin/env bash
# Gedeelde helper: kies het env-bestand bij een omgeving en lees losse waarden uit.
# Bewust via Python: sommige .env-regels bevatten tekens die `source` in bash laten struikelen.
#
#   source "$(dirname "$0")/ce-env.sh"
#   ce_env_file staging          -> pad naar .env.staging (fallback .env)
#   ce_env_value "$FILE" CE_ENV  -> waarde of leeg

# Bij een expliciete omgeving is er bewust GEEN terugval op .env: dat bestand wijst naar
# productie, dus `run-migration.sh staging` zou dan migraties op productie draaien en
# `backup-db.sh staging` zou een productiedump als staging wegschrijven.
ce_env_file() {
  local root="$1" target="${2:-}"
  if [[ -z "$target" ]]; then
    printf '%s\n' "$root/.env"
    return 0
  fi

  if [[ ! -f "$root/.env.$target" ]]; then
    echo "Geen .env.$target gevonden in $root" >&2
    echo "Terugvallen op .env zou naar PRODUCTIE wijzen; maak eerst .env.$target aan:" >&2
    echo "  cp .env.staging.example .env.$target" >&2
    return 1
  fi

  printf '%s\n' "$root/.env.$target"
}

ce_env_value() {
  python3 - "$1" "$2" <<'PY'
import sys
from pathlib import Path

path, key = sys.argv[1], sys.argv[2]
value = ''
try:
    lines = Path(path).read_text().splitlines()
except OSError:
    lines = []

for line in lines:
    line = line.strip()
    if not line or line.startswith('#') or '=' not in line:
        continue
    name, raw = line.split('=', 1)
    if name.strip() != key:
        continue
    raw = raw.strip()
    if raw[:1] in ('"', "'"):
        quote, out, i = raw[0], [], 1
        while i < len(raw):
            if raw[i] == '\\' and i + 1 < len(raw):
                out.append(raw[i + 1])
                i += 2
                continue
            if raw[i] == quote:
                break
            out.append(raw[i])
            i += 1
        value = ''.join(out)
    else:
        value = raw.split(' #', 1)[0].strip()

print(value)
PY
}
