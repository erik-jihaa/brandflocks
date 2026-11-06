#!/usr/bin/env bash
# CE-04.1bis reviewer-flow: submit → review POST (feedback) → accept via review-edits PATCH
# Controleert dat content_human pas na accept wordt bijgewerkt.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE="${CE_PUBLIC_BASE_URL:-https://ce.igisnext.com}"

if [[ -f "$ROOT/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "$ROOT/.env"
  set +a
fi

SLOT="${1:-}"
RESET_HDR=()
if [[ -n "${CE_RESET_TOKEN:-}" ]]; then
  RESET_HDR=(-H "X-CE-Reset-Token: $CE_RESET_TOKEN")
fi

if [[ -z "$SLOT" ]]; then
  SLOT="$(curl -sS "${RESET_HDR[@]}" "$BASE/api/home" | python3 -c "
import json, sys
d = json.load(sys.stdin)
a = d.get('nextArticle') or {}
if a.get('status') in ('written', 'draft', 'feedback', 'pending'):
    print(a.get('slotId', ''))
")"
fi

if [[ -z "$SLOT" ]]; then
  echo "Geen geschikt slot — geef slot UUID als argument." >&2
  exit 1
fi

echo "==> Slot: $SLOT"

echo "==> Submit (human)"
SUBMIT_RESP="$(curl -sS -X POST "${RESET_HDR[@]}" "$BASE/api/articles/$SLOT/submit" \
  -H "Content-Type: application/json" \
  -d '{"reviewMode":"human"}')"
echo "$SUBMIT_RESP" | python3 -m json.tool | head -20

TOKEN="$(echo "$SUBMIT_RESP" | python3 -c "import json,sys; d=json.load(sys.stdin); u=d.get('reviewUrl',''); print(u.rsplit('/',1)[-1] if u else '')")"
if [[ -z "$TOKEN" ]]; then
  echo "FAIL: geen reviewUrl in submit-response" >&2
  exit 1
fi

echo "==> Review GET"
REVIEW_GET="$(curl -sS "$BASE/api/review/$TOKEN")"
echo "$REVIEW_GET" | python3 -c "import json,sys; d=json.load(sys.stdin); print('canDecide:', d.get('canDecide'))"

PAYLOAD_FILE="$(mktemp)"
python3 -c "
import json, sys
review = json.loads(sys.argv[1])
content = review.get('content') or ''
edited = content + '\n\nReviewer toegevoegd: CE-04 E2E testzin.'
print(json.dumps({
    'decision': 'feedback',
    'content': edited,
    'headline': 'E2E Review Headline',
}))
" "$REVIEW_GET" > "$PAYLOAD_FILE"

echo "==> Review POST (feedback + body-edit)"
REVIEW_POST="$(curl -sS -X POST "$BASE/api/review/$TOKEN" \
  -H "Content-Type: application/json" \
  -d @"$PAYLOAD_FILE")"
rm -f "$PAYLOAD_FILE"
echo "$REVIEW_POST" | python3 -c "import json,sys; d=json.load(sys.stdin); print('decision:', d.get('decision'), 'editCount:', d.get('editCount'))"

echo "==> Article na review POST"
AFTER_REVIEW="$(curl -sS "${RESET_HDR[@]}" "$BASE/api/articles/$SLOT")"
EDIT_ID="$(echo "$AFTER_REVIEW" | python3 -c "
import json, sys
d = json.load(sys.stdin)
edits = [e for e in (d.get('reviewEdits') or []) if e.get('status') == 'pending']
print('pendingEdits:', len(edits))
if edits:
    print('editId:', edits[-1]['id'])
else:
    print('editId:', '')
")"

EDIT_ID="$(echo "$AFTER_REVIEW" | python3 -c "
import json, sys
d = json.load(sys.stdin)
edits = [e for e in (d.get('reviewEdits') or []) if e.get('status') == 'pending']
print(edits[-1]['id'] if edits else '')
")"

if [[ -z "$EDIT_ID" ]]; then
  echo "FAIL: geen pending review-edit na reviewer feedback" >&2
  exit 1
fi

echo "==> Accept review-edit"
ACCEPT_RESP="$(curl -sS -X PATCH "${RESET_HDR[@]}" "$BASE/api/articles/$SLOT/review-edits/$EDIT_ID" \
  -H "Content-Type: application/json" \
  -d '{"status":"accepted"}')"
echo "$ACCEPT_RESP" | python3 -c "
import json, sys
d = json.load(sys.stdin)
print('accepted:', 'Reviewer toegevoegd' in (d.get('content') or ''))
"

echo "Done — review URL: $BASE/review/$TOKEN"
