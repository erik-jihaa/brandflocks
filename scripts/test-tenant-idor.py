#!/usr/bin/env python3
"""Tenant-IDOR tests — de paden uit de codebeoordeling van 29 sep 2026.

Vult test-tenant-isolation.py aan: die test client_id-scoping, deze test of een
resource-ID van een andere tenant (theme, slot, artikel, research, bron) nog
bruikbaar is, en of de sessie via /sources/inspiration omgezet kan worden.

Usage:
  ./scripts/test-tenant-idor.py staging
"""
from __future__ import annotations

import json
import os
import secrets
import sys
import urllib.error
import urllib.request
from http.cookiejar import CookieJar
from pathlib import Path
from typing import Any
from urllib.parse import urljoin

sys.path.insert(0, str(Path(__file__).resolve().parent))

from n8n_env import load_env  # noqa: E402

PASS = 0
FAIL = 0
SKIP = 0


def ok(msg: str) -> None:
    global PASS
    PASS += 1
    print(f"  \033[32mPASS\033[0m  {msg}")


def bad(msg: str) -> None:
    global FAIL
    FAIL += 1
    print(f"  \033[31mFAIL\033[0m  {msg}")


def skipped(msg: str) -> None:
    global SKIP
    SKIP += 1
    print(f"  \033[33mSKIP\033[0m  {msg}")


class Client:
    def __init__(self, base: str, token: str = "") -> None:
        self.base = base.rstrip("/")
        self.token = token
        self.opener = urllib.request.build_opener(
            urllib.request.HTTPCookieProcessor(CookieJar())
        )

    def request(
        self,
        method: str,
        path: str,
        body: dict | None = None,
        *,
        admin: bool = False,
    ) -> tuple[int, dict[str, Any]]:
        url = urljoin(self.base + "/", path.lstrip("/"))
        headers = {"Accept": "application/json"}
        if admin and self.token:
            headers["X-CE-Reset-Token"] = self.token
        data = None
        if body is not None:
            data = json.dumps(body).encode("utf-8")
            headers["Content-Type"] = "application/json"
        req = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with self.opener.open(req, timeout=90) as resp:
                status, raw = resp.getcode(), resp.read().decode("utf-8")
        except urllib.error.HTTPError as exc:
            status, raw = exc.code, exc.read().decode("utf-8", errors="replace")
        payload: dict[str, Any] = {}
        if raw:
            try:
                parsed = json.loads(raw)
                payload = parsed if isinstance(parsed, dict) else {"data": parsed}
            except json.JSONDecodeError:
                payload = {"error": raw[:200]}
        return status, payload

    def login(self, email: str, password: str) -> None:
        status, payload = self.request(
            "POST", "/api/auth/login", {"email": email, "password": password}
        )
        if status != 200 or not payload.get("ok"):
            raise RuntimeError(f"Login mislukt voor {email}: {payload}")


def ensure_tenant(admin: Client, email: str, label: str) -> tuple[str, str]:
    password = f"Idor-{secrets.token_urlsafe(12)}!"
    _, clients = admin.request(
        "GET", "/api/admin/list-slots?list_clients=1", admin=True
    )
    for row in clients.get("clients") or []:
        if str(row.get("contact_email") or "").lower() == email.lower():
            client_id = str(row["id"])
            admin.request(
                "POST",
                "/api/admin/set-password",
                {"clientId": client_id, "password": password},
                admin=True,
            )
            return client_id, password

    _, created = admin.request(
        "POST",
        "/api/admin/create-client",
        {
            "company": f"IDOR {label}",
            "contactEmail": email,
            "loginEmail": email,
            "password": password,
            "postsPerWeek": 1,
            "publishMode": "manual_with_review",
            "writingGuide": "IDOR-test tenant — geen klantdata.",
        },
        admin=True,
    )
    client = created.get("client") or {}
    client_id = str(
        created.get("clientId") or client.get("clientId") or client.get("id") or ""
    )
    if not client_id:
        raise RuntimeError(f"create-client zonder id voor {email}: {created}")
    return client_id, password


