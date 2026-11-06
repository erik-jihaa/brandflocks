#!/usr/bin/env bash
# Stap 1 nulmeting: freeze-kopieën + CE-03 (+ dubbele run slot 2/3) + export naar docs/testset/
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${1:-$ROOT/.env.staging}"
if [[ ! -f "$ENV_FILE" ]]; then
  echo "Missing $ENV_FILE" >&2
  exit 1
fi

read_env() {
  local key="$1"
  python3 - "$ENV_FILE" "$key" <<'PY'
import sys
from pathlib import Path
key = sys.argv[2]
for line in Path(sys.argv[1]).read_text().splitlines():
    line = line.strip()
    if line.startswith(key + "="):
        print(line.split("=", 1)[1].strip().strip('"').strip("'"))
        break
PY
}

BASE="$(read_env CE_PUBLIC_BASE_URL)"
TOKEN="$(read_env CE_RESET_TOKEN)"
if [[ -z "$BASE" || -z "$TOKEN" ]]; then
  echo "CE_PUBLIC_BASE_URL en CE_RESET_TOKEN verplicht in $ENV_FILE" >&2
  exit 1
fi

AUTH=(-H "X-CE-Reset-Token: $TOKEN" -H "Content-Type: application/json")
OUT_DIR="$ROOT/docs/testset/stap1-nulmeting"
FREEZE_JSON="$OUT_DIR/freeze-2026-10-02.json"
mkdir -p "$OUT_DIR"

if [[ -n "${SKIP_FREEZE:-}" && -f "$FREEZE_JSON" ]]; then
  echo "==> Freeze overgeslagen (bestaand $FREEZE_JSON)"
else
  echo "==> Freeze kopieën (staging)"
  curl -sS "${AUTH[@]}" -X POST "$BASE/api/admin/testset-freeze" \
    -d '{"freezeLabel":"2026-10-02"}' | tee "$FREEZE_JSON" | python3 -m json.tool >/dev/null
fi

python3 - "$FREEZE_JSON" <<'PY'
import json, sys
data = json.load(open(sys.argv[1]))
for i, s in enumerate(data.get("slots", []), 1):
    print(f"  #{i} copy {s['copySlotId']}  ←  {s['sourceSlotId']}  ({s.get('themeTitle','')[:50]})")
PY

# Build run list: 6 slots + extra runs for copy of source slots 2 and 3 (Axiom #2, iGiS flessenhals)
RUNS_FILE="$OUT_DIR/ce03-runs.json"
python3 - "$FREEZE_JSON" "$RUNS_FILE" <<'PY'
import json, sys
freeze = json.load(open(sys.argv[1]))
slots = freeze["slots"]
# index 1 = slot 2 Axiom, index 2 = slot 3 iGiS
runs = []
for idx, s in enumerate(slots):
    runs.append({"slotIndex": idx + 1, "copySlotId": s["copySlotId"], "clientId": s["clientId"], "run": 1})
for idx in (1, 2):
    s = slots[idx]
    runs.append({"slotIndex": idx + 1, "copySlotId": s["copySlotId"], "clientId": s["clientId"], "run": 2})
json.dump({"runs": runs}, open(sys.argv[2], "w"), indent=2)
print(f"CE-03 runs: {len(runs)}")
PY

poll_article() {
  local slot_id="$1"
  local hash_before="$2"
  local max_wait="${3:-240}"
  local waited=0
  while (( waited < max_wait )); do
    sleep 15
    waited=$((waited + 15))
    metrics=$(curl -sS -H "X-CE-Reset-Token: $TOKEN" \
      "$BASE/api/admin/debug-article?slot_id=$slot_id&metrics=1")
    hash=$(python3 -c "import json,sys; d=json.load(sys.stdin); print((d.get('articles') or [{}])[0].get('content_hash') or '')" <<<"$metrics")
    wc=$(python3 -c "import json,sys; d=json.load(sys.stdin); print((d.get('articles') or [{}])[0].get('content_word_count') or 0)" <<<"$metrics")
    if [[ -n "$hash" && "$hash" != "$hash_before" && "$wc" -gt 50 ]]; then
      echo "    klaar na ${waited}s (words=$wc)"
      return 0
    fi
    echo "    … wacht op CE-03 (${waited}s)"
  done
  echo "    TIMEOUT slot $slot_id" >&2
  return 1
}

echo "==> CE-03 triggers"
python3 - "$RUNS_FILE" "$BASE" "$TOKEN" <<'PY'
import json, sys, time, urllib.request

runs = json.load(open(sys.argv[1]))["runs"]
base, token = sys.argv[2], sys.argv[3]

