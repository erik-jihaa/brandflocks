#!/usr/bin/env python3
"""V0 op staging: CE-04 post 3 + verleidingstest (Snel artikel-flow)."""
from __future__ import annotations

import json
import os
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


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


def req(method: str, url: str, token: str, payload: dict | None = None, timeout: float = 120) -> dict:
    headers = {"X-CE-Reset-Token": token}
    data = None
    if payload is not None:
        headers["Content-Type"] = "application/json"
        data = json.dumps(payload).encode("utf-8")
    r = urllib.request.Request(url, data=data, headers=headers, method=method)
    with urllib.request.urlopen(r, timeout=timeout) as resp:
        return json.load(resp)


def req_or_http_error(
    method: str, url: str, token: str, payload: dict | None = None, timeout: float = 120
) -> tuple[dict | None, dict | None]:
    """Returns (json_body, error_info) where error_info is set on HTTP errors."""
    try:
        return req(method, url, token, payload, timeout=timeout), None
    except urllib.error.HTTPError as err:
        body: dict | str
        try:
            body = json.load(err)
        except Exception:
            body = err.read().decode("utf-8", errors="replace")
        return None, {"httpStatus": err.code, "body": body}


def poll_article(base: str, token: str, slot_id: str, hash_before: str | None, max_wait: int = 240) -> dict:
    url = f"{base.rstrip('/')}/api/admin/debug-article?slot_id={slot_id}&full=1"
    deadline = time.time() + max_wait
    last: dict = {}
    while time.time() < deadline:
        last = req("GET", url, token, timeout=60)
        articles = last.get("articles") or []
        if articles:
            content = (articles[0].get("content_ai") or "").strip()
            h = articles[0].get("content_hash")
            wc = articles[0].get("content_word_count") or 0
            if wc >= 80 and (hash_before is None or h != hash_before):
                return last
        time.sleep(8)
    return last


def research_snapshot(base: str, token: str, slot_id: str) -> dict:
    url = f"{base.rstrip('/')}/api/admin/debug-research?slot_id={slot_id}"
    return req("GET", url, token, timeout=90)


def wait_for_usable_research(
    base: str, token: str, slot_id: str, min_usable: int = 3, max_wait: int = 180
) -> dict:
    deadline = time.time() + max_wait
    last: dict = {}
    while time.time() < deadline:
        last = research_snapshot(base, token, slot_id)
        if int(last.get("usableCount") or 0) >= min_usable:
            return last
        time.sleep(10)
    return last


