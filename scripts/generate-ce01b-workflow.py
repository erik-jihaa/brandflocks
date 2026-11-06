#!/usr/bin/env python3
"""Generate CE-01b Theme naar Agenda-slot n8n workflow JSON."""
import json
import sys
from pathlib import Path

MYSQL_CRED = {"mySql": {"id": "taJrWdU0RBkbtU0R", "name": "Content Engine"}}
CLAUDE_CRED = {"httpHeaderAuth": {"id": "GhgOhw7oQpIMZDDo", "name": "Header Auth account"}}

PARSE_JS = r"""
const raw = $input.first()?.json ?? {};
const body = raw.body ?? raw;
const themeId = String(body.theme_id ?? body.themeId ?? '').trim();
if (!themeId) throw new Error('Missing theme_id');
return { json: { theme_id: themeId } };
"""

BUILD_CONTEXT_JS = r"""
const theme = $('Get Theme').item.json;
const researchRows = $('Get Research').all().map((i) => i.json).filter((r) => r.id);
const liked = researchRows.filter((r) => r.feedback === 'liked');
const usedFallback = liked.length === 0 && researchRows.length > 0;
const selected = liked.length > 0 ? liked : researchRows;

const research_summary = selected.length
  ? selected.map((r) => `[${r.id}] ${r.title}\nSnippet: ${r.snippet || ''}\nRationale: ${r.item_rationale || ''}\nURL: ${r.url || ''}`).join('\n\n')
  : 'Geen research-items beschikbaar.';

return {
  json: {
    ...theme,
    research_items: selected,
    research_summary,
    used_fallback: usedFallback,
    research_count: selected.length,
  },
};
"""

PREPARE_SLOTS_JS = r"""
const ctx = $('Build Context').item.json;
const claude = $('Claude Plan Slots').item.json;
const input = claude?.content?.[0]?.input ?? {};
const rhythmRow = $('Get Content Rhythm').first()?.json ?? {};
const weekCount = Number($('Count Week Slots').first()?.json?.slot_count ?? 0);
let rhythmRoles = [];
try {
  const parsed = typeof rhythmRow.roles === 'string' ? JSON.parse(rhythmRow.roles) : rhythmRow.roles;
  if (Array.isArray(parsed)) rhythmRoles = parsed;
} catch {
  rhythmRoles = [];
}
const rhythmRoleRaw = rhythmRoles.length
  ? rhythmRoles[weekCount % rhythmRoles.length]
  : null;
const rhythm_role = rhythmRoleRaw
  ? String(rhythmRoleRaw.role_name || rhythmRoleRaw.roleName || '').trim()
  : null;

const planned = Array.isArray(input.slots) && input.slots.length ? input.slots : [{
  theme: ctx.title,
  goal: ctx.rationale || ctx.motivation || '',
  search_terms: ctx.title,
  phase_key: ctx.phase_key || '',
  publish_date: ctx.cycle_week || null,
}];

function makeUuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

function isoWeekFromDate(iso) {
  if (!iso) return '';
  const d = new Date(String(iso).slice(0, 10) + 'T12:00:00Z');
  const day = d.getUTCDay() || 7;
  d.setUTCDate(d.getUTCDate() + 4 - day);
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  const week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
  return String(week);
}

const out = [];
for (const slot of planned) {
  const phaseKey = String(slot.phase_key || ctx.phase_key || isoWeekFromDate(ctx.cycle_week) || '1').trim();
  out.push({
    json: {
      slot_id: makeUuid(),
      client_id: ctx.client_id,
      theme_id: ctx.id,
      theme: String(slot.theme || ctx.title || 'Theme').trim(),
      goal: String(slot.goal || ctx.rationale || ctx.motivation || '').trim(),
      search_terms: String(slot.search_terms || slot.theme || ctx.title || '').trim(),
      phase_key: phaseKey,
      publish_date: slot.publish_date ? String(slot.publish_date).slice(0, 10) : (ctx.cycle_week ? String(ctx.cycle_week).slice(0, 10) : null),
      status: 'themed',
      rhythm_role,
      used_fallback: ctx.used_fallback,
    },
  });
}

if (out.length === 0) {
  throw new Error('No content slots planned');
}

return out.slice(0, 1);
"""

