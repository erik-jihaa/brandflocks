#!/usr/bin/env python3
"""Stap 6: uitsplitsing ijkrun-fouten per as (oordeel / meetlat / signaal) + judge vs inhoud."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path
from typing import Any

JUDGE_REASON_HINTS = (
    "meetlat ",
    "meetlat_geblokkeerd",
    "meetlat geblokkeerd",
    "methodische term in klantzichtbare",
    "methodische term in aanwijzing",
    "Geen proof_gap/foe_vervaging op kop",
    "hoofdoordeel regelbreuk (verboden bij T13)",
    "proof_gap ontbreekt (T14 valkuil)",
    "S05:",
)


def primary_axis(case: dict[str, Any]) -> str:
    j_m = str(case.get("judgment_meetlat") or "")
    j_o = str(case.get("judgment_oordeel") or "")
    reasons = [str(r) for r in (case.get("reasons") or [])]

    if j_m == "fout":
        return "meetlat"
    if j_o != "fout":
        return "—"

    for r in reasons:
        if "signaal" in r.lower() or r.startswith("verplicht signaal") or r.startswith("verboden signaal"):
            return "signaal"
    for r in reasons:
        if r.startswith("meetlat "):
            return "meetlat"
    return "oordeel"


def is_judge_v03_reason(reason: str) -> bool:
    r = reason.lower()
    if r.startswith("meetlat ") and "verwacht" in r:
        return True
    if "meetlat_geblokkeerd" in r:
        return True
    if "alleen een melding zonder inhoudelijk oordeel" in r:
        return True
    if "methodische term in klantzichtbare" in r:
        return True
    if "t13 valkuil" in r or "t14 valkuil" in r:
        return True
    return False


def failure_kind(reasons: list[str]) -> str:
    if not reasons:
        return "onbekend"
    judge_hits = sum(1 for r in reasons if is_judge_v03_reason(r) or any(h in r for h in JUDGE_REASON_HINTS))
    if judge_hits == len(reasons):
        return "judge-regel (v0.3)"
    if judge_hits > 0:
        return "mix"
    return "inhoud"


def dim_aanscherpen(case: dict[str, Any]) -> list[str]:
    out = case.get("output")
    if not isinstance(out, dict):
        return []
    dim = out.get("dimensies")
    if not isinstance(dim, dict):
        return []
    rows: list[str] = []
    for key, block in dim.items():
        if not isinstance(block, dict):
            continue
        if str(block.get("oordeel") or "") == "aanscherpen":
            toel = str(block.get("toelichting") or "").strip()
            verb = str(block.get("verbetering") or "").strip()
            body = verb if verb else toel
            rows.append(f"**{key}** — {body}")
    return rows


def summarize_actual(case: dict[str, Any]) -> str:
    soort = str(case.get("soort") or "")
    out = case.get("output")
    if soort == "architectuurcheck" and isinstance(out, dict):
        tot = out.get("totaaloordeel")
        ms = case.get("meetlat_status")
        parts = [f"totaal={tot}", f"meetlat={ms}"]
        sig = out.get("signalen")
        if isinstance(sig, dict):
            true_keys = [k for k, v in sig.items() if v]
            if true_keys:
                parts.append("signalen=" + ",".join(true_keys))
        return "; ".join(parts)
    if soort == "tekstscore" and isinstance(out, dict):
        flags = out.get("flags") or []
        return (
            f"hoofdoordeel={out.get('hoofdoordeel')}; "
            f"BC={out.get('brandContribution')}; flags={','.join(flags) if isinstance(flags, list) else flags}"
        )
    if soort == "schrijfadvies" and isinstance(out, dict):
        return f"aanwijzing={str(out.get('aanwijzing') or '')[:120]}…"
    return "—"


def expected_line(case: dict[str, Any]) -> str:
    parts = []
    if case.get("verwacht_oordeel"):
        parts.append(f"oordeel={case['verwacht_oordeel']}")
    if case.get("verwacht_meetlat"):
        parts.append(f"meetlat={case['verwacht_meetlat']}")
    return "; ".join(parts) if parts else "—"


def main() -> int:
    if len(sys.argv) < 2:
        print(
            "Usage: hba-ijkrun-stap6-uitsplitsing.py RUN.json [BASELINE.json] [-o OUT.md]",
            file=sys.stderr,
        )
        return 2
    out_path: Path | None = None
    positional: list[str] = []
    argv = sys.argv[1:]
    i = 0
    while i < len(argv):
        if argv[i] == "-o" and i + 1 < len(argv):
            out_path = Path(argv[i + 1])
            i += 2
            continue
        positional.append(argv[i])
        i += 1
    run_path = Path(positional[0])
    baseline_path = Path(positional[1]) if len(positional) > 1 else None
    run = json.loads(run_path.read_text(encoding="utf-8"))
    baseline: dict[str, dict] = {}
    if baseline_path and baseline_path.is_file():
        base = json.loads(baseline_path.read_text(encoding="utf-8"))
        for c in base.get("cases") or []:
            baseline[str(c.get("ijkgeval_id"))] = c

    cases = run.get("cases") or []
    fouten = [c for c in cases if str(c.get("judgment")) == "fout"]

    by_axis: dict[str, list[dict]] = {"oordeel": [], "meetlat": [], "signaal": []}
    judge_only_ids: list[str] = []
    mix_ids: list[str] = []
    inhoud_ids: list[str] = []
    meetlat_mismatch_ids: list[str] = []

    rows_md: list[str] = []
    rows_md.append(
        "| ID | As | Judge/inhoud | Verwacht | App | Redenen |"
    )
    rows_md.append("| --- | --- | --- | --- | --- | --- |")

    for c in sorted(fouten, key=lambda x: str(x.get("ijkgeval_id"))):
        cid = str(c.get("ijkgeval_id"))
        axis = primary_axis(c)
        if axis in by_axis:
            by_axis[axis].append(c)
        reasons = [str(r) for r in (c.get("reasons") or [])]
        kind = failure_kind(reasons)
        cid = str(c.get("ijkgeval_id"))
        if kind.startswith("judge"):
            judge_only_ids.append(cid)
        elif kind == "mix":
            mix_ids.append(cid)
        else:
            inhoud_ids.append(cid)
        for r in reasons:
            if r.startswith("meetlat ") and "verwacht" in r:
                meetlat_mismatch_ids.append(cid)
                break
        prev = baseline.get(cid, {})
        delta = ""
        if prev:
            pj = str(prev.get("judgment"))
            if pj != str(c.get("judgment")):
                delta = f" (was {pj} op baseline)"
        reason_cell = "; ".join(reasons).replace("|", "\\|")[:400]
        rows_md.append(
            f"| {cid}{delta} | {axis} | {kind} | {expected_line(c)} | {summarize_actual(c)} | {reason_cell} |"
        )

    a17 = next((c for c in cases if str(c.get("ijkgeval_id")) == "A17"), None)
    a17_block = ""
    if a17:
        a17_block = "\n".join(
            [
                "## A17 — Ter Horst (dimensie aanscherpen)",
                "",
                f"**Judgment:** {a17.get('judgment')} (oordeel-as: {a17.get('judgment_oordeel')}, meetlat-as: {a17.get('judgment_meetlat')})",
                "",
            ]
            + [f"- {line}" for line in dim_aanscherpen(a17)]
            or ["- Geen dimensie met oordeel `aanscherpen` in output."]
        )

    summary = run.get("summary") or {}
    base_summary = ""
    delta_note = ""
    if baseline:
        bs = baseline.get("summary") or {}
        base_summary = (
            f"\n**Vergelijking v1.10.8 → v1.11:** goed {bs.get('goed')}→{summary.get('goed')}, "
            f"fout {bs.get('fout')}→{summary.get('fout')}, review {bs.get('review')}→{summary.get('review')}. "
            f"Judgment-wijziging: **T10** goed→fout (hoofdoordeel v1.11), **S01** fout→review.\n"
        )
        delta_note = (
            "\n**A09–A13:** nog steeds fout; meetlat-as **goed** (afgeleide status klopt), "
            "falen op signaal/oordeel (hygiëne/regel-signalen, geen `meetlat_geblokkeerd`-signaal in output).\n"
        )

    header = f"""# HBA ronde 6 — stap 6: ijkrun-uitsplitsing (v1.11)