def get_metrics(slot_id):
    url = f"{base}/api/admin/debug-article?slot_id={slot_id}&metrics=1"
    req = urllib.request.Request(url, headers={"X-CE-Reset-Token": token})
    with urllib.request.urlopen(req, timeout=60) as resp:
        d = json.load(resp)
    art = (d.get("articles") or [{}])[0]
    return art.get("content_hash") or "", int(art.get("content_word_count") or 0)

def trigger(client_id, slot_id):
    url = f"{base}/api/admin/ce03-phase2-test?client_id={client_id}&slot_id={slot_id}&platform=blog&trigger=1"
    req = urllib.request.Request(url, headers={"X-CE-Reset-Token": token})
    with urllib.request.urlopen(req, timeout=120) as resp:
        json.load(resp)

for r in runs:
    idx, run_n = r["slotIndex"], r["run"]
    slot, client = r["copySlotId"], r["clientId"]
    print(f"  Slot {idx} run {run_n} → {slot}")
    h0, _ = get_metrics(slot)
    trigger(client, slot)
    waited = 0
    while waited < 300:
        time.sleep(15)
        waited += 15
        h1, wc = get_metrics(slot)
        if h1 and h1 != h0 and wc > 50:
            print(f"    klaar na {waited}s (words={wc})")
            break
        print(f"    … wacht op CE-03 ({waited}s)")
    else:
        print(f"    TIMEOUT slot {slot}", file=sys.stderr)
PY

echo "==> Export naar $OUT_DIR"
python3 - "$FREEZE_JSON" "$RUNS_FILE" "$OUT_DIR" "$BASE" "$TOKEN" <<'PY'
import json, sys, urllib.request, secrets, string, datetime
from pathlib import Path

freeze = json.load(open(sys.argv[1]))
runs = json.load(open(sys.argv[2]))["runs"]
out_dir = Path(sys.argv[3])
base, token = sys.argv[4], sys.argv[5]

def fetch(slot_id, full=False):
    url = f"{base}/api/admin/debug-article?slot_id={slot_id}" + ("&full=1" if full else "")
    req = urllib.request.Request(url, headers={"X-CE-Reset-Token": token})
    with urllib.request.urlopen(req, timeout=60) as resp:
        return json.load(resp)

codes = {}
alphabet = string.ascii_uppercase + string.digits
for _ in range(20):
    c = secrets.choice(alphabet) + secrets.choice(alphabet)
    if c not in codes:
        codes[c] = None

runlog = []
code_iter = iter(sorted(codes.keys()))
# one code per unique (slotIndex, run) from last successful fetch — use run order
for r in runs:
    slot_id = r["copySlotId"]
    data = fetch(slot_id, full=True)
    art = (data.get("articles") or [{}])[0]
    code = next(code_iter)
    codes[code] = {
        "slotIndex": r["slotIndex"],
        "run": r["run"],
        "copySlotId": slot_id,
        "sourceSlotId": freeze["slots"][r["slotIndex"] - 1]["sourceSlotId"],
        "stap": "1-nulmeting",
    }
    content = art.get("content_ai") or ""
    md = out_dir / f"{code}.md"
    md.write_text(
        f"# Testset {code}\n\n"
        f"<!-- blind code — zie sleutel.md na score -->\n\n"
        f"## Headline\n\n{(art.get('headline') or art.get('headline_human') or '').strip()}\n\n"
        f"## Tekst (content_ai)\n\n{content.strip()}\n\n"
        f"## self_review\n\n{(art.get('self_review') or '(leeg)').strip()}\n",
        encoding="utf-8",
    )
    runlog.append({
        "code": code,
        "slotIndex": r["slotIndex"],
        "run": r["run"],
        "content_words": art.get("content_word_count"),
        "headline": art.get("headline"),
    })

(out_dir.parent / "sleutel.md").write_text(
    "# Sleutel testset — pas openen na blind score\n\n"
    f"Vastgelegd: {datetime.date.today().isoformat()}\n\n"
    + "\n".join(
        f"- **{code}** → slot {v['slotIndex']}, run {v['run']}, kopie `{v['copySlotId']}` (bron `{v['sourceSlotId']}`)"
        for code, v in sorted(codes.items()) if v
    )
    + "\n",
    encoding="utf-8",
)

(out_dir / "runlog.json").write_text(json.dumps(runlog, indent=2, ensure_ascii=False), encoding="utf-8")
print(f"Exported {len(runlog)} versies; sleutel in docs/testset/sleutel.md")
PY

echo "==> Blind leespagina (HTML)"
python3 "$ROOT/scripts/build-testset-blind-html.py"

echo "==> Klaar. Blind lezen: /testset-nulmeting/ op staging (of lokaal web/testset-nulmeting/index.html)"
