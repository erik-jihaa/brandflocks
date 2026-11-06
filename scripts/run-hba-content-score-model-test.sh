#!/usr/bin/env bash
# HBA testprotocol §2 — Sonnet 5 vs Opus 5 contentscore A/B (batch via admin API).
#
#   ./scripts/run-hba-content-score-model-test.sh staging
#   ./scripts/run-hba-content-score-model-test.sh production --client igisnext
#   ./scripts/run-hba-content-score-model-test.sh staging --client-id UUID
#
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
source "$ROOT/scripts/ce-env.sh"

TARGET_ENV=""
CLIENT_SEARCH="doornvogel"
CLIENT_ID=""
MAX_TOTAL=50
BATCH=2
OUT=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    local|staging|production) TARGET_ENV="$1" ;;
    --client) CLIENT_SEARCH="$2"; shift ;;
    --client-id) CLIENT_ID="$2"; shift ;;
    --max) MAX_TOTAL="$2"; shift ;;
    --batch) BATCH="$2"; shift ;;
    --out) OUT="$2"; shift ;;
    *) echo "Onbekend argument: $1" >&2; exit 1 ;;
  esac
  shift
done

ENV_FILE="$(ce_env_file "$ROOT" "$TARGET_ENV")"
BASE_URL="$(ce_env_value "$ENV_FILE" CE_PUBLIC_BASE_URL)"
TOKEN="$(ce_env_value "$ENV_FILE" CE_RESET_TOKEN)"

if [[ -z "$TOKEN" ]]; then
  echo "CE_RESET_TOKEN ontbreekt in $(basename "$ENV_FILE")" >&2
  exit 1
fi

TS="$(date +%Y%m%d-%H%M%S)"
OUT="${OUT:-$ROOT/briefing/reports/hba-content-score-ab-${TARGET_ENV:-default}-${TS}.md}"
mkdir -p "$(dirname "$OUT")"
JSONL="${OUT%.md}.jsonl"
: > "$JSONL"

preview_url="$BASE_URL/api/admin/hba-content-score-model-test"
if [[ -n "$CLIENT_ID" ]]; then
  preview_q="client_id=$(python3 -c 'import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1]))' "$CLIENT_ID")"
else
  preview_q="client_search=$(python3 -c 'import urllib.parse,sys; print(urllib.parse.quote(sys.argv[1]))' "$CLIENT_SEARCH")"
fi

echo "==> Preview ($TARGET_ENV) — $preview_q"
PREVIEW="$(curl -sS -H "X-CE-Reset-Token: $TOKEN" "$preview_url?$preview_q")"
echo "$PREVIEW" | python3 -m json.tool | head -n 30

CLIENT_NAME="$(echo "$PREVIEW" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('client',{}).get('name','?'))")"
CLIENT_ID="$(echo "$PREVIEW" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('client',{}).get('id',''))")"
TOTAL="$(echo "$PREVIEW" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('totalCandidates',0))")"

if [[ -z "$CLIENT_ID" ]]; then
  echo "Client niet gevonden voor zoekterm '$CLIENT_SEARCH'" >&2
  exit 1
fi

{
  echo "# HBA contentscore A/B — Sonnet 5 vs Opus 5"
  echo
  echo "- Omgeving: **${TARGET_ENV:-default}** ($BASE_URL)"
  echo "- Client: **$CLIENT_NAME** (\`$CLIENT_ID\`)"
  echo "- Modellen: \`claude-sonnet-5\` vs \`claude-opus-5\`"
  echo "- Kandidaten: $TOTAL (max $MAX_TOTAL in deze run)"
  echo "- Datum: $(date -Iseconds)"
  echo
  echo "> Beoordeling (handmatig): per post **juist** / **generiek gemist** / **verkeerd label** — vergelijk diagnoses, niet cijfers."
  echo
} > "$OUT"

offset=0
while [[ $offset -lt $MAX_TOTAL ]]; do
  echo "==> Batch offset=$offset batch=$BATCH"
  BODY="$(python3 - <<PY
import json
print(json.dumps({
  'client_id': '$CLIENT_ID',
  'offset': $offset,
  'limit': $BATCH,
  'max_total': $MAX_TOTAL,
}))
PY
)"
  RESP="$(curl -sS --max-time 600 -X POST \
    -H "X-CE-Reset-Token: $TOKEN" \
    -H 'Content-Type: application/json' \
    -d "$BODY" \
    "$BASE_URL/api/admin/hba-content-score-model-test")"

  echo "$RESP" >> "$JSONL"

  python3 - "$RESP" "$OUT" <<'PY'
import json, sys
resp = json.loads(sys.argv[1])
out = sys.argv[2]
for row in resp.get('results', []):
    with open(out, 'a', encoding='utf-8') as f:
        f.write(f"## {row.get('headline') or row.get('articleId')}\n\n")
        f.write(f"- Artikel: `{row.get('articleId')}` · status: {row.get('status')} · published: {row.get('publishedAt') or '—'}\n\n")
        for key, label in [('sonnet', 'Sonnet 5'), ('opus', 'Opus 5')]:
            block = row.get(key) or {}
            f.write(f"### {label}\n\n")
            if not block.get('ok'):
                f.write(f"**Fout:** {block.get('error', '?')}\n\n")
                continue
            f.write(f"- **Contentrol:** {block.get('contentrol') or '—'}\n")
            f.write(f"- **Status:** {block.get('status') or '—'}\n")
            f.write(f"- **Brand contribution (ref):** {block.get('brandContribution')}\n")
            f.write(f"- **Diagnose:** {block.get('diagnose') or '—'}\n\n")
        f.write("**Beoordeling:** _juist / generiek gemist / verkeerd label_\n\n---\n\n")
PY

  has_more="$(echo "$RESP" | python3 -c "import json,sys; print('1' if json.load(sys.stdin).get('hasMore') else '0')")"
  next="$(echo "$RESP" | python3 -c "import json,sys; print(json.load(sys.stdin).get('nextOffset') or '')")"
  if [[ "$has_more" != "1" || -z "$next" ]]; then
    break
  fi
  offset="$next"
done

echo
echo "Rapport: $OUT"
echo "JSONL:  $JSONL"
