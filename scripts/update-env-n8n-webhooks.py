#!/usr/bin/env python3
"""Update N8N_*_WEBHOOK_URL lines in an env file from CE_N8N_TARGET."""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(Path(__file__).resolve().parent))

from n8n_env import WEBHOOK_ENV_KEYS, load_env, webhook_url, target_env  # noqa: E402


def main() -> None:
    env_name = (sys.argv[1] if len(sys.argv) > 1 else target_env()).strip().lower()
    env_path = ROOT / (".env" if env_name in ("", "production") else f".env.{env_name}")
    if not env_path.is_file():
        raise SystemExit(f"Missing {env_path}")

    import os

    os.environ["CE_N8N_TARGET"] = env_name
    load_env(env_name, override=True)

    lines = env_path.read_text(encoding="utf-8").splitlines()
    updates = {key: webhook_url(base) for base, key in WEBHOOK_ENV_KEYS}

    out: list[str] = []
    seen: set[str] = set()
    key_re = re.compile(r"^([A-Z0-9_]+)=")

    for line in lines:
        match = key_re.match(line.strip())
        if match and match.group(1) in updates:
            key = match.group(1)
            out.append(f"{key}={updates[key]}")
            seen.add(key)
        else:
            out.append(line)

    for base, key in WEBHOOK_ENV_KEYS:
        if key not in seen:
            out.append(f"{key}={updates[key]}")

    env_path.write_text("\n".join(out).rstrip() + "\n", encoding="utf-8")
    print(f"Updated {len(updates)} webhook URL(s) in {env_path.name}")


if __name__ == "__main__":
    main()
