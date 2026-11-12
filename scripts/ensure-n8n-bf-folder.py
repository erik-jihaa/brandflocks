#!/usr/bin/env python3
"""Zorg dat map 'BrandFlocks' bestaat in n8n; schrijf N8N_BF_FOLDER_ID naar .env.*."""
from __future__ import annotations

import os
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(Path(__file__).resolve().parent))

from n8n_env import load_env  # noqa: E402
from n8n_folder import ensure_folder, resolve_project_id  # noqa: E402


def upsert_env_key(path: Path, key: str, value: str) -> None:
    text = path.read_text(encoding="utf-8") if path.is_file() else ""
    line = f"{key}={value}"
    pattern = re.compile(rf"^{re.escape(key)}=.*$", re.MULTILINE)
    if pattern.search(text):
        text = pattern.sub(line, text)
    else:
        if text and not text.endswith("\n"):
            text += "\n"
        text += f"\n# n8n — eigen map los van Content Engine\n{line}\n"
    text = re.sub(r"^N8N_CE_FOLDER_ID=.*\n?", "", text, flags=re.MULTILINE)
    path.write_text(text, encoding="utf-8")


def main() -> None:
    profiles = sys.argv[1:] if len(sys.argv) > 1 else ["brandflocks", "brandflocks-target"]
    load_env("brandflocks", override=True)

    api_url = os.environ.get("N8N_API_URL", "").rstrip("/")
    api_key = os.environ.get("N8N_API_KEY", "")
    if not api_url or not api_key:
        raise SystemExit("N8N_API_URL en N8N_API_KEY vereist in .env.brandflocks")

    project_id = resolve_project_id(api_url, api_key)
    folder_id = ensure_folder(api_url, api_key, project_id=project_id, name="BrandFlocks")

    for profile in profiles:
        env_path = ROOT / f".env.{profile}"
        if not env_path.is_file():
            print(f"Skip (ontbreekt): {env_path.name}")
            continue
        upsert_env_key(env_path, "N8N_BF_FOLDER_ID", folder_id)
        print(f"OK {env_path.name} → N8N_BF_FOLDER_ID={folder_id}")

    print(f"Project: {project_id}")
    print("Volgende stap: ./scripts/setup-brandflocks-n8n.sh")


if __name__ == "__main__":
    main()
