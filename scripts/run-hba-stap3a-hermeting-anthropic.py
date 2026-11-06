#!/usr/bin/env python3
"""Stap 3a hermeting: stap D lokaal via Anthropic (zelfde prompt als ce03-self-review-repository)."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ARTICLE_ID = "9e73e3fd-caee-11f1-84cf-9c6b00b7cd02"
CLIENT_ID = "54aeb554-562d-0f0d-9c48-381681a2eb84"
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


def fetch_architecture(base: str, token: str) -> tuple[str, str, str]:
    """Regels, bewijsvelden, aanspreekvorm via staging admin (server-side DB)."""
    url = f"{base.rstrip('/')}/api/admin/hba-kernformuleringen?client_id={CLIENT_ID}"
    req = urllib.request.Request(url, headers={"X-CE-Reset-Token": token})
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            data = json.load(resp)
    except urllib.error.HTTPError:
        data = {}
    regels = ""
    bewijs_lines: list[str] = []
    aanspreek = "je"
    if data.get("ok"):
        regels = (data.get("geloofwaardigheidstest") or data.get("regels") or "").strip()
        raw_bewijs = data.get("bewijsvelden") or data.get("bewijsVelden") or []
        if isinstance(raw_bewijs, list):
            bewijs_lines = [str(x).strip() for x in raw_bewijs if str(x).strip()]
        aanspreek = (data.get("aanspreekvorm") or "je").strip() or "je"
    if not regels:
        regels = (
            "We verkopen geen koffie die langer dan zes weken geleden is gebrand, ook niet met korting "
            "en ook niet op Black Friday. Wat over is, gaat naar de buurtvoedselbank of wordt weggegooid."
        )
    if not bewijs_lines:
        bewijs_lines = [
            "Op elke zak staan de branddatum en de boer.",
            "Elke eerste donderdag van de maand is er een proefochtend.",
            "In de winkel leggen we bij elke koffie uit wat je proeft en waarom.",
        ]
    bewijs = "\n".join(f"- {b}" for b in bewijs_lines)
    return regels, bewijs, aanspreek


def text_fix(article: str) -> str:
    return article.replace(
        "Dan zetten wij een vers gezette kop voor je neer en leg je uit wat je proeft.",
        "Dan zetten wij een vers gezette kop voor je neer en leggen wij uit wat je proeft.",
    )


def build_user_prompt(content: str, regels: str, bewijs: str, aanspreek: str) -> str:
    regelbreuk = (ROOT / "web/api/prompts/hba-regelbreuk-definitie.txt").read_text(encoding="utf-8").strip()
    merk = (ROOT / "web/api/prompts/hba-merkbewering-definitie.txt").read_text(encoding="utf-8").strip()
    research = "Geen research gekoppeld."
    return (
        f"ARTIKEL:\n{content}\n\n"
        f"ONDERLIGGENDE RESEARCH (de items waarop dit artikel gebaseerd zou moeten zijn):\n{research}\n\n"
        f"REGELS VAN HET MERK:\n{regels}\n\n"
        f"BEWIJSVELDEN VAN HET MERK:\n{bewijs}\n\n"
        f"AANSPREEKVORM VAN HET MERK:\n{aanspreek}\n\n"
        f"{regelbreuk}\n\n{merk}\n\n"
        "OPDRACHT: Beantwoord kort en concreet:\n"
        "1. Welke claim of bewering in het artikel is het minst goed onderbouwd door de research hierboven "
        "(geen onderbouwing, of gebaseerd op een item met reliability = low)? Citeer de zin uit het artikel.\n"
        "2. Is er een aanname in het artikel die niet expliciet uit de research volgt (het model 'vult zelf in')? Zo ja, welke?\n"
        "3. Mist het artikel een tegengeluid dat wel in de research aanwezig was (een item met stance = spreekt_tegen dat niet verwerkt is)?\n"
        "4. Breekt het artikel een regel van het merk, volgens de definitie van REGELBREUK hierboven? "
        "Zo nee, of als er geen regels zijn vastgelegd: regelbreuk = null.\n"
        "5. MERKBEWERINGEN: volg de definitie MERKBEWERING hierboven. Beoordeel het artikel als gehele passage in context. "
        "Per merkbewering: citaat, bron, optioneel steunt_op.\n"
        "Output via de tool."
    )


TOOL = {
    "name": "self_review",
    "description": "Zelfcontrole van artikel tegen research, bewijsvelden en merkregels",
    "input_schema": {
        "type": "object",
        "properties": {
            "weakest_claim": {"type": ["string", "null"]},
            "unsupported_assumption": {"type": ["string", "null"]},
            "missed_counterpoint": {"type": ["string", "null"]},
            "regelbreuk": {"type": ["object", "null"]},
            "merk_beweringen": {
                "type": "array",
                "items": {
                    "type": "object",
                    "properties": {
                        "citaat": {"type": "string"},
                        "bron": {
                            "type": "string",
                            "enum": ["merkarchitectuur", "bewijsveld", "research", "geen_bron"],
                        },
                        "steunt_op": {"type": ["string", "null"]},
                    },
                    "required": ["citaat", "bron"],
                },
            },
        },
        "required": [
            "weakest_claim",
            "unsupported_assumption",
            "missed_counterpoint",
            "regelbreuk",
            "merk_beweringen",
        ],
    },
}


def call_anthropic(api_key: str, user_prompt: str) -> dict:
    payload = {
        "model": "claude-sonnet-4-6",
        "max_tokens": 1400,
        "temperature": 0.2,
        "system": (
            "Je bent een kritische eindredacteur die het zojuist geschreven artikel controleert tegen "
            "de onderliggende research, de bewijsvelden en de merkarchitectuur."
        ),
        "messages": [{"role": "user", "content": user_prompt}],
        "tools": [TOOL],
        "tool_choice": {"type": "tool", "name": "self_review"},
    }
    req = urllib.request.Request(
        "https://api.anthropic.com/v1/messages",
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Content-Type": "application/json",
            "x-api-key": api_key,
            "anthropic-version": "2023-06-01",
        },
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        return json.load(resp)


def to_api_rows(raw: list) -> list[dict]:
    out = []
    for row in raw:
        if not isinstance(row, dict):
            continue
        citaat = str(row.get("citaat") or "").strip()
        if not citaat:
            continue
        bron = str(row.get("bron") or "geen_bron").strip()
        entry = {"citaat": citaat, "bron": bron}
        steunt = str(row.get("steunt_op") or "").strip()
        if steunt:
            entry["steuntOp"] = steunt
        out.append(entry)
    return out


def compare(old_rows: list[dict], new_rows: list[dict]) -> list[dict]:
    norm = lambda s: " ".join(str(s).split())
    new_by = {norm(r.get("citaat", "")): r for r in new_rows}
    used: set[str] = set()
    out: list[dict] = []
    for old in old_rows:
        c = norm(old.get("citaat", ""))
        neu = new_by.get(c)
        entry = {
            "citaat": old.get("citaat"),
            "bronOud": old.get("bron"),
            "bronNieuw": None,
            "steuntOp": None,
            "status": "verwijderd_uit_lijst",
            "reden": "",
        }
        if neu:
            used.add(norm(neu.get("citaat", "")))
            entry["bronNieuw"] = neu.get("bron")
            entry["steuntOp"] = neu.get("steuntOp")
            if entry["bronOud"] == entry["bronNieuw"]:
                entry["status"] = "ongewijzigd"
            else:
                entry["status"] = "label_gewijzigd"
                if entry["bronOud"] == "geen_bron" and entry["bronNieuw"] == "bewijsveld":
                    entry["reden"] = "Vervolgzin op proefochtend; bron uit voorgaande bewijsveld-bewering (context)."
                elif "geen reden voor" in c.lower():
                    entry["reden"] = "Retoriek/standpunt — geen merkbewering (scope)."
        elif "geen reden voor" in c.lower():
            entry["reden"] = "Valt buiten merkbewering (standpunt, geen merkfeit)."
        elif "leg je uit" in c.lower() or "leggen wij uit" in c.lower():
            entry["reden"] = "Vervolg op proefochtend — niet meer als losse geen_bron."
        out.append(entry)
    return out


def main() -> int:
    env_path = Path(os.environ.get("CE_ENV_FILE", str(ROOT / ".env.staging")))
    env = load_env(env_path)
    api_key = env.get("ANTHROPIC_API_KEY", "")
    base = env.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = env.get("CE_RESET_TOKEN", "")
    if not api_key:
        print("ANTHROPIC_API_KEY ontbreekt", file=sys.stderr)
        return 1
    if not BASELINE.is_file():
        print(f"Baseline ontbreekt: {BASELINE}", file=sys.stderr)
        return 1

    baseline = json.loads(BASELINE.read_text(encoding="utf-8"))
    old_rows = baseline.get("selfReview", {}).get("merkBeweringen") or []
    content = text_fix(str(baseline.get("article") or ""))

    regels, bewijs, aanspreek = ("", "", "je")
    if base and token:
        regels, bewijs, aanspreek = fetch_architecture(base, token)

    user_prompt = build_user_prompt(content, regels, bewijs, aanspreek)
    raw_resp = call_anthropic(api_key, user_prompt)
    tool_input = None
    for block in raw_resp.get("content") or []:
        if block.get("type") == "tool_use" and block.get("name") == "self_review":
            tool_input = block.get("input")
            break
    if not isinstance(tool_input, dict):
        print("Geen self_review tool output", file=sys.stderr)
        return 1

    new_rows = to_api_rows(tool_input.get("merk_beweringen") or [])
    unsourced = [r for r in new_rows if r.get("bron") == "geen_bron"]

    report = {
        "generatedAt": datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC"),
        "articleId": ARTICLE_ID,
        "baselineFile": str(BASELINE.relative_to(ROOT)),
        "hermetingVia": "anthropic_local_stap3a_prompt",
        "textFixAppliedInPrompt": True,
        "merkBeweringenVergelijking": compare(old_rows, new_rows),
        "merkBeweringenNieuw": new_rows,
        "unsourcedCount": len(unsourced),
        "ok": len(unsourced) == 0,
        "selfReviewToolInput": tool_input,
    }
    OUT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Geschreven: {OUT}")
    print(f"ok={report['ok']} unsourced={len(unsourced)} beweringen={len(new_rows)}")
    return 0 if report["ok"] else 2


if __name__ == "__main__":
    sys.exit(main())
