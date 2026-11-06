#!/usr/bin/env python3
"""Generate CE-LLM-Visibility n8n workflow JSON (briefing/cursor-overview-report.md, DEEL 2b).

Maandelijkse (of handmatige) GEO-check: verschijnt het merk in antwoorden van
ChatGPT, Perplexity, Claude en Gemini op de GEO-vragen uit Search?

Credentials in n8n:
  - OpenAI account 3      (openAiApi)
  - Perplexity account    (perplexityApi)
  - Anthropic account     (anthropicApi)
  - GOOGLE_GEMINI_API_KEY (n8n environment variable — Google AI Studio API key)

Handmatig: POST webhook ce-llm-visibility met optioneel {"clientId":"…"}.
"""
import sys
from pathlib import Path

MYSQL_CRED = {"mySql": {"id": "taJrWdU0RBkbtU0R", "name": "Content Engine"}}
OPENAI_CRED = {"openAiApi": {"id": "KgpHNfuLruamtmGY", "name": "OpenAI account 3"}}
PERPLEXITY_CRED = {"perplexityApi": {"id": "SmcFj7hyJPXlHCfV", "name": "Perplexity account"}}
ANTHROPIC_CRED = {"anthropicApi": {"id": "ryLnEjvd7YzjmGx4", "name": "Anthropic account"}}

PREPARE_SCOPE_JS = r"""
const input = $input.first()?.json || {};
let clientId = '';
const body = input.body && typeof input.body === 'object' ? input.body : input;
if (body.clientId) clientId = String(body.clientId).trim();
else if (body.client_id) clientId = String(body.client_id).trim();
return [{ json: { clientId } }];
"""

BUILD_RESULTS_JS = r"""
const questions = $('Get LLM Questions').all().map((i) => i.json);
const gptAll = $('Ask ChatGPT').all().map((i) => i.json);
const pplxAll = $('Ask Perplexity').all().map((i) => i.json);
const claudeAll = $('Ask Claude').all().map((i) => i.json);
const geminiAll = $('Ask Gemini').all().map((i) => i.json);

function makeUuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function mentionsClient(text, clientName) {
  const name = String(clientName || '').trim().toLowerCase();
  if (!name) return false;
  return String(text || '').toLowerCase().includes(name);
}

const results = [];
for (let idx = 0; idx < questions.length; idx++) {
  const q = questions[idx] || {};
  const gptText = gptAll[idx]?.choices?.[0]?.message?.content
    || gptAll[idx]?.message?.content
    || gptAll[idx]?.text
    || '';
  const pplxText = pplxAll[idx]?.choices?.[0]?.message?.content
    || pplxAll[idx]?.message?.content
    || pplxAll[idx]?.text
    || '';
  const claudeText = claudeAll[idx]?.content?.[0]?.text
    || claudeAll[idx]?.text
    || '';
  const geminiText = geminiAll[idx]?.candidates?.[0]?.content?.parts?.[0]?.text
    || geminiAll[idx]?.text
    || '';

  const perModel = [
    ['chatgpt', gptText],
    ['perplexity', pplxText],
    ['claude', claudeText],
    ['gemini', geminiText],
  ];

  for (const [model, text] of perModel) {
    results.push({
      id: makeUuid(),
      client_id: q.client_id,
      question_id: q.question_id,
      model,
      mentioned: mentionsClient(text, q.client_name) ? 1 : 0,
      raw_response: text,
    });
  }
}

return results.map((r) => ({ json: r }));
"""

