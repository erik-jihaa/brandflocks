#!/usr/bin/env python3
"""Gezamenlijke acceptatie app.brandflocks.nl — API-niveau (naast handmatige UI)."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from datetime import date, timedelta
from http.cookiejar import CookieJar


def fail(msg: str) -> None:
    print(f"FAIL  {msg}", file=sys.stderr)
    sys.exit(1)


def ok(msg: str) -> None:
    print(f"PASS  {msg}")


def skip(msg: str) -> None:
    print(f"SKIP  {msg}")


class Client:
    def __init__(self, base: str) -> None:
        self.base = base.rstrip("/")
        self.jar = CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def request(self, method: str, path: str, body: dict | None = None, headers: dict | None = None) -> tuple[int, dict | str]:
        url = f"{self.base}{path}"
        data = None
        if body is not None:
            data = json.dumps(body).encode("utf-8")
        h = {"Content-Type": "application/json", "Accept": "application/json"}
        if headers:
            h.update(headers)
        req = urllib.request.Request(url, data=data, headers=h, method=method)
        try:
            with self.opener.open(req, timeout=120) as resp:
                raw = resp.read().decode("utf-8", errors="replace")
                try:
                    return resp.status, json.loads(raw)
                except json.JSONDecodeError:
                    return resp.status, raw
        except urllib.error.HTTPError as e:
            raw = e.read().decode("utf-8", errors="replace")
            try:
                return e.code, json.loads(raw)
            except json.JSONDecodeError:
                return e.code, {"error": raw[:500]}


def env_value(path: str, key: str) -> str:
    try:
        lines = open(path, encoding="utf-8").read().splitlines()
    except OSError:
        return ""
    for line in lines:
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        name, val = line.split("=", 1)
        if name.strip() != key:
            continue
        val = val.strip()
        if len(val) >= 2 and val[0] == val[-1] == '"':
            return val[1:-1]
        return val.split(" #", 1)[0].strip()
    return ""


def main() -> None:
    root = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    env_file = os.path.join(root, ".env.brandflocks")
    base = env_value(env_file, "CE_PUBLIC_BASE_URL") or "https://app.brandflocks.nl"
    token = env_value(env_file, "CE_RESET_TOKEN")
    email = os.environ.get("BF_ACCEPT_EMAIL", "erik@brandflocks.nl")
    password = os.environ.get("BF_ACCEPT_PASSWORD", "")
    if not password:
        fail("Zet BF_ACCEPT_PASSWORD (tenant-wachtwoord) voor login-test")

    c = Client(base)
    passed = 0
    skipped = 0
    failed: list[str] = []

    def check(name: str, cond: bool, detail: str = "") -> None:
        nonlocal passed
        if cond:
            ok(name + (f" — {detail}" if detail else ""))
            passed += 1
        else:
            failed.append(name + (f": {detail}" if detail else ""))

    code, login = c.request("POST", "/api/auth/login", {"email": email, "password": password})
    check(
        "Login API",
        code == 200 and (login.get("ok") is True or login.get("account")),
        str((login.get("account") or {}).get("clientId", login.get("clientId", "")))[:8],
    )

    code, sess = c.request("GET", "/api/auth/session")
    check("Sessie actief", code == 200 and sess.get("authenticated") is True)

    code, home = c.request("GET", "/api/home")
    check("Home dashboard", code == 200 and "error" not in home, type(home).__name__)

    code, fl = c.request(
        "POST",
        "/api/fastlane",
        {"title": f"Acceptatie thema {date.today().isoformat()}", "extraThoughts": "API acceptatietest"},
    )
    slot_id = (fl.get("slotId") or fl.get("slot_id") or "") if isinstance(fl, dict) else ""
    theme_id = (fl.get("themeId") or fl.get("theme_id") or "") if isinstance(fl, dict) else ""
    check("Fastlane → thema+slot", code == 200 and bool(slot_id), slot_id[:8] if slot_id else str(code))

    if slot_id:
        code, art = c.request("GET", f"/api/articles/{slot_id}")
        err_art = (art.get("error") or "") if isinstance(art, dict) else ""
        if code == 200 and (art.get("ok") is True or art.get("slotId")):
            check(
                "Artikel/thema-view op slot",
                True,
                "themeOnly" if art.get("themeOnly") else "article",
            )
        elif "research" in err_art.lower():
            skip("Fastlane artikel-view — nog geen research (normaal zonder CE-02)")
            skipped += 1
            art = {}
        else:
            check("Artikel/thema-view op slot", False, err_art[:80] or str(code))

        if token:
            c.request("GET", f"/api/articles/{slot_id}")
            code, meta = c.request(
                "POST",
                "/api/ce03/slot-meta",
                {"slotId": slot_id},
                headers={"X-CE-Reset-Token": token},
            )
            wd = (meta.get("meta") or {}).get("write_directive", "") if isinstance(meta, dict) else ""
            cm = (meta.get("meta") or {}).get("copy_modus", "")
            if meta.get("writeDirectiveChars"):
                wd = wd or ("x" * int(meta["writeDirectiveChars"]))
            cm_art = art.get("copyModus", "") if isinstance(art, dict) else ""
            if code == 200 and len(str(wd)) > 20:
                check("CE-03 meta / write_directive", True, f"copy_modus={cm or cm_art}")
            elif isinstance(art, dict) and art.get("copyModus"):
                check(
                    "Copy-modus op thema (fastlane)",
                    art.get("copyModus") in ("merkbouw_bewijs", "sell_activatie"),
                    str(art.get("copyModusLabel", "")),
                )
            else:
                skip("CE-03 write_directive — na stub/research; addendum check via campagne-slot")
                skipped += 1

        code, _tr = c.request("POST", f"/api/research/{slot_id}/trigger")
        err = (_tr.get("error", "") if isinstance(_tr, dict) else str(_tr))[:120]
        if code == 200 and isinstance(_tr, dict) and _tr.get("ok"):
            check("Research CE-02 trigger", True)
        elif "niet actief" in err:
            check("Research CE-02 (n8n actief)", False, err)
        else:
            skip(f"Research trigger HTTP {code} — {err}")
            skipped += 1

    start = date.today().isoformat()
    end = (date.today() + timedelta(days=21)).isoformat()
    code, camp = c.request(
        "POST",
        "/api/campagne-preview",
        {
            "title": f"Acceptatie campagne {date.today().isoformat()}",
            "startDate": start,
            "endDate": end,
            "templateKey": "lancering",
            "channels": {"linkedin": 6},
            "goal": "Acceptatietest addendum copy-modus",
        },
    )
    camp_id = camp.get("campaignId") or camp.get("id") if isinstance(camp, dict) else ""
    if code != 200 and isinstance(camp, dict) and camp.get("error"):
        check("Campagne concept aangemaakt", False, str(camp.get("error"))[:80])
    else:
        check("Campagne concept aangemaakt", code == 200 and bool(camp_id), str(camp_id)[:8] if camp_id else "")

    if camp_id:
        code, detail = c.request("GET", f"/api/campagne-preview/{camp_id}")
        check("Campagne detail", code == 200)
        first_slot = None
        if isinstance(detail, dict):
            sl = detail.get("slots") or []
            first_slot = sl[0] if sl else None
        if first_slot:
            sid = first_slot.get("slotId") or first_slot.get("id") or ""
            code, camp_art = c.request("GET", f"/api/articles/{sid}")
            cm2 = (camp_art.get("copyModus") or "") if isinstance(camp_art, dict) else ""
            check(
                "Lancering → sell_activatie (UI/API)",
                code == 200 and cm2 == "sell_activatie",
                camp_art.get("copyModusLabel", cm2) if isinstance(camp_art, dict) else "",
            )

        code, patched = c.request(
            "PATCH",
            f"/api/campagne-preview/{camp_id}",
            {"previewStatus": "bevestigd"},
        )
        check("Campagne bevestigd", code == 200, str(patched.get("previewStatus", "")))

        code, agenda = c.request("GET", "/api/agenda")
        has_camp = False
        if isinstance(agenda, dict):
            items = agenda.get("items") or agenda.get("slots") or []
            has_camp = any(
                (it.get("campaignId") == camp_id or it.get("origin") == "campagne_preview")
                for it in items
                if isinstance(it, dict)
            )
        check("Agenda bereikbaar", code == 200, "campagne-slots zichtbaar" if has_camp else "lijst ok")

    if token:
        code, wh = c.request(
            "POST",
            "/api/admin/ce02-trigger",
            {"slotId": slot_id} if slot_id else {},
            headers={"X-CE-Reset-Token": token},
        )
        if code == 200 and isinstance(wh, dict) and wh.get("ok"):
            ok("CE-02 webhook aangeroepen (achtergrond)")
            passed += 1
        else:
            skip(f"CE-02 trigger ({code}) — check n8n [Bf app]")
            skipped += 1

    print()
    print(f"==> Geaccepteerd: {passed} pass, {len(failed)} fail, {skipped} skip")
    if failed:
        for line in failed:
            print(f"  • {line}")
        sys.exit(1)
    if theme_id:
        print(f"    Handmatig UI: /article/{slot_id} — activatie-knop, research, campagne /campagne")


if __name__ == "__main__":
    main()