def collect_resources(session: Client, client_id: str, admin: Client) -> dict[str, str]:
    """Eén id per soort, opgehaald als de tenant zelf (dus legitiem)."""
    found: dict[str, str] = {}

    _, slots = admin.request(
        "GET", f"/api/admin/list-slots?client_id={client_id}", admin=True
    )
    for item in slots.get("slots") or []:
        slot = item.get("slot") or {}
        if slot.get("id"):
            found["slot"] = str(slot["id"])
            break
    for row in slots.get("themes") or []:
        if row.get("id"):
            found["theme"] = str(row["id"])
            break

    _, sources = session.request("GET", "/api/sources")
    rows = sources.get("sources") if isinstance(sources, dict) else None
    for row in rows or []:
        if row.get("id"):
            found["source"] = str(row["id"])
            break

    if "slot" in found:
        _, article = session.request("GET", f"/api/articles/{found['slot']}")
        article_id = str(article.get("id") or article.get("articleId") or "")
        if article_id:
            found["article"] = article_id

        _, research = session.request("GET", f"/api/research/{found['slot']}")
        items = research.get("researchItems") or research.get("items") or []
        for row in items:
            if isinstance(row, dict) and row.get("id"):
                found["research"] = str(row["id"])
                break

    return found


def expect_blocked(session: Client, method: str, path: str, body: dict | None, label: str) -> None:
    status, payload = session.request(method, path, body)
    if status in (401, 403, 404):
        ok(f"{label} {method} {path.split('?')[0]} -> {status}")
        return
    bad(f"{label} {method} {path} -> HTTP {status} (LEK) — {str(payload)[:140]}")


def expect_allowed(session: Client, method: str, path: str, body: dict | None, label: str) -> None:
    status, payload = session.request(method, path, body)
    if status == 200:
        ok(f"{label} {method} {path.split('?')[0]} -> 200 (eigen data werkt)")
        return
    bad(f"{label} {method} {path} -> HTTP {status} (regressie) — {str(payload)[:140]}")


def run_idor(attacker: Client, attacker_client_id: str, victim: dict[str, str], victim_client_id: str, label: str) -> None:
    theme = victim.get("theme")
    slot = victim.get("slot")
    article = victim.get("article")
    research = victim.get("research")
    source = victim.get("source")

    # Kritiek: sessie omzetten via broninspiratie.
    status, payload = attacker.request(
        "POST", "/api/sources/inspiration", {"clientId": victim_client_id}
    )
    if status in (401, 403, 404):
        ok(f"{label} POST /api/sources/inspiration met vreemde clientId -> {status}")
    else:
        bad(f"{label} POST /api/sources/inspiration -> HTTP {status} (LEK) — {str(payload)[:140]}")

    # Ook als het verzoek faalt: de sessie mag niet omgezet zijn.
    status, account = attacker.request("GET", "/api/account")
    session_client = str(account.get("clientId") or (account.get("account") or {}).get("clientId") or "")
    if session_client == attacker_client_id:
        ok(f"{label} sessie blijft eigen tenant na inspiration-poging")
    else:
        bad(f"{label} sessie is omgezet naar {session_client or '?'} (LEK)")

    if theme:
        expect_blocked(attacker, "PATCH", f"/api/themes/{theme}", {"title": "HACKED"}, label)
        expect_blocked(attacker, "POST", f"/api/themes/{theme}/submit", {}, label)
        expect_blocked(attacker, "POST", f"/api/themes/{theme}/research/trigger", {}, label)
        expect_blocked(attacker, "POST", "/api/admin/ce02-trigger", {"themeId": theme}, label)
    else:
        skipped(f"{label} geen theme van slachtoffer — theme-tests overgeslagen")

    if research:
        expect_blocked(attacker, "PATCH", f"/api/research-items/{research}", {"feedback": "liked"}, label)
    else:
        skipped(f"{label} geen research-item van slachtoffer")

    if slot:
        expect_blocked(attacker, "POST", f"/api/research/{slot}/submit", {}, label)
        expect_blocked(attacker, "POST", f"/api/research/{slot}/trigger", {}, label)
        expect_blocked(attacker, "PATCH", f"/api/agenda/{slot}/schedule", {"publishDate": "2026-10-01", "publishTime": "09:00"}, label)
        expect_blocked(attacker, "POST", f"/api/agenda/{slot}/archive", {}, label)
        expect_blocked(attacker, "POST", f"/api/articles/{slot}/claim-evidence", {"claim": "test"}, label)
    else:
        skipped(f"{label} geen slot van slachtoffer")

    if article:
        expect_blocked(attacker, "GET", f"/api/articles/{article}/publications", None, label)
        expect_blocked(attacker, "POST", f"/api/articles/{article}/publications", {"platform": "manual", "action": "geplaatst"}, label)
    else:
        skipped(f"{label} geen artikel van slachtoffer")

    if source:
        expect_blocked(attacker, "POST", f"/api/sources/{source}/check", {}, label)
        expect_blocked(attacker, "GET", f"/api/sources/calendar/list?source_id={source}", None, label)
        expect_blocked(attacker, "GET", f"/api/sources/drive/oauth/start?source_id={source}", None, label)
    else:
        skipped(f"{label} geen bron van slachtoffer")


