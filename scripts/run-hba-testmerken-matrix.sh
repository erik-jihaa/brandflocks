#!/usr/bin/env bash
# Haal HBA-testmerken matrix op van staging (reset-token) → docs/hba-testmerken-resultaat.md
# Standaard: 5 runs stabiliteit (v1.7). Alleen huidige matrix: --single
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV_FILE="${CE_ENV_FILE:-$ROOT/.env.staging}"
OUT="$ROOT/docs/hba-testmerken-resultaat.md"
S4="$ROOT/docs/hba-testmerken-s4-onderzoek.md"
PROTO="$ROOT/docs/hba-testmerken-protocol-stap34.md"
STAB="$ROOT/docs/hba-testmerken-stabiliteit.md"

RUNS=5
if [[ "${1:-}" == "--single" ]]; then
  RUNS=1
  shift 1
elif [[ "${1:-}" == "--runs" ]]; then
  RUNS="${2:-5}"
  shift 2
fi

read_env() {
  python3 - "$ENV_FILE" "$1" <<'PY'
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

TMP="$(mktemp)"
if [[ "$RUNS" -gt 1 ]]; then
  echo "==> Stabiliteit: ${RUNS} runs × alle testposts (één score-request per post)…"
  PYTHONUNBUFFERED=1 python3 "$ROOT/scripts/run-hba-testmerken-stability-runs.py" "$BASE" "$TOKEN" "$RUNS" > "$TMP"
else
  curl -sS -H "X-CE-Reset-Token: $TOKEN" "$BASE/api/admin/hba-testmerken-matrix" > "$TMP"
fi

python3 - "$TMP" "$OUT" "$S4" "$PROTO" "$STAB" "$RUNS" <<'PY'
import json, sys
from pathlib import Path
from datetime import datetime

NULMETING_VERSION = "v1.3"

data_path, out_path, s4_path, proto_path, stab_path, runs_arg = sys.argv[1:7]
runs = int(runs_arg or "1")
payload = json.load(open(data_path, encoding="utf-8"))
if not payload.get("ok"):
    print(json.dumps(payload, indent=2, ensure_ascii=False))
    sys.exit(1)

if runs > 1:
    docs = Path(out_path).parent
    prompt_version = str(payload.get("promptVersion") or "onbekend")
    out_json = docs / f"hba-testmerken-stability-{prompt_version}-{runs}runs.json"
    slim = {k: v for k, v in payload.items() if k != "runMatrices"}
    out_json.write_text(json.dumps(slim, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    summary = payload.get("summary") or {}
    lines = [
        f"# HBA-testmerken — stabiliteit ({prompt_version})",
        "",
        f"Gegenereerd: {payload.get('generatedAt', datetime.utcnow().isoformat())}",
        f"Promptversie: {prompt_version}",
        f"Runs per post: {payload.get('runs', runs)}",
        f"Modellen: {', '.join(payload.get('modelsUsed') or []) or '—'}",
        "",
        f"**Stable goed (5/5):** {summary.get('stable_goed', 0)} · "
        f"**instabiel:** {summary.get('instabiel', 0)} · "
        f"**fout:** {summary.get('fout', 0)} · "
        f"**review:** {summary.get('review', 0)} · "
        f"**niet beoordeeld:** {summary.get('niet_beoordeeld', 0)}",
        "",
        f"`category_fallback` in laatste run: {payload.get('categoryFallbackPostsLastRun', '—')} posts · "
        f"`hoofdoordeel_gecorrigeerd`: {payload.get('hoofdoordeelGecorrigeerdCount', 0)} keer "
        f"({payload.get('hoofdoordeelGecorrigeerdPercent', 0)}% van {payload.get('totalScoreCount', 0)} scores)",
        "",
        f"Flags op functionele onderdelen (doel 0): **{len(payload.get('flagsOpFunctioneleOnderdelen') or [])}**",
        "",
    ]
    cb = payload.get("constraintBreachStats") or {}
    if cb:
        lines.extend(
            [
                f"`constraint_breach` behouden na verificatie: **{cb.get('geverifieerdBehouden', 0)}** · "
                f"verworpen door verificatie: **{cb.get('verworpenDoorVerificatie', 0)}** · "
                f"vervallen zonder uitleg: **{cb.get('vervallenGeenUitleg', 0)}**",
                "",
            ]
        )
    lines.extend(
        [
        f"Ruwe JSON: `{out_json.name}`",
        "",
        "| Merk | Post | Goed runs | Hoofdoordeel per run | BC-spreiding |",
        "| --- | --- | ---: | --- | ---: |",
        ]
    )
    for r in payload.get("rows") or []:
        hd = ", ".join(str(x or "—") for x in (r.get("hoofdoordeelPerRun") or []))
        spread = r.get("brandContributionSpread")
        spread_s = f"{spread:.1f}" if isinstance(spread, (int, float)) else "—"
        lines.append(
            f"| {r.get('merk','')} | {r.get('label','')} | "
            f"{r.get('goodRuns',0)}/{r.get('runsTotal',runs)} | {hd} | {spread_s} |"
        )
    unstable = payload.get("unstablePosts") or []
    if unstable:
        lines.extend(["", "### Instabiele posts (met citaten uit afwijkende runs)", ""])
        for u in unstable:
            lines.append(
                f"- **{u.get('merk')} — {u.get('label')}:** "
                f"{u.get('goodRuns')} goed runs; hoofdoordeel: {u.get('hoofdoordeelPerRun')}"
            )
            for d in u.get("deviatingRuns") or []:
                cit = d.get("flag_citaten") or {}
                cit_parts = [f"{k}: «{v}»" for k, v in cit.items()]
                cit_txt = "; ".join(cit_parts) if cit_parts else "—"
                warn = d.get("warnings") or []
                warn_txt = f" Waarschuwing: {'; '.join(warn)}" if warn else ""
                lines.append(
                    f"  - Run {d.get('run')}: oordeel **{d.get('judgment')}**, "
                    f"hoofdoordeel {d.get('hoofdoordeel')}, flags {d.get('flags')}; citaten: {cit_txt}{warn_txt}"
                )
    func_hits = payload.get("flagsOpFunctioneleOnderdelen") or []
    if func_hits:
        lines.extend(["", "### Flags op functionele onderdelen", ""])
        for h in func_hits:
            lines.append(
                f"- **{h.get('merk')} — {h.get('label')}** ({h.get('flag')}): «{h.get('citaat')}»"
            )

    def row_matches(merk, label, index=None):
        for r in payload.get("rows") or []:
            if r.get("merk") != merk:
                continue
            if label and r.get("label") == label:
                return r
            if index is not None and r.get("index") == index:
                return r
        return None

    def all_runs(fn, r):
        return all(fn(run) for run in (r.get("runs") or []))

    checks = []
    for merk in ("Brandpunt Koffie", "Stelwerk", "Duinroos Thuiszorg"):
        r3 = row_matches(merk, "Breekt de regel")
        ok = r3 and all_runs(lambda x: x.get("hoofdoordeel") == "regelbreuk", r3)
        checks.append(("Post 3 " + merk, ok))
        r2 = row_matches(merk, "Categorietaal")
        ok = r2 and all_runs(
            lambda x: x.get("hoofdoordeel") == "categorietaal"
            and "constraint_breach" not in (x.get("flags") or []),
            r2,
        )
        checks.append(("Post 2 " + merk, ok))
        r1 = row_matches(merk, "Op merk")
        ok = r1 and all_runs(lambda x: x.get("hoofdoordeel") == "op_merk", r1)
        checks.append(("Post 1 " + merk, ok))
        r4 = row_matches(merk, "Neutraal / grensgeval")
        ok4 = r4 and all_runs(lambda x: x.get("hoofdoordeel") == "neutraal", r4)
        checks.append(("Post 4 " + merk, ok4))
    bp5 = row_matches("Brandpunt Koffie", "Verdedigt de regel")
    checks.append(
        (
            "Brandpunt post 5",
            bp5
            and all_runs(
                lambda x: x.get("hoofdoordeel") == "op_merk"
                and "category_fallback" not in (x.get("flags") or []),
                bp5,
            ),
        )
    )
    dr6 = row_matches("Duinroos Thuiszorg", "Logica van de tegenkracht")
    checks.append(
        (
            "Duinroos post 6",
            dr6
            and all_runs(
                lambda x: x.get("hoofdoordeel") == "tegenkracht"
                and "constraint_breach" not in (x.get("flags") or []),
                dr6,
            ),
        )
    )
    spread_ok = all(
        (r.get("brandContributionSpread") is None or r.get("brandContributionSpread") <= 1.0)
        for r in payload.get("rows") or []
        if not r.get("ambiguous")
    )
    checks.append(("BC-spreiding ≤ 1 (alle posts)", spread_ok))
    corr_pct_raw = payload.get("hoofdoordeelGecorrigeerdPercent")
    corr_pct = float(corr_pct_raw) if corr_pct_raw is not None else 100.0
    checks.append(("hoofdoordeel_gecorrigeerd ≤ 5%", corr_pct <= 5.0))
    func_n = len(payload.get("flagsOpFunctioneleOnderdelen") or [])
    checks.append(("Flags op functionele onderdelen = 0", func_n == 0))
    bp6 = row_matches("Brandpunt Koffie", "Mechanisme dat markthygiëne is")
    checks.append(
        (
            "Brandpunt post 6 (review)",
            bool(bp6 and bp6.get("ambiguous")),
        )
    )
    v0_path = docs / "hba-v0-testresultaat.json"
    if v0_path.is_file():
        try:
            v0 = json.loads(v0_path.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            v0 = {}
        lines.extend(["", "### V0 — self_review en hbaHint", ""])
        for v in v0.get("verleiding") or []:
            merk = v.get("merk") or "—"
            sr = v.get("selfReview")
            hint = v.get("hbaHint")
            if hint is None:
                hint = (v.get("hbaHintTrigger") or {}).get("hint")
            sr_ok = isinstance(sr, dict) and bool(
                (sr.get("weakestClaim") or "").strip()
                or (sr.get("themeConflictWhy") or "").strip()
                or (sr.get("themePremisseWat") or "").strip()
                or sr.get("themePremisse")
            )
            hint_ok = isinstance(hint, dict) and bool((hint.get("aanwijzing") or "").strip())
            lines.append(
                f"- **{merk}:** self_review={'gevuld' if sr_ok else 'leeg'} · "
                f"hint={'gevuld' if hint_ok else 'leeg'}"
            )
    lines.extend(["", "### Klaar-criteria (alle runs)", ""])
    for name, ok in checks:
        lines.append(f"- [{'x' if ok else ' '}] {name}")
    lines.extend(
        [
            "",
            f"Vóór R2 (v1.6 run B): `category_fallback` op **13/16** posts. "
            f"Na R2 laatste run: **{payload.get('categoryFallbackPostsLastRun', '—')}/{len(payload.get('rows') or [])}** posts.",
        ]
    )
    Path(stab_path).write_text("\n".join(lines) + "\n", encoding="utf-8")
    existing = Path(out_path)
    if existing.is_file():
        text = existing.read_text(encoding="utf-8")
        marker = "## Stabiliteit v1.7"
        block = "\n".join(lines)
        if marker in text:
            head, _, tail = text.partition(marker)
            old_tail = tail.split("\n---\n", 1)
            rest = old_tail[1] if len(old_tail) > 1 else ""
            text = head + block + ("\n---\n" + rest if rest else "\n")
        else:
            text = text.rstrip() + "\n\n---\n\n" + block + "\n"
        existing.write_text(text, encoding="utf-8")
    print(f"Wrote stability ({len(payload.get('rows') or [])} posts, {runs} runs) → {out_json.name}")
    sys.exit(0)

rows = payload.get("rows") or []
summary = payload.get("summary") or {}
review = payload.get("reviewList") or []
prompt_version = str(payload.get("promptVersion") or "onbekend")

docs = Path(out_path).parent
Path(docs / f"hba-testmerken-matrix-{prompt_version}.json").write_text(
    json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8"
)
before_rows = {}
before_path = docs / f"hba-testmerken-matrix-{NULMETING_VERSION}.json"
if prompt_version != NULMETING_VERSION and before_path.is_file():
    before = json.loads(before_path.read_text(encoding="utf-8"))
    for b in before.get("rows") or []:
        before_rows[(b.get("merk"), b.get("label"))] = b

def fmt(v):
    if v is None:
        return "—"
    if isinstance(v, float):
        return f"{v:.1f}"
    return str(v)

lines = []
lines.append("# HBA-testmerken — resultaat")
lines.append("")
lines.append(f"Gegenereerd: {payload.get('generatedAt', datetime.utcnow().isoformat())}")
lines.append(f"Omgeving: {payload.get('environment', '?')}")
lines.append(f"Promptversie: {payload.get('promptVersion', '?')}")
models = payload.get("modelsUsed") or []
lines.append(f"Modellen (scores): {', '.join(models) if models else '—'}")
if payload.get("note"):
    lines.append("")
    lines.append(f"*{payload['note']}*")
lines.append("")
if prompt_version == NULMETING_VERSION:
    lines.append("## Nulmeting (vóór S2)")
else:
    lines.append(f"## Meting {prompt_version} (na S2)")
    if before_rows:
        lines.append("")
        lines.append(
            f"Vóór-kolommen: nulmeting {NULMETING_VERSION} uit `docs/{before_path.name}`."
        )
lines.append("")
lines.append(
    f"Samenvatting: **{summary.get('goed', 0)} goed**, "
    f"**{summary.get('fout', 0)} fout**, "
    f"**{summary.get('review', 0)} review**, "
    f"**{summary.get('geen_score', 0)} zonder score**."
)
lines.append("")
header = "| Merk | Post | Verwacht | BC | Distinct. | Hoofdoordeel | Contentrol | Flags (werkelijk) | Oordeel | Toelichting |"
sep = "| --- | --- | --- | ---: | ---: | --- | --- | --- | --- | --- |"
if before_rows:
    header += " Vóór: BC | Vóór: flags | Vóór: oordeel |"
    sep += " ---: | --- | --- |"
lines.append(header)
lines.append(sep)

for r in rows:
    flags_exp = ", ".join(r.get("verwacht_flags") or []) or "—"
    niveau = r.get("verwacht_niveau") or "—"
    exp_cell = (r.get("verwacht") or "").replace("\n", " ")
    if len(exp_cell) > 80:
        exp_cell = exp_cell[:77] + "…"
    exp_cell = f"{exp_cell} ({flags_exp}; {niveau})"
    flags_act = ", ".join(r.get("flags") or []) or "—"
    reasons = "; ".join(r.get("reasons") or [])
    judgment = "review" if r.get("ambiguous") else (r.get("judgment") or "—")
    line = "| {merk} | {label} | {exp} | {bc} | {dist} | {hoofd} | {rol} | {flags} | **{j}** | {reasons} |".format(
        merk=r.get("merk", ""),
        label=r.get("label", ""),
        exp=exp_cell.replace("|", "\\|"),
        bc=fmt(r.get("brandContribution")),
        dist=fmt(r.get("distinctiviteit")),
        hoofd=(r.get("hoofdoordeel") or "—").replace("|", "\\|"),
        rol=(r.get("contentrol") or "—").replace("|", "\\|"),
        flags=flags_act.replace("|", "\\|"),
        j=judgment,
        reasons=(reasons or "—").replace("|", "\\|"),
    )
    if before_rows:
        b = before_rows.get((r.get("merk"), r.get("label"))) or {}
        b_judgment = "review" if b.get("ambiguous") else (b.get("judgment") or "—")
        line += " {bc} | {flags} | {j} |".format(
            bc=fmt(b.get("brandContribution")),
            flags=(", ".join(b.get("flags") or []) or "—").replace("|", "\\|"),
            j=b_judgment,
        )
    lines.append(line)

lines.append("")
lines.append("### Reviewlijst (geen auto goed/fout)")
lines.append("")
if not review:
    lines.append("_Geen._")
else:
    for item in review:
        lines.append(f"- **{item.get('merk')} — {item.get('post')}:** {item.get('verwacht')}")

lines.append("")
lines.append("---")
lines.append("")
if Path(s4_path).is_file():
    lines.append(Path(s4_path).read_text(encoding="utf-8").strip())
    lines.append("")
if Path(stab_path).is_file():
    lines.append("---")
    lines.append("")
    lines.append(Path(stab_path).read_text(encoding="utf-8").strip())
    lines.append("")
if Path(proto_path).is_file():
    lines.append("---")
    lines.append("")
    lines.append(Path(proto_path).read_text(encoding="utf-8").strip())
    lines.append("")

Path(out_path).write_text("\n".join(lines) + "\n", encoding="utf-8")
print(f"Wrote {out_path} ({len(rows)} rijen)")
PY

rm -f "$TMP"
