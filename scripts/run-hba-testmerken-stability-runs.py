#!/usr/bin/env python3
"""Score elke HBA-testpost N keer (één HTTP-request per post) en aggregeer stabiliteit."""
from __future__ import annotations

import json
import sys
import urllib.error
import urllib.request
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path


def post_json(url: str, token: str, payload: dict, timeout: float = 360) -> dict:
    last_err: Exception | None = None
    for attempt in range(1, 4):
        try:
            req = urllib.request.Request(
                url,
                data=json.dumps(payload).encode("utf-8"),
                headers={"Content-Type": "application/json", "X-CE-Reset-Token": token},
                method="POST",
            )
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                return json.load(resp)
        except (TimeoutError, urllib.error.URLError, urllib.error.HTTPError) as err:
            last_err = err
            print(f"      retry {attempt}/3 na fout: {err}", file=sys.stderr, flush=True)
    raise RuntimeError(f"POST mislukt na 3 pogingen: {last_err}") from last_err


def get_json(url: str, token: str, timeout: float = 180) -> dict:
    last_err: Exception | None = None
    for attempt in range(1, 4):
        try:
            req = urllib.request.Request(url, headers={"X-CE-Reset-Token": token})
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                return json.load(resp)
        except (TimeoutError, urllib.error.URLError, urllib.error.HTTPError) as err:
            last_err = err
            print(f"GET retry {attempt}/3: {err}", file=sys.stderr, flush=True)
    raise RuntimeError(f"GET mislukt: {last_err}") from last_err


def constraint_breach_stats(run_matrices: list[dict]) -> dict:
    """Tel constraint_breach over alle run-snapshots (één rij = één score)."""
    kept = 0
    rejected = 0
    dropped_no_uitleg = 0
    for matrix in run_matrices:
        for row in matrix.get("rows") or []:
            flags = row.get("flags") or []
            removed = row.get("flags_verwijderd") or []
            for item in removed:
                if (item.get("flag") or "") != "constraint_breach":
                    continue
                reason = (item.get("reason") or "").lower()
                if "verificatie=nee" in reason:
                    rejected += 1
                elif "uitleg" in reason:
                    dropped_no_uitleg += 1
            if "constraint_breach" in flags:
                kept += 1
    return {
        "geverifieerdBehouden": kept,
        "verworpenDoorVerificatie": rejected,
        "vervallenGeenUitleg": dropped_no_uitleg,
    }


