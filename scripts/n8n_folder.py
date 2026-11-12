"""n8n workflow folders (BrandFlocks vs Content Engine) via public API."""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.request
from typing import Any

from n8n_env import target_env


def api_request(method: str, url: str, api_key: str, data: dict | None = None) -> Any:
    body = None if data is None else json.dumps(data).encode()
    req = urllib.request.Request(
        url,
        data=body,
        method=method,
        headers={
            "X-N8N-API-KEY": api_key,
            "Content-Type": "application/json",
            "Accept": "application/json",
        },
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        raw = resp.read().decode()
        return json.loads(raw) if raw else {}


def workflow_folder_id() -> str:
    """Map-id voor workflow push: Bf gebruikt N8N_BF_FOLDER_ID, CE gebruikt N8N_CE_FOLDER_ID."""
    env = target_env()
    if env in ("brandflocks", "brandflocks-target"):
        return os.environ.get("N8N_BF_FOLDER_ID", "").strip()
    return os.environ.get("N8N_CE_FOLDER_ID", "").strip()


def list_projects(api_url: str, api_key: str) -> list[dict]:
    data = api_request("GET", f"{api_url}/api/v1/projects", api_key)
    return list(data.get("data") or [])


def list_folders(api_url: str, api_key: str, project_id: str) -> list[dict]:
    data = api_request("GET", f"{api_url}/api/v1/projects/{project_id}/folders", api_key)
    return list(data.get("data") or [])


def find_project_for_folder(api_url: str, api_key: str, folder_id: str) -> str | None:
    for project in list_projects(api_url, api_key):
        pid = project.get("id") or ""
        if not pid:
            continue
        for folder in list_folders(api_url, api_key, pid):
            if folder.get("id") == folder_id:
                return pid
    return None


def resolve_project_id(api_url: str, api_key: str) -> str:
    explicit = os.environ.get("N8N_PROJECT_ID", "").strip()
    if explicit:
        return explicit
    ce_folder = os.environ.get("N8N_CE_FOLDER_ID", "").strip()
    if ce_folder:
        pid = find_project_for_folder(api_url, api_key, ce_folder)
        if pid:
            return pid
    bf_folder = os.environ.get("N8N_BF_FOLDER_ID", "").strip()
    if bf_folder:
        pid = find_project_for_folder(api_url, api_key, bf_folder)
        if pid:
            return pid
    raise SystemExit(
        "Zet N8N_PROJECT_ID in .env.brandflocks, of N8N_CE_FOLDER_ID (ContentEngine-map) ter referentie."
    )


def ensure_folder(
    api_url: str,
    api_key: str,
    *,
    project_id: str,
    name: str = "BrandFlocks",
) -> str:
    """Return folder id; create *name* in project when missing."""
    for folder in list_folders(api_url, api_key, project_id):
        if (folder.get("name") or "").strip().lower() == name.strip().lower():
            return str(folder["id"])
    created = api_request(
        "POST",
        f"{api_url}/api/v1/projects/{project_id}/folders",
        api_key,
        {"name": name},
    )
    return str(created["id"])