RESPOND_JS = r"""
const inserted = ($('Prepare Slot Rows').all?.() ?? []).map((i) => i.json).filter((r) => r.slot_id);
const existing = $('Check Existing Slots').all().map((i) => i.json).filter((r) => r.id);
const ctx = $('Build Context').item?.json ?? $('Validate Theme').item.json;
const slotIds = inserted.length ? inserted.map((r) => r.slot_id) : existing.map((r) => r.id);
return {
  json: {
    ok: true,
    theme_id: ctx.id,
    slots_created: inserted.length,
    slot_ids: slotIds,
    used_research_fallback: ctx.used_fallback === true,
    research_items_used: ctx.research_count || 0,
  },
};
"""

nodes = [
    {
        "parameters": {
            "httpMethod": "POST",
            "path": "ce01b-theme-to-slot",
            "responseMode": "responseNode",
            "options": {},
        },
        "id": "ce01b-webhook",
        "name": "Webhook Trigger",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [400, 400],
        "webhookId": "ce01b-theme-to-slot-webhook",
    },
    {
        "parameters": {"jsCode": PARSE_JS},
        "id": "ce01b-parse",
        "name": "Parse Input",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [620, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "CREATE TABLE IF NOT EXISTS content_slots (\n  id CHAR(36) NOT NULL PRIMARY KEY,\n  client_id CHAR(36) NOT NULL,\n  theme_id CHAR(36) NULL,\n  status VARCHAR(50) DEFAULT 'themed',\n  phase_key VARCHAR(50) NULL,\n  theme VARCHAR(255) NULL,\n  goal TEXT NULL,\n  search_terms VARCHAR(500) NULL,\n  publish_date DATE NULL,\n  input_human TEXT NULL,\n  origin VARCHAR(30) DEFAULT 'theme',\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  KEY idx_theme (theme_id),\n  KEY idx_client (client_id)\n)",
            "options": {},
        },
        "id": "ce01b-ensure-slots",
        "name": "Ensure Slots Table",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [840, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT t.*, c.name AS client_name, c.archetype, c.adversary, c.brand_goal,\n  COALESCE(c.posts_per_week, 1) AS posts_per_week\nFROM themes t\nJOIN clients c ON c.id = t.client_id\nWHERE t.id = $1\nLIMIT 1",
            "options": {"queryReplacement": "={{ [ $('Parse Input').item.json.theme_id ] }}"},
        },
        "id": "ce01b-get-theme",
        "name": "Get Theme",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1060, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "jsCode": "const row = $input.first()?.json ?? {};\nif (!row.id) throw new Error('Theme not found');\nreturn { json: row };"
        },
        "id": "ce01b-validate-theme",
        "name": "Validate Theme",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1280, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT id, status FROM content_slots\nWHERE theme_id = $1\n  AND COALESCE(status, '') NOT IN ('archived', 'archive', 'deleted')\nORDER BY created_at ASC",
            "options": {"queryReplacement": "={{ [ $('Validate Theme').item.json.id ] }}"},
        },
        "id": "ce01b-check-existing",
        "name": "Check Existing Slots",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1500, 400],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {
            "conditions": {
                "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict"},
                "conditions": [
                    {
                        "id": "has-existing",
                        "leftValue": "={{ $('Check Existing Slots').all().filter(i => i.json.id).length }}",
                        "rightValue": 0,
                        "operator": {"type": "number", "operation": "gt"},
                    }
                ],
                "combinator": "and",
            },
            "options": {},
        },
        "id": "ce01b-if-existing",
        "name": "If Slots Exist",
        "type": "n8n-nodes-base.if",
        "typeVersion": 2,
        "position": [1720, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT posts_per_week, roles FROM content_rhythms\nWHERE client_id = $1 AND active = 1\nORDER BY created_at DESC\nLIMIT 1",
            "options": {"queryReplacement": "={{ [ $('Validate Theme').item.json.client_id ] }}"},
        },
        "id": "ce01b-get-rhythm",
        "name": "Get Content Rhythm",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1940, 640],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT COUNT(DISTINCT s.id) AS slot_count\nFROM content_slots s\nINNER JOIN themes t ON t.id = s.theme_id\nWHERE s.client_id = $1\n  AND COALESCE(s.status, '') NOT IN ('archived', 'archive', 'deleted')\n  AND DATE(t.cycle_week) = DATE($2)",
            "options": {
                "queryReplacement": "={{ [ $('Validate Theme').item.json.client_id, $('Validate Theme').item.json.cycle_week ] }}"
            },
        },
        "id": "ce01b-count-week-slots",
        "name": "Count Week Slots",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [2160, 640],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT * FROM research_items\nWHERE theme_id = $1\n  AND COALESCE(feedback, 'none') != 'forgotten'\n  AND (\n    feedback = 'liked'\n    OR NOT EXISTS (\n      SELECT 1 FROM research_items r2\n      WHERE r2.theme_id = $1 AND r2.feedback = 'liked'\n    )\n  )\nORDER BY created_at ASC\nLIMIT 20",
            "options": {"queryReplacement": "={{ [ $('Validate Theme').item.json.id ] }}"},
        },
        "id": "ce01b-get-research",
        "name": "Get Research",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1940, 520],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": BUILD_CONTEXT_JS},
        "id": "ce01b-build-context",
        "name": "Build Context",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2160, 520],
    },
    {
        "parameters": {
            "method": "POST",
            "url": "https://api.anthropic.com/v1/messages",
            "authentication": "genericCredentialType",
            "genericAuthType": "httpHeaderAuth",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "anthropic-version", "value": "2023-06-01"},
                    {"name": "content-type", "value": "application/json"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": '={{ JSON.stringify({\n  model: "claude-sonnet-4-6",\n  max_tokens: 2048,\n  system: "Je vertaalt een goedgekeurd content-thema naar concrete agenda-slots voor " + $json.client_name + ". Archetype: " + ($json.archetype || "") + ". Adversary: " + ($json.adversary || "") + ".",\n  messages: [{\n    role: "user",\n    content: "THEMA: " + $json.title + "\\nMotivation: " + ($json.motivation || "") + "\\nRationale: " + ($json.rationale || "") + "\\nCycle week: " + ($json.cycle_week || "onbekend") + "\\n\\nGESELECTEERDE RESEARCH (" + ($json.used_fallback ? "fallback: geen likes, alle items" : "geliked") + "):\\n" + $json.research_summary + "\\n\\nPlan exact 1 agenda-slot. Vul goal, search_terms, phase_key (ISO-weeknummer als string), publish_date (YYYY-MM-DD binnen cycle_week)."\n  }],\n  tools: [{\n    name: "plan_content_slots",\n    description: "Concrete content_slots voor dit thema",\n    input_schema: {\n      type: "object",\n      properties: {\n        slots: {\n          type: "array",\n          items: {\n            type: "object",\n            properties: {\n              theme: { type: "string" },\n              goal: { type: "string" },\n              search_terms: { type: "string" },\n              phase_key: { type: "string" },\n              publish_date: { type: "string" }\n            },\n            required: ["goal", "search_terms", "phase_key"]\n          }\n        }\n      },\n      required: ["slots"]\n    }\n  }],\n  tool_choice: { type: "tool", name: "plan_content_slots" }\n}) }}',
            "options": {},
        },
        "id": "ce01b-claude",
        "name": "Claude Plan Slots",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [2380, 520],
        "credentials": CLAUDE_CRED,
    },
    {
        "parameters": {"jsCode": PREPARE_SLOTS_JS},
        "id": "ce01b-prepare-slots",
        "name": "Prepare Slot Rows",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2600, 520],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "INSERT INTO content_slots (id, client_id, theme_id, status, phase_key, theme, goal, rhythm_role)\nVALUES ($1, $2, $3, $4, $5, $6, $7, $8)",
            "options": {
                "queryReplacement": "={{ [ $json.slot_id, $json.client_id, $json.theme_id, $json.status, $json.phase_key, $json.theme, $json.goal, $json.rhythm_role ] }}"
            },
        },
        "id": "ce01b-insert-slot",
        "name": "Insert Content Slot",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [2820, 520],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "jsCode": "return $('Check Existing Slots').all().map((i) => i.json).filter((r) => r.id).map((r) => ({ json: { slot_id: r.id } }));"
        },
        "id": "ce01b-use-existing",
        "name": "Use Existing Slots",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1940, 280],
    },
    {
        "parameters": {
            "jsCode": "const fromNew = ($('Prepare Slot Rows').all?.() ?? []).map((i) => i.json.slot_id).filter(Boolean);\nif (fromNew.length) return fromNew.map((id) => ({ json: { slot_id: id } }));\nreturn $input.all().map((i) => i.json).filter((r) => r.slot_id).map((r) => ({ json: { slot_id: r.slot_id } }));"
        },
        "id": "ce01b-collect-slots",
        "name": "Collect Slot IDs",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [3040, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "UPDATE themes SET status = 'converted_to_slot' WHERE id = $1",
            "options": {"queryReplacement": "={{ [ $('Validate Theme').item.json.id ] }}"},
        },
        "id": "ce01b-update-theme",
        "name": "Update Theme Status",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [3260, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {"jsCode": RESPOND_JS},
        "id": "ce01b-respond-build",
        "name": "Build Response",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [3480, 400],
    },
    {
        "parameters": {"respondWith": "json", "responseBody": "={{ JSON.stringify($json) }}", "options": {}},
        "id": "ce01b-respond",
        "name": "Respond OK",
        "type": "n8n-nodes-base.respondToWebhook",
        "typeVersion": 1.1,
        "position": [3920, 400],
    },
]

