#!/usr/bin/env python3
"""n8n workflow: fetch open Trello cards for a board (CE-02 helper)."""
import json
from pathlib import Path

TRELLO_CRED = {"trelloApi": {"id": "GIhZmS4y4uS16HQB", "name": "Trello account"}}

VALIDATE_JS = r"""
const query = $input.first()?.json?.query || {};
const boardId = String(query.board_id || query.boardId || '').trim();
if (!boardId) {
  throw new Error('Missing board_id query parameter');
}
return { json: { board_id: boardId } };
"""

FORMAT_JS = r"""
const cards = $input.all().map((i) => i.json).filter((c) => c && c.id && c.name);
return {
  json: {
    ok: true,
    board_id: $('Validate Board').item.json.board_id,
    cards: cards.map((c) => ({
      id: String(c.id),
      name: String(c.name),
      desc: String(c.desc || ''),
      url: String(c.url || ''),
      shortUrl: String(c.shortUrl || c.url || ''),
    })),
  },
};
"""

nodes = [
    {
        "parameters": {
            "httpMethod": "GET",
            "path": "ce-trello-cards",
            "responseMode": "responseNode",
            "options": {},
        },
        "id": "trello-cards-webhook",
        "name": "Webhook",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [400, 300],
        "webhookId": "ce-trello-cards-webhook",
    },
    {
        "parameters": {"jsCode": VALIDATE_JS},
        "id": "trello-cards-validate",
        "name": "Validate Board",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [620, 300],
    },
    {
        "parameters": {
            "method": "GET",
            "url": "=https://api.trello.com/1/boards/{{ $json.board_id }}/cards",
            "authentication": "predefinedCredentialType",
            "nodeCredentialType": "trelloApi",
            "sendQuery": True,
            "queryParameters": {
                "parameters": [
                    {"name": "fields", "value": "name,desc,url,shortUrl"},
                    {"name": "filter", "value": "open"},
                ]
            },
            "options": {},
        },
        "id": "trello-cards-fetch",
        "name": "Fetch Cards",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [840, 300],
        "credentials": TRELLO_CRED,
    },
    {
        "parameters": {"jsCode": FORMAT_JS, "mode": "runOnceForAllItems"},
        "id": "trello-cards-format",
        "name": "Format Response",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1060, 300],
    },
    {
        "parameters": {
            "respondWith": "json",
            "responseBody": "={{ JSON.stringify($json) }}",
            "options": {},
        },
        "id": "trello-cards-respond",
        "name": "Respond",
        "type": "n8n-nodes-base.respondToWebhook",
        "typeVersion": 1.1,
        "position": [1280, 300],
    },
]

connections = {
    "Webhook": {"main": [[{"node": "Validate Board", "type": "main", "index": 0}]]},
    "Validate Board": {"main": [[{"node": "Fetch Cards", "type": "main", "index": 0}]]},
    "Fetch Cards": {"main": [[{"node": "Format Response", "type": "main", "index": 0}]]},
    "Format Response": {"main": [[{"node": "Respond", "type": "main", "index": 0}]]},
}

workflow = {
    "name": "CE-Trello Cards",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-Trello Cards.json"
out.write_text(json.dumps(workflow, indent=2) + "\n", encoding="utf-8")
print("Wrote", out)
