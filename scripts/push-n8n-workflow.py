#!/usr/bin/env python3
"""Create or update an n8n workflow from JSON, activate it, place in CE folder."""
from __future__ import annotations

import json
import os
import sys
import urllib.error
import urllib.request
from pathlib import Path

from n8n_env import load_env, patch_workflow_for_env, target_env, webhook_path, webhook_url


def request(method: str, url: str, api_key: str, data: dict | None = None) -> dict:
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
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode()
            return json.loads(raw) if raw else {}
    except urllib.error.HTTPError as err:
        detail = err.read().decode()
        raise RuntimeError(f"{err.code}|{detail}") from err


def find_workflow_id(api_url: str, api_key: str, name: str) -> str | None:
    cursor: str | None = None
    while True:
        url = f"{api_url}/api/v1/workflows?limit=100"
        if cursor:
            url += f"&cursor={cursor}"
        data = request("GET", url, api_key)
        for workflow in data.get("data", []):
            if workflow.get("name") == name:
                return workflow["id"]
        cursor = data.get("nextCursor")
        if not cursor:
            return None


def sanitize_settings(settings: dict | None) -> dict:
    raw = settings or {}
    return {"executionOrder": raw.get("executionOrder") or "v1"}


def build_payload(workflow: dict) -> dict:
    payload = {
        "name": workflow["name"],
        "nodes": workflow["nodes"],
        "connections": workflow["connections"],
        "settings": sanitize_settings(workflow.get("settings")),
    }
    if workflow.get("pinData"):
        payload["pinData"] = workflow["pinData"]
    return payload


def save_workflow(
    api_url: str,
    api_key: str,
    payload: dict,
    folder_id: str,
    existing_id: str | None,
) -> str:
    """Save workflow; use parentFolderId when the n8n instance supports it."""
    attempts: list[tuple[str, dict]] = []
    if folder_id:
        attempts.append(("with folder", {**payload, "parentFolderId": folder_id}))
    attempts.append(("default", payload))

    last_error: RuntimeError | None = None
    for label, body in attempts:
        try:
            if existing_id:
                request("PUT", f"{api_url}/api/v1/workflows/{existing_id}", api_key, body)
                if label == "with folder":
                    print(f"Placed in folder {folder_id}")
                return existing_id
            created = request("POST", f"{api_url}/api/v1/workflows", api_key, body)
            if label == "with folder":
                print(f"Placed in folder {folder_id}")
            return created["id"]
        except RuntimeError as err:
            last_error = err
            code_str, _, detail = str(err).partition("|")
            if label == "with folder" and code_str == "400" and "additional properties" in detail:
                print(
                    "Note: n8n API does not support parentFolderId yet — "
                    "workflow keeps its current folder (move once in n8n UI if needed)."
                )
                continue
            raise SystemExit(f"n8n API failed ({code_str}): {detail}") from err

    if last_error:
        raise SystemExit(f"n8n API failed: {last_error}")
    raise SystemExit("n8n API failed")


def main() -> None:
    env_target = target_env()
    load_env(env_target if env_target != "production" else None, override=True)

    if len(sys.argv) < 2:
        print(
            "Usage: push-n8n-workflow.py <workflow.json> [webhook-base-path]",
            file=sys.stderr,
        )
        print("  CE_N8N_TARGET=staging|production (default: production)", file=sys.stderr)
        raise SystemExit(1)

    path = sys.argv[1]
    webhook_base = sys.argv[2] if len(sys.argv) > 2 else None

    api_url = os.environ.get("N8N_API_URL", "").rstrip("/")
    api_key = os.environ.get("N8N_API_KEY", "")
    folder_id = os.environ.get("N8N_CE_FOLDER_ID", "").strip()

    if not api_url or not api_key:
        env_file = ".env" if env_target == "production" else f".env.{env_target}"
        raise SystemExit(f"N8N_API_URL and N8N_API_KEY must be set in {env_file}")

    with open(path, encoding="utf-8") as handle:
        workflow = json.load(handle)

    if env_target != "production":
        patch_workflow_for_env(workflow)

    payload = build_payload(workflow)

    print(f"Target: {env_target}")
    existing_id = find_workflow_id(api_url, api_key, workflow["name"])
    if existing_id:
        print(f"Updating workflow {existing_id}")
    else:
        print(f"Creating workflow {workflow['name']}")

    workflow_id = save_workflow(api_url, api_key, payload, folder_id, existing_id)

    request("POST", f"{api_url}/api/v1/workflows/{workflow_id}/activate", api_key)
    print(f"Activated: {workflow_id}")
    if webhook_base:
        print(f"Webhook: {webhook_url(webhook_base)}")


if __name__ == "__main__":
    main()
