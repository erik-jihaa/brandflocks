#!/usr/bin/env bash
# Voeg een Trello-bord toe als bron voor Testklant BV (via n8n board-lijst + CE API)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CLIENT_ID="${CE_CLIENT_ID:-016384af-b26e-4415-aa9c-abc46b804798}"
API_BASE="${CE_API_BASE:-https://ce.igisnext.com/api}"
BOARD_ID="${1:-}"
BOARD_NAME="${2:-}"

if [[ ! -f "$ROOT/.env" ]]; then
  echo "Missing $ROOT/.env" >&2
  exit 1
fi
set -a
# shellcheck disable=SC1091
source "$ROOT/.env"
set +a

WEBHOOK="${N8N_API_URL}/webhook/ce-trello-boards"

echo "==> Trello boards ophalen via n8n…"
RAW=$(curl -sS "$WEBHOOK")
echo "$RAW" | python3 -c "import json,sys; d=json.load(sys.stdin); print('boards:', len(d.get('boards',[])))"

if [[ -z "$BOARD_ID" ]]; then
  PICK=$(echo "$RAW" | python3 -c "
import json,sys
d=json.load(sys.stdin)
boards=d.get('boards') or []
if not boards:
    raise SystemExit('Geen boards — controleer n8n Trello credential')
pref=['content','content engine','igis','editorial','redactie','marketing']
chosen=boards[0]
for b in boards:
    name=(b.get('name') or '').lower()
    if any(p in name for p in pref):
        chosen=b
        break
print(json.dumps({'id': chosen['id'], 'name': chosen.get('name') or 'Trello board'}))
")
  BOARD_ID=$(echo "$PICK" | python3 -c "import json,sys; print(json.load(sys.stdin)['id'])")
  BOARD_NAME=$(echo "$PICK" | python3 -c "import json,sys; print(json.load(sys.stdin)['name'])")
  echo "==> Gekozen board: $BOARD_NAME ($BOARD_ID)"
fi

TITLE="${BOARD_NAME:-Trello board}"

echo "==> Bron aanmaken op $API_BASE …"
PAYLOAD=$(python3 - <<PY
import json
print(json.dumps({
    "category": "Trello",
    "title": """$TITLE""",
    "trelloBoardId": """$BOARD_ID""",
}))
PY
)
RESP=$(curl -sS -X POST "$API_BASE/sources" \
  -H "Content-Type: application/json" \
  -H "X-Client-Id: $CLIENT_ID" \
  -d "$PAYLOAD")

echo "$RESP" | python3 -m json.tool

SOURCE_ID=$(echo "$RESP" | python3 -c "import json,sys; d=json.load(sys.stdin); print(d.get('id',''))")
if [[ -z "$SOURCE_ID" ]]; then
  echo "Create failed" >&2
  exit 1
fi

echo "==> Check verbinding…"
curl -sS -X POST "$API_BASE/sources/$SOURCE_ID/check" -H "X-Client-Id: $CLIENT_ID" | python3 -m json.tool
echo "Done."
