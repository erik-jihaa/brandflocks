#!/usr/bin/env python3
"""Cross-tenant isolation tests — Platform fase 3 #14.

Uses two tenant sessions and verifies tenant A cannot read or mutate tenant B data
(and vice versa) via the tenant API.

Usage:
  CE_N8N_TARGET=staging python3 scripts/test-tenant-isolation.py staging
  ./scripts/test-tenant-isolation.sh staging
"""
from __future__ import annotations

import json
import os
import secrets
import sys
import urllib.error
import urllib.request
from dataclasses import dataclass, field
from http.cookiejar import CookieJar
from pathlib import Path
from typing import Any
from urllib.parse import urljoin

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(Path(__file__).resolve().parent))

from n8n_env import load_env, parse_env_value  # noqa: E402

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


@dataclass
class TenantFixture:
    client_id: str
    email: str
    password: str
    slot_ids: list[str] = field(default_factory=list)
    theme_ids: list[str] = field(default_factory=list)
    source_ids: list[str] = field(default_factory=list)
    report_ids: list[str] = field(default_factory=list)
    image_ids: list[str] = field(default_factory=list)
    brand_marker: str = ""


class HttpClient:
    def __init__(self, base: str, token: str = "") -> None:
        self.base = base.rstrip("/")
        self.token = token
        self.jar = CookieJar()
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))

    def request(
        self,
        method: str,
        path: str,
        body: dict | None = None,
        *,
        admin: bool = False,
        expect_status: int | None = None,
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
            with self.opener.open(req, timeout=60) as resp:
                status = resp.getcode()
                raw = resp.read().decode("utf-8")
        except urllib.error.HTTPError as exc:
            status = exc.code
            raw = exc.read().decode("utf-8", errors="replace")
        payload: dict[str, Any] = {}
        if raw:
            try:
                payload = json.loads(raw)
            except json.JSONDecodeError:
                payload = {"error": raw}
        if expect_status is not None and status != expect_status:
            raise RuntimeError(f"{method} {path} -> HTTP {status}, expected {expect_status}: {payload}")
        return status, payload

    def login(self, email: str, password: str) -> None:
        status, payload = self.request("POST", "/api/auth/login", {"email": email, "password": password})
        if status != 200 or not payload.get("ok"):
            raise RuntimeError(f"Login failed for {email}: {payload}")


def load_config(env_name: str) -> dict[str, str]:
    os.environ["CE_N8N_TARGET"] = env_name
    load_env(env_name, override=True)
    base = os.environ.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = os.environ.get("CE_RESET_TOKEN", "")
    if not base or not token:
        raise SystemExit(f"CE_PUBLIC_BASE_URL and CE_RESET_TOKEN required in .env.{env_name if env_name != 'production' else ''}")
    domain = "staging.invalid" if "staging" in base else "isolation.test"
    return {
        "base": base,
        "token": token,
        "email_a": os.environ.get("CE_ISOLATION_EMAIL_A", f"isolation-a@{domain}"),
        "email_b": os.environ.get("CE_ISOLATION_EMAIL_B", f"isolation-b@{domain}"),
        "password_a": os.environ.get("CE_ISOLATION_PASSWORD_A", ""),
        "password_b": os.environ.get("CE_ISOLATION_PASSWORD_B", ""),
    }


def ensure_tenant(admin: HttpClient, email: str, password: str, label: str) -> tuple[str, str]:
    _, clients_payload = admin.request("GET", "/api/admin/list-slots?list_clients=1", admin=True)
    for row in clients_payload.get("clients") or []:
        if str(row.get("contact_email") or "").lower() == email.lower():
            client_id = str(row["id"])
            if not password:
                password = f"Iso-{secrets.token_urlsafe(12)}!"
            admin.request(
                "POST",
                "/api/admin/set-password",
                {"clientId": client_id, "password": password},
                admin=True,
            )
            return client_id, password

    if not password:
        password = f"Iso-{secrets.token_urlsafe(12)}!"
    body = {
        "company": f"Isolation {label}",
        "contactEmail": email,
        "loginEmail": email,
        "password": password,
        "postsPerWeek": 1,
        "publishMode": "manual_with_review",
        "brandGoal": f"ISOLATION_MARKER_{label}_{secrets.token_hex(4)}",
        "writingGuide": "Isolation test tenant — geen klantdata.",
    }
    _, created = admin.request("POST", "/api/admin/create-client", body, admin=True)
    client = created.get("client") or {}
    client_id = str(created.get("clientId") or client.get("clientId") or client.get("id") or "")
    if not client_id:
        raise RuntimeError(f"create-client missing id for {email}: {created}")
    print(f"  Created tenant {label}: {email}")
    return client_id, password


def admin_snapshot(admin: HttpClient, client_id: str) -> dict[str, list[str]]:
    _, payload = admin.request("GET", f"/api/admin/list-slots?client_id={client_id}", admin=True)
    slot_ids = [str(item["slot"]["id"]) for item in payload.get("slots") or [] if item.get("slot", {}).get("id")]
    theme_ids = [str(row["id"]) for row in payload.get("themes") or [] if row.get("id")]
    image_ids = [str(row["id"]) for row in payload.get("clientImages") or [] if row.get("id")]
    return {"slot_ids": slot_ids, "theme_ids": theme_ids, "image_ids": image_ids}


def seed_tenant(session: HttpClient, fixture: TenantFixture) -> None:
    if not fixture.slot_ids:
        status, payload = session.request(
            "POST",
            "/api/fastlane",
            {
                "title": f"Isolation slot {fixture.brand_marker}",
                "platform": "Linkedin",
                "angle": "archetype",
                "origin": "fastlane",
            },
        )
        if status == 200 and payload.get("slotId"):
            fixture.slot_ids.append(str(payload["slotId"]))

    if not fixture.source_ids:
        status, payload = session.request(
            "POST",
            "/api/sources",
            {
                "category": "URL/RSS",
                "title": f"Isolation RSS {fixture.brand_marker}",
                "url": f"https://example.com/isolation-{secrets.token_hex(4)}.rss",
            },
        )
        if status in (200, 201):
            source = payload.get("source") if isinstance(payload.get("source"), dict) else payload
            source_id = str(source.get("id") or "")
            if source_id:
                fixture.source_ids.append(source_id)

    _, strategy = session.request("GET", "/api/strategy")
    marker = str(
        strategy.get("brandGoal")
        or strategy.get("brand_goal")
        or strategy.get("brandstory")
        or strategy.get("brandStory")
        or ""
    )
    if marker:
        fixture.brand_marker = marker

    _, sources_list = session.request("GET", "/api/sources?category=URL/RSS")
    rows = sources_list.get("sources") if isinstance(sources_list, dict) else sources_list
    for row in rows or []:
        sid = str(row.get("id") or "")
        if sid and sid not in fixture.source_ids:
            fixture.source_ids.append(sid)

    _, reports = session.request("GET", "/api/reports")
    for row in reports.get("reports") or []:
        rid = str(row.get("id") or row.get("reportId") or "")
        if rid:
            fixture.report_ids.append(rid)

    _, images = session.request("GET", "/api/images")
    for row in images.get("images") or []:
        iid = str(row.get("id") or "")
        if iid and iid not in fixture.image_ids:
            fixture.image_ids.append(iid)


def collect_agenda_slot_ids(session: HttpClient) -> set[str]:
    _, payload = session.request("GET", "/api/agenda")
    ids: set[str] = set()
    for group in payload.get("groups") or []:
        for row in group.get("rows") or []:
            slot_id = str(row.get("slotId") or row.get("slot_id") or "")
            if slot_id:
                ids.add(slot_id)
    return ids


def expect_blocked(session: HttpClient, method: str, path: str, body: dict | None = None, label: str = "") -> None:
    status, payload = session.request(method, path, body)
    if status in (401, 403, 404):
        ok(f"{label} {method} {path} -> {status}")
        return
    if status == 200 and not payload.get("ok", True) and payload.get("error"):
        ok(f"{label} {method} {path} -> blocked ({payload.get('error')})")
        return
    bad(f"{label} {method} {path} -> HTTP {status} (leak?) — {str(payload)[:120]}")


def expect_list_excludes(session: HttpClient, path: str, foreign_ids: set[str], extractors: list, label: str) -> None:
    status, payload = session.request("GET", path)
    if status != 200:
        bad(f"{label} GET {path} -> HTTP {status}")
        return
    found: set[str] = set()
    for extractor in extractors:
        found.update(extractor(payload))
    leaked = foreign_ids & found
    if leaked:
        bad(f"{label} GET {path} — foreign ids visible: {', '.join(sorted(leaked))}")
    else:
        ok(f"{label} GET {path} — geen foreign ids")


def run_cross_tests(attacker: HttpClient, victim: TenantFixture, label: str) -> None:
    foreign_slots = set(victim.slot_ids)
    foreign_sources = set(victim.source_ids)
    foreign_reports = set(victim.report_ids)
    foreign_images = set(victim.image_ids)

    agenda_ids = collect_agenda_slot_ids(attacker)
    leaked_slots = foreign_slots & agenda_ids
    if leaked_slots:
        bad(f"{label} GET /api/agenda — foreign slot ids: {', '.join(sorted(leaked_slots))}")
    else:
        ok(f"{label} GET /api/agenda — geen foreign slots")

    for slot_id in foreign_slots:
        expect_blocked(attacker, "GET", f"/api/articles/{slot_id}", label=label)

    for source_id in foreign_sources:
        expect_blocked(attacker, "GET", f"/api/sources/{source_id}", label=label)
        expect_blocked(attacker, "PATCH", f"/api/sources/{source_id}", {"title": "HACKED"}, label=label)

    for report_id in foreign_reports:
        expect_blocked(attacker, "GET", f"/api/reports/{report_id}", label=label)

    for image_id in foreign_images:
        expect_blocked(attacker, "PATCH", f"/api/images/{image_id}", {"altText": "HACKED"}, label=label)
        expect_blocked(attacker, "DELETE", f"/api/images/{image_id}", label=label)

    if victim.brand_marker:
        _, strategy = attacker.request("GET", "/api/strategy")
        attacker_brand = str(
            strategy.get("brandGoal")
            or strategy.get("brand_goal")
            or strategy.get("brandstory")
            or strategy.get("brandStory")
            or ""
        )
        if victim.brand_marker in attacker_brand and attacker_brand == victim.brand_marker:
            bad(f"{label} GET /api/strategy — toont victim brandGoal")
        else:
            ok(f"{label} GET /api/strategy — eigen brandGoal")

    expect_list_excludes(
        attacker,
        "/api/reports",
        foreign_reports,
        [lambda p: {str(r.get("id") or r.get("reportId") or "") for r in (p.get("reports") or [])}],
        label,
    )


def main() -> int:
    env_name = (sys.argv[1] if len(sys.argv) > 1 else os.environ.get("CE_ISOLATION_ENV", "staging")).strip().lower()
    cfg = load_config(env_name)
    base = cfg["base"]
    token = cfg["token"]

    print(f"==> Tenant-isolatie {env_name} — {base}")

    admin = HttpClient(base, token)
    _, session_info = admin.request("GET", "/api/auth/session")
    if not session_info.get("authRequired"):
        skipped("auth uitgeschakeld — isolatietest niet zinvol op deze omgeving")
        print(f"\n==> {PASS} pass, {FAIL} fail, {SKIP} skip")
        return 0

    print("==> Tenants voorbereiden")
    client_a, password_a = ensure_tenant(admin, cfg["email_a"], cfg["password_a"], "A")
    client_b, password_b = ensure_tenant(admin, cfg["email_b"], cfg["password_b"], "B")

    fixture_a = TenantFixture(client_a, cfg["email_a"], password_a)
    fixture_b = TenantFixture(client_b, cfg["email_b"], password_b)

    snap_a = admin_snapshot(admin, client_a)
    snap_b = admin_snapshot(admin, client_b)
    fixture_a.slot_ids.extend(snap_a["slot_ids"])
    fixture_a.theme_ids.extend(snap_a["theme_ids"])
    fixture_a.image_ids.extend(snap_a["image_ids"])
    fixture_b.slot_ids.extend(snap_b["slot_ids"])
    fixture_b.theme_ids.extend(snap_b["theme_ids"])
    fixture_b.image_ids.extend(snap_b["image_ids"])

    session_a = HttpClient(base)
    session_b = HttpClient(base)
    session_a.login(cfg["email_a"], password_a)
    session_b.login(cfg["email_b"], password_b)

    print("==> Testdata aanvullen (fastlane / source)")
    seed_tenant(session_a, fixture_a)
    seed_tenant(session_b, fixture_b)

    if not fixture_a.slot_ids and not fixture_b.slot_ids:
        skipped("geen slot ids — kan cross-slot tests niet draaien")

    print("==> Cross-tenant checks A → B")
    run_cross_tests(session_a, fixture_b, "A→B")

    print("==> Cross-tenant checks B → A")
    run_cross_tests(session_b, fixture_a, "B→A")

    print(f"\n==> {PASS} pass, {FAIL} fail, {SKIP} skip")
    return 1 if FAIL else 0


if __name__ == "__main__":
    raise SystemExit(main())
