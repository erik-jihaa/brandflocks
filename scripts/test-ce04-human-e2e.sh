#!/usr/bin/env bash
# CE-04.1bis end-to-end: human submit → mail webhook → review API
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BASE="${CE_PUBLIC_BASE_URL:-https://ce.igisnext.com}"
SLOT="${1:-}"

if [[ -z "$SLOT" ]]; then
  SLOT="$(curl -sS "$BASE/api/home" | python3 -c "
import json, sys
d = json.load(sys.stdin)
a = d.get('nextArticle') or {}
if a.get('status') in ('written', 'draft', 'feedback'):
    print(a.get('slotId', ''))
")"
fi

if [[ -z "$SLOT" ]]; then
  echo "Geen geschikt slot — geef slot UUID als argument." >&2
  exit 1
fi

echo "==> Slot: $SLOT"
echo "==> Submit (human)"
RESP="$(curl -sS -X POST "$BASE/api/articles/$SLOT/submit" \
  -H "Content-Type: application/json" \
  -d '{"reviewMode":"human"}')"

echo "$RESP" | python3 -m json.tool | head -25

TOKEN="$(echo "$RESP" | python3 -c "import json,sys; d=json.load(sys.stdin); u=d.get('reviewUrl',''); print(u.rsplit('/',1)[-1] if u else '')")"
MAIL="$(echo "$RESP" | python3 -c "import json,sys; print(json.load(sys.stdin).get('mailSent'))")"

if [[ "$MAIL" != "True" ]]; then
  echo "WARN: mailSent is not true" >&2
fi

if [[ -n "$TOKEN" ]]; then
  echo "==> Review API"
  curl -sS "$BASE/api/review/$TOKEN" | python3 -c "import json,sys; d=json.load(sys.stdin); print('canDecide:', d.get('canDecide'), 'headline:', d.get('headline','')[:60])"
  echo "Review URL: $BASE/review/$TOKEN"
fi

echo "Done."
