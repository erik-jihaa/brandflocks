#!/usr/bin/env python3
"""HBA stap 1: slot-research via service-token hoort bij het slot (geen andere tenant).

Usage:
  ./scripts/test-hba-slot-research-isolation.py staging

Vereist CE_PUBLIC_BASE_URL + CE_RESET_TOKEN in .env.staging (of CE_ENV_FILE).
"""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from n8n_env import load_env  # noqa: E402

# HBA testmerken op staging (zelfde IDs als run-hba-v0-staging.py)
STELWERK_CLIENT = "27b4136b-d251-fc60-e8ff-45222cb8a407"
DUINROOS_CLIENT = "a4b504fe-1b0c-b758-fcfd-575398b51eff"
BRANDPUNT_CLIENT = "54aeb554-562d-0f0d-9c48-381681a2eb84"

# URL's die alleen in het oude (foute) Stelwerk-fixture zaten — mogen niet op Stelwerk-slots terugkomen.
FORBIDDEN_ON_STELWERK_URL_FRAGMENTS = (
    "krijg-ik-te-maken-met-wachttijden",
    "black-friday-cyber-monday",
    "online-winkelen-bescherm-jezelf-tegen-oplichting",
    "verpleging-en-verzorging-thuis",
    "wet-langdurige-zorg",
)


def req_get(base: str, token: str, path: str) -> dict:
    url = f"{base.rstrip('/')}{path}"
    r = urllib.request.Request(
        url,
        headers={"X-CE-Reset-Token": token, "Accept": "application/json"},
        method="GET",
    )
    with urllib.request.urlopen(r, timeout=90) as resp:
        return json.load(resp)


def latest_slot_for_client(base: str, token: str, client_id: str) -> str:
    data = req_get(base, token, f"/api/admin/list-slots?client_id={client_id}")
    slots = data.get("slots") or []
    if not slots:
        raise RuntimeError(f"Geen slots voor client {client_id}")
    row = slots[0]
    slot = row.get("slot") if isinstance(row.get("slot"), dict) else row
    slot_id = str(slot.get("id") or "")
    if not slot_id:
        raise RuntimeError(f"Geen slot-id in list-slots voor client {client_id}")
    return slot_id


def research_debug(base: str, token: str, slot_id: str) -> dict:
    return req_get(base, token, f"/api/admin/debug-research?slot_id={slot_id}")


def item_urls(payload: dict) -> set[str]:
    urls: set[str] = set()
    for row in payload.get("items") or []:
        u = (row.get("url") or "").strip()
        if u:
            urls.add(u.lower())
    fetch = payload.get("fetchSlotResearch") or {}
    if fetch.get("ok") is True:
        for group in fetch.get("themeGroups") or []:
            for item in group.get("items") or []:
                u = (item.get("url") or "").strip()
                if u:
                    urls.add(u.lower())
        for item in fetch.get("items") or []:
            u = (item.get("url") or "").strip()
            if u:
                urls.add(u.lower())
    return urls


def main() -> int:
    os.environ.setdefault("CE_N8N_TARGET", "staging")
    load_env("staging")
    base = (os.environ.get("CE_PUBLIC_BASE_URL") or "").strip()
    token = (os.environ.get("CE_RESET_TOKEN") or "").strip()
    if not base or not token:
        print("CE_PUBLIC_BASE_URL / CE_RESET_TOKEN ontbreken", file=sys.stderr)
        return 2

    stelwerk_slot = latest_slot_for_client(base, token, STELWERK_CLIENT)
    duinroos_slot = latest_slot_for_client(base, token, DUINROOS_CLIENT)
    brandpunt_slot = latest_slot_for_client(base, token, BRANDPUNT_CLIENT)

    st = research_debug(base, token, stelwerk_slot)
    fetch = st.get("fetchSlotResearch") or {}
    if fetch.get("ok") is not True:
        print(f"FAIL fetchSlotResearch Stelwerk: {fetch}")
        return 1
    print(f"OK fetchSlotResearch Stelwerk slot {stelwerk_slot}")

    st_urls = item_urls(st)
    duin_urls = item_urls(research_debug(base, token, duinroos_slot))
    bp_urls = item_urls(research_debug(base, token, brandpunt_slot))

    overlap_duin = st_urls & duin_urls
    overlap_bp = st_urls & bp_urls
    if overlap_duin:
        print(f"FAIL Stelwerk research overlapt Duinroos URLs: {overlap_duin}")
        return 1
    if overlap_bp:
        print(f"FAIL Stelwerk research overlapt Brandpunt URLs: {overlap_bp}")
        return 1

    bad_fragments = [
        frag
        for frag in FORBIDDEN_ON_STELWERK_URL_FRAGMENTS
        if any(frag in u for u in st_urls)
    ]
    if bad_fragments:
        print(f"FAIL Stelwerk bevat oude fixture-URL's ({bad_fragments}): {st_urls}")
        return 1

    print("OK geen research-URL overlap Stelwerk ↔ Duinroos/Brandpunt")
    print("OK geen oude cross-fixture URL's op Stelwerk")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8", errors="replace")
        print(f"HTTP {e.code}: {body}", file=sys.stderr)
        raise SystemExit(1)
