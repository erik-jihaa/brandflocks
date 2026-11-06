#!/usr/bin/env python3
"""Generate CE-LinkedIn Sync n8n workflow — weekly Apify engagement refresh."""
import json
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_env import load_env, target_env
from n8n_workflow_utils import write_workflow

_env = target_env()
load_env(_env if _env != "production" else None)
CE_PUBLIC_BASE_URL = (os.environ.get("CE_PUBLIC_BASE_URL") or "https://ce.igisnext.com").rstrip("/")
CE_RESET_TOKEN = os.environ.get("CE_RESET_TOKEN", "")

nodes = [
    {
        "parameters": {
            "rule": {"interval": [{"field": "weeks", "triggerAtDay": [1], "triggerAtHour": 7}]},
        },
        "id": "linkedin-schedule",
        "name": "Weekly Schedule",
        "type": "n8n-nodes-base.scheduleTrigger",
        "typeVersion": 1.2,
        "position": [400, 300],
    },
    {
        "parameters": {
            "method": "POST",
            "url": f"{CE_PUBLIC_BASE_URL}/api/linkedin/sync",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "Content-Type", "value": "application/json"},
                    {"name": "User-Agent", "value": "ContentEngine/1.0"},
                    {"name": "X-CE-Reset-Token", "value": CE_RESET_TOKEN},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={}",
            "options": {"timeout": 300000},
        },
        "id": "linkedin-sync",
        "name": "Sync LinkedIn Engagement",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [640, 300],
    },
]

connections = {
    "Weekly Schedule": {
        "main": [[{"node": "Sync LinkedIn Engagement", "type": "main", "index": 0}]]
    },
}

workflow = {
    "name": "CE-LinkedIn Sync",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-LinkedIn Sync.json"
write_workflow(workflow, out)
print("Wrote", out)