**Run:** {run.get('generatedAt')} · **Ijkset:** v{run.get('ijkset_versie')} · **Prompt:** {run.get('scoring_prompt_version')}  
**Omgeving:** staging · **Geen wijziging** aan ijkset of judge (meting).  
**Ruwe data:** `{run_path.name}` · **Log:** `hba-ijkrun-nulmeting-v1.11.log`  
**Baseline:** {baseline_path.name if baseline_path else '—'}
{base_summary}{delta_note}
## Telling

| Uitslag | Aantal |
| --- | ---: |
| goed | {summary.get('goed', '—')} |
| fout | {summary.get('fout', '—')} |
| review | {summary.get('review', '—')} |
| niet gedraaid | {summary.get('niet_gedraaid', '—')} |

## Fouten per as (primair)

| As | Aantal |
| --- | ---: |
| oordeel | {len(by_axis['oordeel'])} |
| meetlat | {len(by_axis['meetlat'])} |
| signaal | {len(by_axis['signaal'])} |

## Fout door judge-regel v0.3 vs inhoud

| Categorie | Aantal fout-gevallen |
| --- | ---: |
| Alleen judge-regel (v0.3-rubric) | {len(judge_only_ids)} | {", ".join(judge_only_ids) or "—"} |
| Mix judge + inhoud | {len(mix_ids)} | {", ".join(mix_ids) or "—"} |
| Inhoud (model/score/architectuur) | {len(inhoud_ids)} | {", ".join(inhoud_ids) or "—"} |

**Meetlat afleiding** (verwacht actief/geblokkeerd ≠ `meetlat_status`): {len(meetlat_mismatch_ids)} — {", ".join(meetlat_mismatch_ids) or "—"}.

Heuristiek judge-regel: meetlat-verwacht mismatch, `meetlat_geblokkeerd`-signaal, “alleen melding zonder oordeel”, T13/T14-rubric.

## Alle fout-gevallen

"""
    out_text = header + "\n".join(rows_md) + "\n\n" + a17_block + "\n"
    if out_path:
        out_path.write_text(out_text, encoding="utf-8")
        print(f"Geschreven: {out_path}", file=sys.stderr)
    else:
        print(out_text)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
