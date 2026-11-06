#!/usr/bin/env python3
"""Stap 2: 5× hint forceren (Brandpunt-artikel + drie V0-slots) en regel-letterlijk toetsen."""
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

TARGETS = [
    {
        "label": "Brandpunt artikel week 41",
        "slotId": "d733223a-7964-4324-9883-dcd4b1809006",
        "expectRuleMention": True,
        "aanspreekvorm": "je",
    },
    {
        "label": "V0 Brandpunt",
        "slotId": "1f856a88-fc18-47a2-81be-3d335358066c",
        "expectRuleMention": False,
        "aanspreekvorm": "je",
    },
    {
        "label": "V0 Stelwerk",
        "slotId": "b5b00e3e-15e0-40a9-a5e5-518f0b6715a2",
        "expectRuleMention": False,
        "aanspreekvorm": "",
    },
    {
        "label": "V0 Duinroos",
        "slotId": "06754e6b-eba1-4ac6-81a8-e116b07d29ba",
        "expectRuleMention": False,
        "aanspreekvorm": "",
    },
]

DERIVED = re.compile(
    r"\b(nadert|naderen|naderde|naderend)\b|"
    r"\bbijna\s+(zes|6)\b|"
    r"\brond\s+(de\s+)?(zes|6)\b|"
    r"grens van zes weken",
    re.IGNORECASE,
)
LITERAL_ZES = re.compile(
    r"(ouder|langer)\s+dan\s+zes\s+weken",
    re.IGNORECASE,
)
MENTIONS_ZES = re.compile(r"\b(zes|6)\s+weken\b", re.IGNORECASE)


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


def hint_blob(hint: dict) -> str:
    parts = [
        hint.get("aanwijzing"),
        hint.get("regelOvertreden"),
        hint.get("passageVoor"),
        hint.get("passageNa"),
        hint.get("headlineNa"),
        hint.get("ctaNa"),
    ]
    return "\n".join(p for p in parts if isinstance(p, str) and p.strip())


def normalize_cite(text: str) -> str:
    text = text.lower()
    for ch in ['"', "'", "«", "»", "“", "”", "‘", "’"]:
        text = text.replace(ch, "")
    return re.sub(r"\s+", " ", text).strip()


def extract_quotes(text: str) -> list[str]:
    pats = [
        r'"([^"\n]{8,400})"',
        r"'([^'\n]{8,400})'",
        r"“([^”\n]{8,400})”",
        r"„([^“\n]{8,400})“",
        r"‘([^’\n]{8,400})’",
        r"«([^»\n]{8,400})»",
    ]
    found: list[str] = []
    for pat in pats:
        for frag in re.findall(pat, text):
            t = frag.strip()
            if t and re.search(r"\s", t):
                found.append(t)
    return found


def fetch_article(base: str, token: str, slot_id: str) -> str:
    snap = req("GET", f"{base}/api/admin/debug-article?slot_id={slot_id}&full=1", token, timeout=60)
    arts = snap.get("articles") or []
    if not arts:
        return ""
    art = arts[0]
    title = (art.get("title") or art.get("headline") or "").strip()
    body = (art.get("content_ai") or art.get("content") or "").strip()
    return (title + "\n\n" + body).strip()