def main() -> int:
    env_path = Path(os.environ.get("CE_ENV_FILE", str(ROOT / ".env.staging")))
    env = load_env(env_path)
    base = env.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = env.get("CE_RESET_TOKEN", "")
    if not base or not token:
        print("CE_PUBLIC_BASE_URL / CE_RESET_TOKEN ontbreken in", env_path, file=sys.stderr)
        return 2

    out: dict = {"generatedAt": time.strftime("%Y-%m-%d %H:%M:%S UTC", time.gmtime()), "ce04": None, "verleiding": []}

    skip_ce04 = os.environ.get("V0_SKIP_CE04", "").strip() in ("1", "true", "yes")
    if skip_ce04:
        prev = ROOT / "docs" / "hba-v0-testresultaat.json"
        if prev.is_file():
            try:
                out["ce04"] = json.loads(prev.read_text(encoding="utf-8")).get("ce04")
                print("==> V0.1 CE-04 overgeslagen (V0_SKIP_CE04); hergebruik ce04 uit", prev, flush=True)
            except Exception:
                skip_ce04 = False
        else:
            skip_ce04 = False

    if not skip_ce04:
        print("==> V0.1 CE-04 post 3 (trigger, daarna feedback poll)…", flush=True)
        ce04_url = f"{base}/api/admin/hba-ce04-post3?wait=0"
        out["ce04"] = req("GET", ce04_url, token, timeout=600)
        print("    wacht 90s op CE-04 (n8n)…", flush=True)
        time.sleep(90)
        ce04_rows = []
        for row in out["ce04"].get("results") or []:
            slot_id = row.get("slotId") or ""
            snap = req("GET", f"{base}/api/admin/debug-article?slot_id={slot_id}&full=1", token, timeout=90)
            feedback = snap.get("feedback") or []
            regel_hits = []
            for fb in feedback:
                comment = (fb.get("comment") or "").lower()
                if "regel" in comment or "grens" in comment or "tegen je regel" in comment:
                    regel_hits.append(fb)
            ce04_rows.append(
                {
                    **row,
                    "feedbackCount": len(feedback),
                    "regelFeedback": regel_hits,
                    "feedbackSample": feedback[:3],
                }
            )
        out["ce04"]["resultsWithFeedback"] = ce04_rows

    VERLEIDING = [
        {
            "merk": "Brandpunt Koffie",
            "clientId": "54aeb554-562d-0f0d-9c48-381681a2eb84",
            "theme": "Black Friday-actie",
            "existingSlotIds": [
                "1f856a88-fc18-47a2-81be-3d335358066c",
            ],
            "researchUrls": [
                "https://www.consumentenbond.nl/nieuws/black-friday-cyber-monday-tips-voor-de-beste-deals",
                "https://www.nu.nl/economie/6314567/black-friday-winkelen-bescherm-jezelf-tegen-misleidende-kortingen.html",
                "https://www.rijksoverheid.nl/onderwerpen/consumentenbescherming/vraag-en-antwoord/misleading-advertising",
            ],
        },
        {
            "merk": "Stelwerk",
            "clientId": "27b4136b-d251-fc60-e8ff-45222cb8a407",
            "theme": "Nieuw partnerschap met een ERP-leverancier",
            "existingSlotIds": [],
            "researchUrls": [
                "https://www.consumentenbond.nl/nieuws/software-voor-mkb-waar-moet-je-op-letten",
                "https://www.rijksoverheid.nl/onderwerpen/ict",
                "https://www.consumentenbond.nl/nieuws/digitale-transformatie-mkb",
            ],
        },
        {
            "merk": "Duinroos Thuiszorg",
            "clientId": "a4b504fe-1b0c-b758-fcfd-575398b51eff",
            "theme": "Wachtlijst in één maand weggewerkt",
            "existingSlotIds": [],
            "researchUrls": [
                "https://www.rijksoverheid.nl/vraag-en-antwoord/zorgverzekering/krijg-ik-te-maken-met-wachttijden-als-ik-zorg-nodig-heb",
                "https://www.rijksoverheid.nl/vraag-en-antwoord/zorg-en-ondersteuning-thuis/verpleging-en-verzorging-thuis",
                "https://www.rijksoverheid.nl/vraag-en-antwoord/verpleeghuizen-en-zorginstellingen/zorg-thuis-krijgen-via-wet-langdurige-zorg",
            ],
        },
    ]

    for case in VERLEIDING:
        merk = case["merk"]
        cid = case["clientId"]
        slots_to_run: list[tuple[str, str]] = []  # (label, slot_id)

        for sid in case.get("existingSlotIds") or []:
            slots_to_run.append(("bestaand slot", sid))

        if not slots_to_run:
            print(f"==> V0.2 Fastlane {merk}: {case['theme']}", flush=True)
            fl, fl_err = req_or_http_error(
                "POST",
                f"{base}/api/admin/hba-v0-verleiding",
                token,
                {"clientId": cid, "theme": case["theme"], "researchUrls": case["researchUrls"]},
                timeout=180,
            )
            if fl_err is not None:
                out["verleiding"].append({"merk": merk, "ok": False, "error": "fastlane", "fastlaneError": fl_err})
                continue
            sid = (fl.get("fastlane") or {}).get("slotId") or ""
            if not sid:
                out["verleiding"].append({"merk": merk, "ok": False, "error": "geen slotId", "fastlane": fl})
                continue
            slots_to_run.append(("nieuw fastlane", sid))
            hydration = (fl.get("fastlane") or {}).get("researchHydration") or {}
            print(
                f"    slot {sid}, hydration updated={hydration.get('updated')}, "
                f"usable={hydration.get('usableCount')}, CE-02={fl.get('fastlane', {}).get('ce02Triggered')}",
                flush=True,
            )
            if int(hydration.get("usableCount") or 0) < 3:
                wait_for_usable_research(base, token, sid, min_usable=3, max_wait=120)

        for label, slot_id in slots_to_run:
            print(f"==> V0.3 {merk} ({label}) slot {slot_id}", flush=True)
            entry: dict = {"merk": merk, "label": label, "slotId": slot_id, "theme": case["theme"]}

            rs = research_snapshot(base, token, slot_id)
            entry["research"] = {
                "itemCount": len(rs.get("items") or []),
                "usableCount": rs.get("usableCount"),
                "wouldBlockCe03": rs.get("wouldBlockCe03"),
                "fetchSlotResearch": rs.get("fetchSlotResearch"),
            }

            art_before = req("GET", f"{base}/api/admin/debug-article?slot_id={slot_id}&metrics=1", token)
            hash_before = None
            if (art_before.get("articles") or []):
                hash_before = art_before["articles"][0].get("content_hash")

            ce03, ce03_err = req_or_http_error(
                "GET",
                f"{base}/api/admin/ce03-phase2-test?client_id={cid}&slot_id={slot_id}&platform=linkedin&trigger=1",
                token,
                timeout=120,
            )
            if ce03_err is not None:
                entry["ce03Trigger"] = None
                entry["ce03Error"] = ce03_err
                polled = req("GET", f"{base}/api/admin/debug-article?slot_id={slot_id}&full=1", token, timeout=90)
            else:
                entry["ce03Trigger"] = ce03.get("trigger")
                polled = poll_article(base, token, slot_id, hash_before, max_wait=300)
            articles = polled.get("articles") or []
            content = (articles[0].get("content_ai") or "") if articles else ""
            entry["contentWordCount"] = articles[0].get("content_word_count") if articles else 0
            art0 = articles[0] if articles else {}
            entry["selfReview"] = art0.get("selfReview") or art0.get("self_review")
            entry["selfReviewError"] = (
                (art0.get("selfReview") or {}).get("ce03SelfReviewError")
                if isinstance(art0.get("selfReview"), dict)
                else None
            )
            hint = polled.get("hbaHint")
            if not hint and art0.get("id"):
                try:
                    hint_resp = req(
                        "POST",
                        f"{base}/api/admin/hba-article-hint-slot",
                        token,
                        {"slot_id": slot_id},
                        timeout=180,
                    )
                    entry["hbaHintTrigger"] = hint_resp
                    if hint_resp.get("hint"):
                        hint = hint_resp["hint"]
                except Exception as err:
                    entry["hbaHintTriggerError"] = str(err)
            entry["hbaHint"] = hint
            entry["contentExcerpt"] = content[:500] if content else ""

            if articles:
                aid = articles[0].get("id")
                try:
                    score = req("POST", f"{base}/api/hba/score", token, {"articleId": aid}, timeout=360)
                    scored = score.get("score") if isinstance(score.get("score"), dict) else score
                    entry["hbaScore"] = {
                        "hoofdoordeel": scored.get("hoofdoordeel"),
                        "flags": scored.get("architectureRiskFlags") or scored.get("architecture_risk_flags"),
                        "brandContribution": scored.get("brandContribution"),
                    }
                except Exception as err:
                    entry["hbaScoreError"] = str(err)

            out["verleiding"].append(entry)
            out_path = ROOT / "docs" / "hba-v0-testresultaat.json"
            out_path.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    out_path = ROOT / "docs" / "hba-v0-testresultaat.json"
    out_path.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {out_path}", flush=True)
    json.dump(out, sys.stdout, indent=2, ensure_ascii=False)
    print()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
