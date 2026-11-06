#!/usr/bin/env python3
"""Stap 3a: stap D opnieuw op week-41-artikel, vergelijk merk_beweringen (geen rewrite)."""
from __future__ import annotations

import json
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ARTICLE_ID = "9e73e3fd-caee-11f1-84cf-9c6b00b7cd02"
BASELINE = ROOT / "docs/hba-v1.11-stap3-geen-verzonnen-feiten.json"
OUT = ROOT / "docs/hba-v1.11-stap3a-merkbewering-hermeting.json"


def load_env(path: Path) -> dict[str, str]:
    out: dict[str, str] = {}
    if not path.is_file():
        return out
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        k, v = line.split("=", 1)
        out[k.strip()] = v.strip().strip('"').strip("'")
    return out


def norm_citaat(s: str) -> str:
    return " ".join(s.split())


def match_old_new(old_rows: list[dict], new_rows: list[dict]) -> list[dict]:
    new_by = {norm_citaat(r.get("citaat", "")): r for r in new_rows}
    used_new = set()
    comparisons: list[dict] = []

    for old in old_rows:
        c = norm_citaat(old.get("citaat", ""))
        neu = new_by.get(c)
        if neu is None:
            for nc, nr in new_by.items():
                if nc in c or c in nc:
                    neu = nr
                    break
        entry = {
            "citaat": old.get("citaat"),
            "bronOud": old.get("bron"),
            "bronNieuw": None,
            "steuntOp": None,
            "status": "verwijderd_uit_lijst",
            "reden": "",
        }
        if neu is not None:
            used_new.add(norm_citaat(neu.get("citaat", "")))
            entry["bronNieuw"] = neu.get("bron")
            entry["steuntOp"] = neu.get("steuntOp") or neu.get("steunt_op")
            if entry["bronOud"] == entry["bronNieuw"]:
                entry["status"] = "ongewijzigd"
            else:
                entry["status"] = "label_gewijzigd"
                if entry["bronOud"] == "geen_bron" and entry["bronNieuw"] == "bewijsveld":
                    entry["reden"] = "Vervolgzin op proefochtend; bron uit voorgaande bewijsveld-bewering (context)."
                elif "geen reden voor" in c.lower():
                    entry["reden"] = "Retoriek/standpunt — geen merkbewering (scope)."
                else:
                    entry["reden"] = "Nieuwe definitie merkbewering / context."
        else:
            if "geen reden voor" in c.lower():
                entry["reden"] = "Valt buiten merkbewering (standpunt, geen merkfeit)."
            elif "leg je uit" in c.lower() or "leggen wij uit" in c.lower():
                entry["reden"] = "Vervolg op proefochtend — niet meer als losse geen_bron."
        comparisons.append(entry)

    for neu in new_rows:
        nc = norm_citaat(neu.get("citaat", ""))
        if nc in used_new:
            continue
        comparisons.append(
            {
                "citaat": neu.get("citaat"),
                "bronOud": None,
                "bronNieuw": neu.get("bron"),
                "steuntOp": neu.get("steuntOp") or neu.get("steunt_op"),
                "status": "nieuw_in_lijst",
                "reden": "Nieuwe merkbewering na hermeting.",
            }
        )

    return comparisons


def main() -> int:
    env_path = ROOT / ".env.staging"
    env = load_env(env_path)
    base = env.get("CE_STAGING_API_BASE") or env.get("CE_API_BASE") or "https://staging.igisnext.com/api"
    token = env.get("CE_RESET_TOKEN") or env.get("RESET_TOKEN") or ""
    if not token:
        print("CE_RESET_TOKEN ontbreekt in .env.staging", file=sys.stderr)
        return 1

    if not BASELINE.is_file():
        print(f"Baseline ontbreekt: {BASELINE}", file=sys.stderr)
        return 1

    baseline = json.loads(BASELINE.read_text(encoding="utf-8"))
    old_rows = baseline.get("selfReview", {}).get("merkBeweringen") or []

    url = f"{base.rstrip('/')}/admin/hba-ce03-self-review-rerun"
    payload = {
        "article_id": ARTICLE_ID,
        "persist": False,
        "apply_stap3a_text_fix": True,
    }
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers={"Content-Type": "application/json", "X-CE-Reset-Token": token},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            data = json.load(resp)
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        print(f"HTTP {e.code}: {body}", file=sys.stderr)
        return 1

    if not data.get("ok"):
        print(json.dumps(data, indent=2), file=sys.stderr)
        return 1

    new_review = data.get("selfReview") or {}
    new_rows = new_review.get("merkBeweringen") or []
    unsourced = [r for r in new_rows if (r.get("bron") or "") == "geen_bron"]

    report = {
        "generatedAt": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC"),
        "articleId": ARTICLE_ID,
        "baselineFile": str(BASELINE.relative_to(ROOT)),
        "textFixAppliedInPrompt": True,
        "merkBeweringenVergelijking": match_old_new(old_rows, new_rows),
        "merkBeweringenNieuw": new_rows,
        "unsourcedCount": len(unsourced),
        "ok": len(unsourced) == 0,
        "selfReviewNieuw": new_review,
    }
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Geschreven: {OUT}")
    print(f"ok={report['ok']} unsourced={len(unsourced)} beweringen={len(new_rows)}")
    return 0 if report["ok"] else 2


if __name__ == "__main__":
    sys.exit(main())