nodes = [
    {
        "parameters": {
            "rule": {
                "interval": [
                    {"field": "cronExpression", "expression": "0 6 1 * *"},
                ]
            }
        },
        "id": "llmvis-schedule",
        "name": "Monthly Trigger",
        "type": "n8n-nodes-base.scheduleTrigger",
        "typeVersion": 1.2,
        "position": [240, 320],
    },
    {
        "parameters": {
            "httpMethod": "POST",
            "path": "ce-llm-visibility",
            "responseMode": "onReceived",
            "options": {},
        },
        "id": "llmvis-webhook",
        "name": "Webhook Trigger",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [240, 520],
        "webhookId": "ce-llm-visibility",
    },
    {
        "parameters": {"jsCode": PREPARE_SCOPE_JS},
        "id": "llmvis-prepare-scope",
        "name": "Prepare Scope",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [460, 420],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT q.id AS question_id, q.client_id, q.question, c.name AS client_name\nFROM llm_probleemvragen q\nJOIN clients c ON c.id = q.client_id\nWHERE q.active = 1 AND q.question_kind = 'geo'\n  AND ($1 = '' OR q.client_id = $1)\nORDER BY q.client_id, q.created_at",
            "options": {
                "queryReplacement": "={{ [ $('Prepare Scope').first().json.clientId || '' ] }}"
            },
        },
        "id": "llmvis-get-questions",
        "name": "Get LLM Questions",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [680, 420],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {
            "method": "POST",
            "url": "https://api.openai.com/v1/chat/completions",
            "authentication": "predefinedCredentialType",
            "nodeCredentialType": "openAiApi",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "content-type", "value": "application/json"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={{ JSON.stringify({ model: 'gpt-4o', messages: [{ role: 'user', content: $('Get LLM Questions').item.json.question }] }) }}",
            "options": {},
        },
        "id": "llmvis-ask-chatgpt",
        "name": "Ask ChatGPT",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [900, 420],
        "credentials": OPENAI_CRED,
    },
    {
        "parameters": {
            "method": "POST",
            "url": "https://api.perplexity.ai/chat/completions",
            "authentication": "predefinedCredentialType",
            "nodeCredentialType": "perplexityApi",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "content-type", "value": "application/json"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={{ JSON.stringify({ model: 'sonar', messages: [{ role: 'user', content: $('Get LLM Questions').item.json.question }] }) }}",
            "options": {},
        },
        "id": "llmvis-ask-perplexity",
        "name": "Ask Perplexity",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [1120, 420],
        "credentials": PERPLEXITY_CRED,
    },
    {
        "parameters": {
            "method": "POST",
            "url": "https://api.anthropic.com/v1/messages",
            "authentication": "predefinedCredentialType",
            "nodeCredentialType": "anthropicApi",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "anthropic-version", "value": "2023-06-01"},
                    {"name": "content-type", "value": "application/json"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={{ JSON.stringify({ model: 'claude-sonnet-4-6', max_tokens: 1024, messages: [{ role: 'user', content: $('Get LLM Questions').item.json.question }] }) }}",
            "options": {},
        },
        "id": "llmvis-ask-claude",
        "name": "Ask Claude",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [1340, 420],
        "credentials": ANTHROPIC_CRED,
    },
    {
        "parameters": {
            "method": "POST",
            "url": "=https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key={{ $env.GOOGLE_GEMINI_API_KEY }}",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "content-type", "value": "application/json"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={{ JSON.stringify({ contents: [{ parts: [{ text: $('Get LLM Questions').item.json.question }] }] }) }}",
            "options": {},
        },
        "id": "llmvis-ask-gemini",
        "name": "Ask Gemini",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.2,
        "position": [1560, 420],
    },
    {
        "parameters": {"jsCode": BUILD_RESULTS_JS},
        "id": "llmvis-build-results",
        "name": "Build Results",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1780, 420],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "INSERT INTO llm_visibility_checks (id, client_id, question_id, model, mentioned, raw_response)\nVALUES ($1, $2, $3, $4, $5, $6)",
            "options": {
                "queryReplacement": "={{ [ $json.id, $json.client_id, $json.question_id, $json.model, $json.mentioned, $json.raw_response ] }}"
            },
        },
        "id": "llmvis-insert",
        "name": "Insert Visibility Check",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [2000, 420],
        "credentials": MYSQL_CRED,
    },
]

connections = {
    "Monthly Trigger": {"main": [[{"node": "Prepare Scope", "type": "main", "index": 0}]]},
    "Webhook Trigger": {"main": [[{"node": "Prepare Scope", "type": "main", "index": 0}]]},
    "Prepare Scope": {"main": [[{"node": "Get LLM Questions", "type": "main", "index": 0}]]},
    "Get LLM Questions": {"main": [[{"node": "Ask ChatGPT", "type": "main", "index": 0}]]},
    "Ask ChatGPT": {"main": [[{"node": "Ask Perplexity", "type": "main", "index": 0}]]},
    "Ask Perplexity": {"main": [[{"node": "Ask Claude", "type": "main", "index": 0}]]},
    "Ask Claude": {"main": [[{"node": "Ask Gemini", "type": "main", "index": 0}]]},
    "Ask Gemini": {"main": [[{"node": "Build Results", "type": "main", "index": 0}]]},
    "Build Results": {"main": [[{"node": "Insert Visibility Check", "type": "main", "index": 0}]]},
}

workflow = {
    "name": "CE-LLM-Visibility",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-LLM-Visibility.json"
sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_workflow_utils import write_workflow

write_workflow(workflow, out)
print("Wrote", out)
