#!/usr/bin/env bash
# Export productie-schema naar docs/schema.sql (draait op server via admin API, geen remote mysqldump).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/docs/schema.sql"

if [[ ! -f "$ROOT/.env" ]]; then
  echo "Missing $ROOT/.env" >&2
  exit 1
fi

read -r BASE TOKEN <<<"$(python3 - "$ROOT/.env" <<'PY'
import sys
from pathlib import Path

def parse_env_value(raw: str) -> str:
    raw = raw.strip()
    if not raw:
        return ""
    if raw[0] in ('"', "'"):
        q = raw[0]
        i = 1
        out = []
        while i < len(raw):
            if raw[i] == "\\" and i + 1 < len(raw):
                out.append(raw[i + 1])
                i += 2
                continue
            if raw[i] == q:
                break
            out.append(raw[i])
            i += 1
        return "".join(out)
    if " #" in raw:
        raw = raw.split(" #", 1)[0]
    return raw.strip()

base = "https://ce.igisnext.com"
token = ""
for line in Path(sys.argv[1]).read_text().splitlines():
    line = line.strip()
    if not line or line.startswith("#") or "=" not in line:
        continue
    key, val = line.split("=", 1)
    key = key.strip()
    if key == "CE_PUBLIC_BASE_URL":
        parsed = parse_env_value(val)
        if parsed:
            base = parsed.rstrip("/")
    elif key == "CE_RESET_TOKEN":
        parsed = parse_env_value(val)
        if parsed:
            token = parsed

print(base, token)
PY
)"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN not set in .env" >&2
  exit 1
fi

echo "==> Init schema_migrations (retroactive, idempotent)"
curl -fsS -X POST \
  -H "X-CE-Reset-Token: $TOKEN" \
  -H "Content-Type: application/json" \
  "$BASE/api/admin/schema-migrations-init" \
  | python3 -m json.tool

echo "==> Fetch schema from $BASE/api/admin/schema-export"
curl -fsS \
  -H "X-CE-Reset-Token: $TOKEN" \
  "$BASE/api/admin/schema-export" \
  -o "$OUT"

TABLES=$(grep -c '^CREATE TABLE' "$OUT" || true)
echo "Wrote $OUT ($TABLES CREATE TABLE statements)"