def run_ssrf(session: Client, label: str) -> None:
    for url in ["http://169.254.169.254/latest/meta-data/", "http://127.0.0.1/"]:
        status, payload = session.request("POST", "/api/archive/check-url", {"url": url})
        state = str(payload.get("status") or "")
        if status in (400, 403, 404) or state in ("invalid", "red"):
            ok(f"{label} POST /api/archive/check-url {url} -> {state or status}")
        else:
            bad(f"{label} check-url {url} -> {status} / {state} (LEK)")


def main() -> int:
    env_name = (sys.argv[1] if len(sys.argv) > 1 else "staging").strip().lower()
    os.environ["CE_N8N_TARGET"] = env_name
    load_env(env_name, override=True)
    base = os.environ.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = os.environ.get("CE_RESET_TOKEN", "")
    if not base or not token:
        raise SystemExit(f"CE_PUBLIC_BASE_URL en CE_RESET_TOKEN vereist voor {env_name}")

    domain = "staging.invalid" if "staging" in base else "idor.test"
    print(f"==> Tenant-IDOR {env_name} — {base}")

    admin = Client(base, token)
    # Standaard dezelfde wegwerp-tenants als test-tenant-isolation.py, die daar al
    # data krijgen; zo hoeft deze test geen nieuwe AI-runs te starten.
    email_a = os.environ.get("CE_IDOR_EMAIL_A", f"isolation-a@{domain}")
    email_b = os.environ.get("CE_IDOR_EMAIL_B", f"isolation-b@{domain}")

    print("==> Tenants voorbereiden")
    client_a, password_a = ensure_tenant(admin, email_a, "A")
    client_b, password_b = ensure_tenant(admin, email_b, "B")

    session_a, session_b = Client(base), Client(base)
    session_a.login(email_a, password_a)
    session_b.login(email_b, password_b)

    print("==> Resources inventariseren")
    res_a = collect_resources(session_a, client_a, admin)
    res_b = collect_resources(session_b, client_b, admin)
    print(f"  A: {', '.join(sorted(res_a)) or 'geen'}")
    print(f"  B: {', '.join(sorted(res_b)) or 'geen'}")

    print("==> IDOR A → B")
    run_idor(session_a, client_a, res_b, client_b, "A→B")

    print("==> IDOR B → A")
    run_idor(session_b, client_b, res_a, client_a, "B→A")

    print("==> Eigen data moet blijven werken")
    if res_a.get("theme"):
        expect_allowed(session_a, "PATCH", f"/api/themes/{res_a['theme']}", {"title": "IDOR eigen theme"}, "A")
    if res_a.get("article"):
        expect_allowed(session_a, "GET", f"/api/articles/{res_a['article']}/publications", None, "A")
    if res_a.get("slot"):
        expect_allowed(session_a, "PATCH", f"/api/agenda/{res_a['slot']}/schedule", {"publishDate": "2026-10-02", "publishTime": "10:00"}, "A")
    if res_a.get("source"):
        expect_allowed(session_a, "POST", f"/api/sources/{res_a['source']}/check", {}, "A")

    print("==> SSRF via archive check-url")
    run_ssrf(session_a, "A")

    print(f"\n==> {PASS} pass, {FAIL} fail, {SKIP} skip")
    return 1 if FAIL else 0


if __name__ == "__main__":
    raise SystemExit(main())