def judge(hint: dict, article: str, address: str, expect_rule: bool) -> dict:
    text = hint_blob(hint)
    derived = bool(DERIVED.search(text))
    mentions = bool(MENTIONS_ZES.search(text))
    literal = bool(LITERAL_ZES.search(text))
    quotes = extract_quotes(
        "\n".join(
            p
            for p in [hint.get("aanwijzing"), hint.get("regelOvertreden")]
            if isinstance(p, str)
        )
    )
    passage_voor = (hint.get("passageVoor") or "").strip()
    if passage_voor:
        quotes.append(passage_voor)
    article_norm = normalize_cite(article)
    quote_not_in = any(normalize_cite(q) not in article_norm for q in quotes if normalize_cite(q))
    placeholder = bool(re.search(r"\[[^\[\]]{3,200}\]", (hint.get("passageNa") or "") + (hint.get("aanwijzing") or "")))
    passage = "\n".join(
        p
        for p in [hint.get("passageNa"), hint.get("ctaNa"), hint.get("headlineNa")]
        if isinstance(p, str)
    )
    wrong_address = False
    if address == "je":
        wrong_address = bool(re.search(r"\b(u|uw|uwe)\b", passage, re.IGNORECASE))
    elif address == "u":
        wrong_address = bool(re.search(r"\b(je|jij|jou|jouw)\b", passage, re.IGNORECASE))
    ok = not derived
    if mentions:
        ok = ok and literal
    if expect_rule and mentions and not literal:
        ok = False
    if quote_not_in or wrong_address or placeholder:
        ok = False
    reasons = []
    if derived:
        reasons.append("afgeleide grens (nadert/rond/bijna/grens van zes weken)")
    if mentions and not literal:
        reasons.append("zes weken genoemd zonder letterlijke regel")
    if quote_not_in:
        reasons.append("citaat niet in brontekst")
    if wrong_address:
        reasons.append("aanspreekvorm wijkt af")
    if placeholder:
        reasons.append("placeholder in lopende tekst")
    return {
        "ok": ok,
        "derivedBoundary": derived,
        "quoteNotInSource": quote_not_in,
        "wrongAddress": wrong_address,
        "placeholder": placeholder,
        "mentionsZesWeken": mentions,
        "literalZesWeken": literal,
        "reasons": reasons,
    }


def main() -> int:
    env = load_env(Path(os.environ.get("CE_ENV_FILE", str(ROOT / ".env.staging"))))
    base = env.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = env.get("CE_RESET_TOKEN", "")
    if not base or not token:
        print("CE_PUBLIC_BASE_URL / CE_RESET_TOKEN ontbreken", file=sys.stderr)
        return 2

    runs = int(os.environ.get("HBA_STAP2_RUNS", "5"))
    out = {
        "generatedAt": time.strftime("%Y-%m-%d %H:%M:%S UTC", time.gmtime()),
        "promptVersion": "v1.11",
        "runsPerTarget": runs,
        "targets": [],
    }
    all_ok = True
    url = f"{base}/api/admin/hba-article-hint-slot"

    for target in TARGETS:
        row = {
            "label": target["label"],
            "slotId": target["slotId"],
            "runs": [],
            "okCount": 0,
        }
        print(f"==> {target['label']} ({runs}×)", flush=True)
        article = fetch_article(base, token, target["slotId"])
        for i in range(1, runs + 1):
            print(f"    run {i}/{runs}…", flush=True)
            try:
                resp = req("POST", url, token, {"slot_id": target["slotId"], "force": True})
            except urllib.error.HTTPError as err:
                body = err.read().decode("utf-8", errors="replace")
                row["runs"].append({"run": i, "ok": False, "error": body})
                all_ok = False
                continue
            hint = resp.get("hint") or {}
            verdict = judge(hint, article, target.get("aanspreekvorm") or "", target["expectRuleMention"])
            if verdict["ok"]:
                row["okCount"] += 1
            else:
                all_ok = False
            row["runs"].append(
                {
                    "run": i,
                    "articleId": resp.get("articleId"),
                    "aanwijzing": hint.get("aanwijzing"),
                    "regelOvertreden": hint.get("regelOvertreden"),
                    "passageNa": hint.get("passageNa"),
                    "ctaNa": hint.get("ctaNa"),
                    "invulvelden": hint.get("invulvelden"),
                    "error": resp.get("error"),
                    **verdict,
                }
            )
        row["pass"] = row["okCount"] == runs
        out["targets"].append(row)
        print(f"    {row['okCount']}/{runs}", flush=True)

    dest_name = (
        "hba-v1.11-stap2a-citaatcontrole.json"
        if os.environ.get("HBA_STAP2A", "1").strip() not in ("0", "false")
        else "hba-v1.11-stap2-hints-5runs.json"
    )
    dest = ROOT / "docs" / dest_name
    dest.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {dest}", flush=True)
    return 0 if all_ok else 1


if __name__ == "__main__":
    raise SystemExit(main())
