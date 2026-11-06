#!/usr/bin/env python3
"""V0 hints opnieuw forceren op de drie stap-2 slots (staging)."""
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


def req(method: str, url: str, token: str, payload: dict | None = None, timeout: float = 180) -> dict:
    headers = {"X-CE-Reset-Token": token}
    data = None
    if payload is not None:
        headers["Content-Type"] = "application/json"
        data = json.dumps(payload).encode("utf-8")
    r = urllib.request.Request(url, data=data, headers=headers, method=method)
    with urllib.request.urlopen(r, timeout=timeout) as resp:
        return json.load(resp)


def main() -> int:
    env = load_env(Path(os.environ.get("CE_ENV_FILE", str(ROOT / ".env.staging"))))
    base = env.get("CE_PUBLIC_BASE_URL", "").rstrip("/")
    token = env.get("CE_RESET_TOKEN", "")
    if not base or not token:
        print("CE_PUBLIC_BASE_URL / CE_RESET_TOKEN ontbreken", file=sys.stderr)
        return 2

    v0_path = ROOT / "docs" / "hba-v1.10-stap2-v0-verleiding.json"
    pack = json.loads(v0_path.read_text(encoding="utf-8"))
    out = {
        "generatedAt": time.strftime("%Y-%m-%d %H:%M:%S UTC", time.gmtime()),
        "hints": [],
    }
    for row in pack.get("verleiding") or []:
        merk = row.get("merk")
        slot_id = row.get("slotId")
        print(f"==> hint {merk} slot {slot_id}", flush=True)
        try:
            resp = req(
                "POST",
                f"{base}/api/admin/hba-article-hint-slot",
                token,
                {"slot_id": slot_id, "force": True},
                timeout=180,
            )
        except urllib.error.HTTPError as err:
            body = err.read().decode("utf-8", errors="replace")
            out["hints"].append({"merk": merk, "slotId": slot_id, "ok": False, "error": body})
            continue
        hint = resp.get("hint") or {}
        out["hints"].append(
            {
                "merk": merk,
                "slotId": slot_id,
                "articleId": resp.get("articleId"),
                "ok": resp.get("ok"),
                "aanwijzing": hint.get("aanwijzing"),
                "passageNa": hint.get("passageNa"),
                "ctaNa": hint.get("ctaNa"),
                "invulvelden": hint.get("invulvelden"),
                "bewijsveldBron": hint.get("bewijsveldBron"),
                "error": resp.get("error"),
            }
        )
    dest = ROOT / "docs" / "hba-v1.10-stap3-v0-hints.json"
    dest.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"Wrote {dest}", flush=True)
    json.dump(out, sys.stdout, indent=2, ensure_ascii=False)
    print()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
