#!/usr/bin/env python3
"""Minimal n8n workflow: list Trello boards via stored credential."""
import json
from pathlib import Path

TRELLO_CRED = {"trelloApi": {"id": "GIhZmS4y4uS16HQB", "name": "Trello account"}}

FORMAT_JS = r"""
const boards = $input.all().map((i) => i.json).filter((b) => b && b.id && b.name);
return {
  json: {
    ok: true,
    boards: boards.map((b) => ({
      id: String(b.id),
      name: String(b.name),
      url: String(b.url || ''),
    })),
  },
};
"""

nodes = [
    {
        "parameters": {
            "httpMethod": "GET",
            "path": "ce-trello-boards",
            "responseMode": "responseNode",
            "options": {},
        },
        "id": "trello-webhook",
        "name": "Webhook",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [400, 300],
        "webhookId": "ce-trello-boards-webhook",
    },
    {
        "parameters": {
            "method": "GET",
            "url": "https://api.trello.com/1/members/me/boards",
            "authentication": "predefinedCredentialType",
            "nodeCredentialType": "trelloApi",
            "sendQuery": True,
            "queryParameters": {
                "parameters": [
                    {"name": "fields", "value": "name,url"},
                    {"name": "filter", "value": "open"},
                ]
            },
            "options": {},
        },
        "id": "trello-boards",
        "name": "Fetch Boards",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [620, 300],
        "credentials": TRELLO_CRED,
    },
    {
        "parameters": {"jsCode": FORMAT_JS, "mode": "runOnceForAllItems"},
        "id": "trello-format",
        "name": "Format Response",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [840, 300],
    },
    {
        "parameters": {
            "respondWith": "json",
            "responseBody": "={{ JSON.stringify($json) }}",
            "options": {},
        },
        "id": "trello-respond",
        "name": "Respond",
        "type": "n8n-nodes-base.respondToWebhook",
        "typeVersion": 1.1,
        "position": [1060, 300],
    },
]

connections = {
    "Webhook": {"main": [[{"node": "Fetch Boards", "type": "main", "index": 0}]]},
    "Fetch Boards": {"main": [[{"node": "Format Response", "type": "main", "index": 0}]]},
    "Format Response": {"main": [[{"node": "Respond", "type": "main", "index": 0}]]},
}

workflow = {
    "name": "CE-Trello Boards",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-Trello Boards.json"
out.write_text(json.dumps(workflow, indent=2), encoding="utf-8")
print("Wrote", out)
