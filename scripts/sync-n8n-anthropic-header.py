#!/usr/bin/env python3
"""Sync n8n httpHeaderAuth (x-api-key) from ANTHROPIC_API_KEY in .env.brandflocks."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request

from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
if str(ROOT / "scripts") not in sys.path:
    sys.path.insert(0, str(ROOT / "scripts"))

from n8n_env import load_env, target_env  # noqa: E402

DEFAULT_CREDENTIAL_ID = "GhgOhw7oQpIMZDDo"
DEFAULT_CREDENTIAL_NAME = "Header Auth account"


def main() -> int:
    env_name = (os.environ.get("CE_N8N_TARGET") or os.environ.get("CE_ENV") or "brandflocks").strip()
    load_env(env_name, override=False)

    api = os.environ.get("N8N_API_URL", "").rstrip("/")
    n8n_key = os.environ.get("N8N_API_KEY", "").strip()
    anthropic = os.environ.get("ANTHROPIC_API_KEY", "").strip()
    cred_id = os.environ.get("N8N_ANTHROPIC_HEADER_CREDENTIAL_ID", DEFAULT_CREDENTIAL_ID).strip()
    cred_name = os.environ.get("N8N_ANTHROPIC_HEADER_CREDENTIAL_NAME", DEFAULT_CREDENTIAL_NAME).strip()

    if not api or not n8n_key:
        print("N8N_API_URL / N8N_API_KEY ontbreekt in env.", file=sys.stderr)
        return 1
    if not anthropic.startswith("sk-ant-"):
        print("ANTHROPIC_API_KEY ontbreekt of is ongeldig in env.", file=sys.stderr)
        return 1

    payload = {
        "name": cred_name,
        "type": "httpHeaderAuth",
        "data": {"name": "x-api-key", "value": anthropic},
    }
    url = f"{api}/api/v1/credentials/{cred_id}"
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode(),
        method="PATCH",
        headers={"X-N8N-API-KEY": n8n_key, "Content-Type": "application/json"},
    )
    try:
        with urllib.request.urlopen(req, timeout=30) as resp:
            if resp.status != 200:
                print(f"Onverwachte status {resp.status}", file=sys.stderr)
                return 1
    except urllib.error.HTTPError as e:
        print(e.read().decode()[:300], file=sys.stderr)
        return 1

    print(f"OK: credential {cred_id} ({cred_name}) bijgewerkt voor {target_env()}.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
