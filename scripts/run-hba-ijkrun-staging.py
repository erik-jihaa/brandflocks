#!/usr/bin/env python3
"""Draai HBA ijkset (v0.3 op staging) nulmeting — één geval per HTTP-request."""
from __future__ import annotations

import json
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path


def get_json(url: str, token: str, timeout: float = 600) -> dict:
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


def main() -> int:
    if len(sys.argv) not in (3, 4):
        print(
            "Usage: run-hba-ijkrun-staging.py BASE_URL TOKEN [OUT.json]",
            file=sys.stderr,
        )
        return 2
    base, token = sys.argv[1], sys.argv[2]
    out_path = Path(sys.argv[3]) if len(sys.argv) == 4 else None
    base = base.rstrip("/")

    inventory = get_json(f"{base}/api/admin/hba-ijkrun", token, timeout=120)
    if not inventory.get("ok"):
        print(json.dumps(inventory, indent=2, ensure_ascii=False))
        return 1

    case_ids: list[str] = []
    for row in inventory.get("cases") or []:
        cid = str(row.get("ijkgeval_id") or "")
        if cid:
            case_ids.append(cid)

    print(f"==> {len(case_ids)} gevallen (inventory)", file=sys.stderr, flush=True)
    results: list[dict] = []
    summary = {"goed": 0, "fout": 0, "review": 0, "niet_gedraaid": 0}

    for i, case_id in enumerate(case_ids, start=1):
        print(f"    [{i}/{len(case_ids)}] {case_id}…", file=sys.stderr, flush=True)
        payload = get_json(
            f"{base}/api/admin/hba-ijkrun?execute=1&id={case_id}",
            token,
            timeout=600,
        )
        if not payload.get("ok"):
            print(json.dumps(payload, indent=2, ensure_ascii=False))
            return 1
        for case in payload.get("cases") or []:
            results.append(case)
            j = str(case.get("judgment") or "")
            if j in summary:
                summary[j] += 1

    aggregate = {
        "ok": True,
        "generatedAt": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC"),
        "ijkset_versie": inventory.get("ijkset_versie"),
        "scoring_prompt_version": inventory.get("scoring_prompt_version"),
        "environment": inventory.get("environment"),
        "summary": summary,
        "counts": inventory.get("counts"),
        "cases": results,
    }

    text = json.dumps(aggregate, indent=2, ensure_ascii=False)
    if out_path:
        out_path.write_text(text + "\n", encoding="utf-8")
        print(f"Geschreven: {out_path}", file=sys.stderr)
    else:
        print(text)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