connections = {
    "Webhook Trigger": {"main": [[{"node": "Parse Input", "type": "main", "index": 0}]]},
    "Parse Input": {"main": [[{"node": "Ensure Slots Table", "type": "main", "index": 0}]]},
    "Ensure Slots Table": {"main": [[{"node": "Get Theme", "type": "main", "index": 0}]]},
    "Get Theme": {"main": [[{"node": "Validate Theme", "type": "main", "index": 0}]]},
    "Validate Theme": {"main": [[{"node": "Check Existing Slots", "type": "main", "index": 0}]]},
    "Check Existing Slots": {"main": [[{"node": "If Slots Exist", "type": "main", "index": 0}]]},
    "If Slots Exist": {
        "main": [
            [{"node": "Use Existing Slots", "type": "main", "index": 0}],
            [{"node": "Get Content Rhythm", "type": "main", "index": 0}],
        ]
    },
    "Get Content Rhythm": {"main": [[{"node": "Count Week Slots", "type": "main", "index": 0}]]},
    "Count Week Slots": {"main": [[{"node": "Get Research", "type": "main", "index": 0}]]},
    "Get Research": {"main": [[{"node": "Build Context", "type": "main", "index": 0}]]},
    "Build Context": {"main": [[{"node": "Claude Plan Slots", "type": "main", "index": 0}]]},
    "Claude Plan Slots": {"main": [[{"node": "Prepare Slot Rows", "type": "main", "index": 0}]]},
    "Prepare Slot Rows": {"main": [[{"node": "Insert Content Slot", "type": "main", "index": 0}]]},
    "Insert Content Slot": {"main": [[{"node": "Collect Slot IDs", "type": "main", "index": 0}]]},
    "Use Existing Slots": {"main": [[{"node": "Collect Slot IDs", "type": "main", "index": 0}]]},
    "Collect Slot IDs": {"main": [[{"node": "Update Theme Status", "type": "main", "index": 0}]]},
    "Update Theme Status": {"main": [[{"node": "Build Response", "type": "main", "index": 0}]]},
    "Build Response": {"main": [[{"node": "Respond OK", "type": "main", "index": 0}]]},
}

workflow = {
    "name": "CE-01b (Theme naar Slot)",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-01b - Theme naar Slot.json"
sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_workflow_utils import write_workflow

write_workflow(workflow, out)
print("Wrote", out)