def aggregate(run_matrices: list[dict], runs: int) -> dict:
    by_key: dict[tuple[str, str], list[dict]] = defaultdict(list)
    for matrix in run_matrices:
        for row in matrix.get("rows") or []:
            key = (str(row.get("merk") or ""), str(row.get("label") or ""))
            by_key[key].append(row)

    rows = []
    unstable = []
    correction_count = 0
    volgt_flags_count = 0
    category_fallback_last = 0
    total_scored = 0
    functional_flag_hits: list[dict] = []

    for (merk, label), run_rows in sorted(by_key.items()):
        if len(run_rows) < runs:
            print(f"Warning: {merk} / {label} heeft maar {len(run_rows)} runs", file=sys.stderr)
        ambiguous = any(bool(r.get("ambiguous")) for r in run_rows)
        good_runs = sum(1 for r in run_rows if (r.get("judgment") or "") == "goed")
        bc_vals = [
            float(r["brandContribution"])
            for r in run_rows
            if r.get("brandContribution") is not None
        ]
        spread = (max(bc_vals) - min(bc_vals)) if bc_vals else None
        hoofd_per_run = [r.get("hoofdoordeel") for r in run_rows]
        for r in run_rows:
            if r.get("brandContribution") is not None:
                total_scored += 1
            if r.get("hoofdoordeel_gecorrigeerd"):
                correction_count += 1
            if r.get("hoofdoordeel_volgt_uit_flags") is True:
                volgt_flags_count += 1
            for hit in r.get("functionele_flag_hits") or []:
                functional_flag_hits.append(
                    {
                        "merk": merk,
                        "label": label,
                        "flag": hit.get("flag"),
                        "citaat": hit.get("citaat"),
                    }
                )
        last = run_rows[-1] if run_rows else {}
        flags = last.get("flags") or []
        if "category_fallback" in flags:
            category_fallback_last += 1

        stable_good = good_runs == runs and not ambiguous
        if ambiguous:
            judgment = "review"
        elif stable_good:
            judgment = "goed"
        elif good_runs == 0:
            judgment = "fout"
        else:
            judgment = "instabiel"

        row = {
            "merk": merk,
            "label": label,
            "verwacht_hoofdoordeel": run_rows[0].get("verwacht_hoofdoordeel") if run_rows else None,
            "ambiguous": ambiguous,
            "goodRuns": good_runs,
            "runsTotal": runs,
            "stableGood": stable_good,
            "hoofdoordeelPerRun": hoofd_per_run,
            "brandContributionSpread": spread,
            "judgment": judgment,
            "index": run_rows[0].get("index") if run_rows else None,
            "runs": [
                {
                    "run": i + 1,
                    "judgment": r.get("judgment"),
                    "reasons": r.get("reasons") or [],
                    "brandContribution": r.get("brandContribution"),
                    "distinctiviteit": r.get("distinctiviteit"),
                    "flags": r.get("flags") or [],
                    "flags_verwijderd": r.get("flags_verwijderd") or [],
                    "flag_citaten": r.get("flag_citaten") or {},
                    "hoofdoordeel": r.get("hoofdoordeel"),
                    "hoofdoordeel_gecorrigeerd": r.get("hoofdoordeel_gecorrigeerd"),
                    "hoofdoordeel_volgt_uit_flags": r.get("hoofdoordeel_volgt_uit_flags"),
                    "kern_categorietaal": r.get("kern_categorietaal"),
                    "merkbewering": r.get("merkbewering"),
                    "warnings": r.get("warnings") or [],
                    "functionele_flag_hits": r.get("functionele_flag_hits") or [],
                }
                for i, r in enumerate(run_rows)
            ],
        }
        rows.append(row)
        if not stable_good and not ambiguous:
            deviating = []
            for i, r in enumerate(run_rows):
                if (r.get("judgment") or "") != "goed":
                    deviating.append(
                        {
                            "run": i + 1,
                            "judgment": r.get("judgment"),
                            "hoofdoordeel": r.get("hoofdoordeel"),
                            "flags": r.get("flags") or [],
                            "flag_citaten": r.get("flag_citaten") or {},
                            "reasons": r.get("reasons") or [],
                            "warnings": r.get("warnings") or [],
                        }
                    )
            unstable.append(
                {
                    "merk": merk,
                    "label": label,
                    "goodRuns": good_runs,
                    "hoofdoordeelPerRun": hoofd_per_run,
                    "deviatingRuns": deviating,
                }
            )

    summary = {"stable_goed": 0, "instabiel": 0, "review": 0, "fout": 0}
    for row in rows:
        j = row["judgment"]
        if j == "goed":
            summary["stable_goed"] += 1
        elif j == "review":
            summary["review"] += 1
        elif j == "instabiel":
            summary["instabiel"] += 1
        else:
            summary["fout"] += 1

    prompt_version = "onbekend"
    models: set[str] = set()
    for matrix in run_matrices:
        pv = str(matrix.get("codePromptVersion") or matrix.get("promptVersion") or "")
        if pv:
            prompt_version = pv.split("+")[0]
        for m in matrix.get("modelsUsed") or []:
            models.add(str(m))

    cb_stats = constraint_breach_stats(run_matrices)

    return {
        "ok": True,
        "generatedAt": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC"),
        "promptVersion": prompt_version,
        "runs": runs,
        "modelsUsed": sorted(models),
        "environment": run_matrices[-1].get("environment") if run_matrices else "staging",
        "summary": summary,
        "categoryFallbackPostsLastRun": category_fallback_last,
        "totalScoreCount": total_scored,
        "hoofdoordeelGecorrigeerdCount": 0,
        "hoofdoordeelGecorrigeerdPercent": 0.0,
        "hoofdoordeelVolgtUitFlagsCount": volgt_flags_count,
        "hoofdoordeelVolgtUitFlagsPercent": round(100 * volgt_flags_count / total_scored, 1)
        if total_scored
        else 0.0,
        "constraintBreachStats": cb_stats,
        "flagsOpFunctioneleOnderdelen": functional_flag_hits,
        "unstablePosts": unstable,
        "rows": rows,
        "runMatrices": run_matrices,
    }


def main() -> int:
    if len(sys.argv) != 4:
        print("Usage: run-hba-testmerken-stability-runs.py BASE_URL TOKEN RUNS", file=sys.stderr)
        return 2
    base, token, runs_s = sys.argv[1:4]
    runs = max(1, min(10, int(runs_s)))
    base = base.rstrip("/")

    matrix = get_json(f"{base}/api/admin/hba-testmerken-matrix", token)
    if not matrix.get("ok"):
        print(json.dumps(matrix, indent=2, ensure_ascii=False))
        return 1

    targets = []
    for row in matrix.get("rows") or []:
        targets.append(
            {
                "merk": row.get("merk"),
                "clientId": row.get("clientId"),
                "index": int(row.get("index") or 0),
            }
        )

    run_matrices: list[dict] = []
    for run in range(1, runs + 1):
        print(f"==> Run {run}/{runs}: {len(targets)} posts scoren…", file=sys.stderr, flush=True)
        for t in targets:
            merk = t["merk"]
            idx = t["index"]
            cid = t["clientId"]
            print(f"    {merk} post {idx}…", file=sys.stderr, flush=True)
            post_json(
                f"{base}/api/admin/import-hba-testposts",
                token,
                {
                    "testmerk": {"merknaam": merk},
                    "clientId": cid,
                    "scoreIndex": idx,
                },
            )
        snap = get_json(f"{base}/api/admin/hba-testmerken-matrix", token)
        if not snap.get("ok"):
            print(json.dumps(snap, indent=2, ensure_ascii=False))
            return 1
        run_matrices.append(snap)

    payload = aggregate(run_matrices, runs)
    json.dump(payload, sys.stdout, indent=2, ensure_ascii=False)
    print()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
