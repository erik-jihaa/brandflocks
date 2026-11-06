#!/usr/bin/env python3
"""Stap 3: Brandpunt week 41 herschrijven op staging en toetsen op verzonnen feiten + je."""
from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SLOT_ID = "d733223a-7964-4324-9883-dcd4b1809006"
CLIENT_ID = "54aeb554-562d-0f0d-9c48-381681a2eb84"

INVENTED = re.compile(
    r"\bop bestelling\b|"
    r"\buitverkocht\b|"
    r"kleine batches|"
    r"\bverstuurt\b|"
    r"aanmeld(?:en|link|lijst)|\bde lijst\b",
    re.IGNORECASE,
)
U_FORM = re.compile(r"\b(u|uw|uwe)\b", re.IGNORECASE)


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


def req(method: str, url: str, token: str, payload: dict | None = None, timeout: float = 180) -> dict:
    headers = {"X-CE-Reset-Token": token}
    data = None
    if payload is not None:
        headers["Content-Type"] = "application/json"
        data = json.dumps(payload).encode("utf-8")
    r = urllib.request.Request(url, data=data, headers=headers, method=method)
    with urllib.request.urlopen(r, timeout=timeout) as resp:
        return json.load(resp)


def article_blob(snap: dict) -> tuple[dict, str]:
    arts = snap.get("articles") or []
    art = arts[0] if arts else {}
    title = (art.get("headline") or "").strip()
    body = (art.get("content_ai") or art.get("content") or "").strip()
    return art, (title + "\n\n" + body).strip()


def judge(text: str, review: dict | None) -> dict:
    invented = sorted(set(m.group(0).lower() for m in INVENTED.finditer(text)))
    uses_u = bool(U_FORM.search(text))
    claims = (review or {}).get("merkBeweringen") or (review or {}).get("merk_beweringen") or []
    unsourced = [
        c
        for c in claims
        if isinstance(c, dict) and (c.get("bron") or "") == "geen_bron"
    ]
    ok = invented == [] and not uses_u and unsourced == [] and len(claims) > 0
    reasons = []
    if invented:
        reasons.append("verzonnen feit: " + ", ".join(invented))
    if uses_u:
        reasons.append("aanspreekvorm u i.p.v. je")
    if unsourced:
        reasons.append(f"{len(unsourced)} merkbewering(en) zonder bron")
    if len(claims) == 0:
        reasons.append("self_review toont geen merk_beweringen")
    return {
        "ok": ok,
        "inventedPhrases": invented,
        "usesU": uses_u,
        "claimCount": len(claims),
        "unsourcedCount": len(unsourced),
        "reasons": reasons,
    }


def main() -> int:
    env = load_env(Path(os.environ.get("CE_ENV_FILE", str(ROOT / ".env.staging"))))
    base = env.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = env.get("CE_RESET_TOKEN", "")
    if not base or not token:
        print("CE_PUBLIC_BASE_URL / CE_RESET_TOKEN ontbreken", file=sys.stderr)
        return 2

    before = req("GET", f"{base}/api/admin/debug-article?slot_id={SLOT_ID}&full=1", token, timeout=60)
    art_before, text_before = article_blob(before)
    hash_before = art_before.get("content_hash") or ""

    print("==> Trigger CE-03 rewrite", flush=True)
    trigger = req(
        "GET",
        f"{base}/api/admin/ce03-phase2-test?client_id={CLIENT_ID}&slot_id={SLOT_ID}&platform=linkedin&trigger=1",
        token,
        timeout=60,
    )
    print(json.dumps({"trigger": trigger.get("trigger"), "articleId": trigger.get("articleId")}), flush=True)

    after = before
    art = art_before
    text = text_before
    deadline = time.time() + int(os.environ.get("HBA_STAP3_WAIT", "240"))
    while time.time() < deadline:
        time.sleep(8)
        after = req("GET", f"{base}/api/admin/debug-article?slot_id={SLOT_ID}&full=1", token, timeout=60)
        art, text = article_blob(after)
        hash_now = art.get("content_hash") or ""
        if hash_now and hash_now != hash_before:
            print(f"    content veranderd na {int(240 - (deadline - time.time()))}s", flush=True)
            break
        print("    … wacht op CE-03", flush=True)
    else:
        print("CE-03 timeout — content_hash ongewijzigd", file=sys.stderr)

    review = art.get("selfReview") or art.get("self_review")
    verdict = judge(text, review if isinstance(review, dict) else None)
    out = {
        "generatedAt": time.strftime("%Y-%m-%d %H:%M:%S UTC", time.gmtime()),
        "slotId": SLOT_ID,
        "articleId": art.get("id"),
        "headline": art.get("headline"),
        "contentHashBefore": hash_before,
        "contentHashAfter": art.get("content_hash"),
        "selfReview": review,
        "article": text,
        **verdict,
    }
    dest = ROOT / "docs" / "hba-v1.11-stap3-geen-verzonnen-feiten.json"
    dest.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {dest}", flush=True)
    print(json.dumps({"ok": verdict["ok"], "reasons": verdict["reasons"]}, ensure_ascii=False), flush=True)
    return 0 if verdict["ok"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
