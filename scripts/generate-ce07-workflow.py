#!/usr/bin/env python3
"""Generate CE-07 Performance Rapportage n8n workflow JSON."""
import json
import sys
from pathlib import Path

MYSQL_CRED = {"mySql": {"id": "taJrWdU0RBkbtU0R", "name": "Content Engine"}}
CLAUDE_CRED = {"httpHeaderAuth": {"id": "GhgOhw7oQpIMZDDo", "name": "Header Auth account"}}

PARSE_JS = r"""
const raw = $input.first()?.json ?? {};
const body = raw.body ?? raw;
const clientId = String(body.client_id ?? body.clientId ?? '').trim();
const periodStart = String(body.period_start ?? body.periodStart ?? '').slice(0, 10);
const periodEnd = String(body.period_end ?? body.periodEnd ?? '').slice(0, 10);
const scope = String(body.scope ?? 'general').trim() || 'general';
const scopeArticleId = String(body.scope_article_id ?? body.scopeArticleId ?? '').trim();
if (!periodStart || !periodEnd) throw new Error('period_start and period_end required');
return { json: { client_id: clientId, period_start: periodStart, period_end: periodEnd, scope, scope_article_id: scopeArticleId } };
"""

COMPUTE_JS = r"""
const parse = $('Parse Input').item.json;
const client = $('Get Client').item.json;
const rows = $('Get Articles').all().map((i) => i.json).filter((r) => r.id);

function engagement(r) {
  return Number(r.views || 0) + Number(r.likes || 0) + Number(r.comments || 0);
}
function parseFlags(raw) {
  if (!raw) return [];
  if (Array.isArray(raw)) return raw.map(String);
  try {
    const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
    return Array.isArray(parsed) ? parsed.map(String) : [];
  } catch {
    return [];
  }
}
function onStrategy(r) {
  const hoofd = String(r.hba_hoofdoordeel || '').trim();
  if (hoofd !== '') return hoofd === 'op_merk';
  const bc = Number(r.brand_contribution_score);
  return Number.isFinite(bc) && bc >= 6.0;
}
function eligibleBestPost(p) {
  const hoofd = String(p.hbaHoofdoordeel || '').trim();
  if (hoofd === 'regelbreuk' || hoofd === 'tegenkracht') return false;
  return true;
}
function bestPostRank(p) {
  const hoofd = String(p.hbaHoofdoordeel || '').trim();
  if (hoofd === 'op_merk') return 3;
  if (hoofd === 'neutraal') return 2;
  if (hoofd === 'categorietaal') return 0;
  return 1;
}

const posts = rows.map((r) => ({
  id: r.id,
  headline: r.headline || 'Untitled',
  angle: r.angle || 'general',
  tone: r.tone || 'b2b',
  contentrol: r.contentrol || null,
  brandContribution: r.brand_contribution_score != null ? Number(r.brand_contribution_score) : null,
  hbaHoofdoordeel: r.hba_hoofdoordeel || null,
  hbaFlags: parseFlags(r.hba_flags),
  engagement: engagement(r),
  views: Number(r.views || 0),
  likes: Number(r.likes || 0),
  comments: Number(r.comments || 0),
  publish_date: String(r.publish_date || '').slice(0, 10),
  on_strategy: onStrategy(r),
}));

const strategyPosts = posts.filter((p) => p.on_strategy);
const generalPosts = posts.filter((p) => !p.on_strategy);
const total = posts.length;

const pctOnStrategy = total ? Math.round((strategyPosts.length / total) * 1000) / 10 : 0;
const pctGeneral = total ? Math.round((generalPosts.length / total) * 1000) / 10 : 0;

const toneCounts = {};
for (const p of posts) {
  const t = String(p.tone || 'b2b').toLowerCase();
  toneCounts[t] = (toneCounts[t] || 0) + 1;
}

const contentrolCounts = {
  positionering: 0,
  bewijs: 0,
  educatie: 0,
  activatie: 0,
  community: 0,
  service: 0,
  employer_culture: 0,
  reactief: 0,
};
for (const p of posts) {
  const role = String(p.contentrol || '').toLowerCase();
  if (contentrolCounts[role] != null) contentrolCounts[role] += 1;
}

function avgEng(list) {
  if (!list.length) return 0;
  return Math.round((list.reduce((s, p) => s + p.engagement, 0) / list.length) * 10) / 10;
}
function medianEng(list) {
  if (!list.length) return 0;
  const sorted = list.map((p) => p.engagement).sort((a, b) => a - b);
  const mid = Math.floor(sorted.length / 2);
  return sorted.length % 2 ? sorted[mid] : Math.round(((sorted[mid - 1] + sorted[mid]) / 2) * 10) / 10;
}

const engStrategy = avgEng(strategyPosts);
const engGeneral = avgEng(generalPosts);
let diffPct = 0;
if (engGeneral > 0) {
  diffPct = Math.round(((engStrategy - engGeneral) / engGeneral) * 1000) / 10;
} else if (engStrategy > 0) {
  diffPct = 100;
}

let best = null;
for (const p of posts) {
  if (!eligibleBestPost(p)) continue;
  if (!best) {
    best = p;
    continue;
  }
  if (p.engagement > best.engagement) {
    best = p;
    continue;
  }
  if (p.engagement === best.engagement && bestPostRank(p) > bestPostRank(best)) {
    best = p;
  }
}

const mean = total ? posts.reduce((s, p) => s + p.engagement, 0) / total : 0;
const outliers = posts
  .filter((p) => total > 2 && p.engagement > mean * 1.5 && p.engagement > 0)
  .slice(0, 5)
  .map((p) => ({
    article_id: p.id,
    headline: p.headline,
    angle_final: p.angle,
    engagement: p.engagement,
    publish_date: p.publish_date,
  }));

const dayMap = new Map();
for (const p of posts) {
  const d = p.publish_date || parse.period_start;
  if (!dayMap.has(d)) {
    dayMap.set(d, {
      publish_date: d,
      strategy_views: 0,
      strategy_likes: 0,
      strategy_comments: 0,
      general_views: 0,
      general_likes: 0,
      general_comments: 0,
    });
  }
  const row = dayMap.get(d);
  const prefix = p.on_strategy ? 'strategy' : 'general';
  row[`${prefix}_views`] += p.views;
  row[`${prefix}_likes`] += p.likes;
  row[`${prefix}_comments`] += p.comments;
}
const dailyBreakdown = [...dayMap.values()].sort((a, b) => a.publish_date.localeCompare(b.publish_date));

function makeUuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

return {
  json: {
    report_id: makeUuid(),
    client_id: client.id,
    client_name: client.name,
    archetype: client.archetype || '',
    adversary: client.adversary || '',
    period_start: parse.period_start,
    period_end: parse.period_end,
    scope: parse.scope,
    scope_article_id: parse.scope_article_id || null,
    pct_on_strategy: pctOnStrategy,
    pct_general: pctGeneral,
    tone_distribution: toneCounts,
    angle_distribution: contentrolCounts,
    geloofwaardigheidstest: client.geloofwaardigheidstest || '',
    brand_mechanisme: client.mechanisme || '',
    brand_archetype_markt: client.branche_archetype || '',
    archetype_same_as_market: String(client.archetype || '').trim() !== ''
      && String(client.branche_archetype || '').trim() !== ''
      && String(client.archetype || '').trim().toLowerCase() === String(client.branche_archetype || '').trim().toLowerCase(),
    hba_posts: posts.map((p) => ({
      article_id: p.id,
      headline: p.headline,
      hoofdoordeel: p.hbaHoofdoordeel,
      flags: p.hbaFlags,
      brand_contribution: p.brandContribution,
      engagement: p.engagement,
      on_strategy: p.on_strategy,
    })),
    execution_rate: total ? Math.min(100, Math.round((posts.filter((p) => p.publish_date).length / total) * 1000) / 10) : 0,
    engagement_on_strategy: engStrategy,
    engagement_general: engGeneral,
    engagement_diff_pct: diffPct,
    median_on_strategy: medianEng(strategyPosts),
    median_general: medianEng(generalPosts),
    article_count_strategy: strategyPosts.length,
    article_count_general: generalPosts.length,
    article_count_total: total,
    outlier_posts: outliers,
    best_performing_post: best
      ? {
          article_id: best.id,
          headline: best.headline,
          angle_final: best.angle,
          engagement: best.engagement,
          publish_date: best.publish_date,
        }
      : null,
    daily_breakdown: dailyBreakdown,
  },
};
"""

PREPARE_SAVE_JS = r"""
const m = $('Compute Metrics').item.json;
const claude = $('Claude Insight').item.json;
const narrative = claude?.content?.[0]?.input?.narrative
  || claude?.content?.[0]?.text
  || 'Geen narrative gegenereerd.';
return {
  json: {
    ...m,
    narrative: String(narrative).trim(),
  },
};
"""

RESPOND_JS = r"""
const r = $('Prepare Save').item.json;
return {
  json: {
    ok: true,
    report_id: r.report_id,
    period_start: r.period_start,
    period_end: r.period_end,
    scope: r.scope,
    engagement_diff_pct: r.engagement_diff_pct,
    narrative: r.narrative,
  },
};
"""

nodes = [
    {
        "parameters": {
            "httpMethod": "POST",
            "path": "ce07-performance-report",
            "responseMode": "responseNode",
            "options": {},
        },
        "id": "ce07-webhook",
        "name": "Webhook Trigger",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [400, 400],
        "webhookId": "ce07-performance-report-webhook",
    },
    {
        "parameters": {"jsCode": PARSE_JS},
        "id": "ce07-parse",
        "name": "Parse Input",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [620, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "CREATE TABLE IF NOT EXISTS performance_reports (\n  id CHAR(36) NOT NULL PRIMARY KEY,\n  client_id CHAR(36) NOT NULL,\n  period_start DATE NOT NULL,\n  period_end DATE NOT NULL,\n  scope VARCHAR(20) DEFAULT 'general',\n  scope_article_id CHAR(36) NULL,\n  pct_on_strategy DECIMAL(6,2) NULL,\n  pct_general DECIMAL(6,2) NULL,\n  tone_distribution JSON NULL,\n  angle_distribution JSON NULL,\n  execution_rate DECIMAL(6,2) NULL,\n  engagement_on_strategy DECIMAL(12,2) NULL,\n  engagement_general DECIMAL(12,2) NULL,\n  engagement_diff_pct DECIMAL(8,2) NULL,\n  median_on_strategy DECIMAL(12,2) NULL,\n  median_general DECIMAL(12,2) NULL,\n  outlier_posts JSON NULL,\n  best_performing_post JSON NULL,\n  daily_breakdown JSON NULL,\n  narrative TEXT NULL,\n  generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  KEY idx_client (client_id),\n  KEY idx_period (period_start, period_end)\n)",
            "options": {},
        },
        "id": "ce07-ensure-table",
        "name": "Ensure Reports Table",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [840, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT c.id, c.name, c.archetype, c.adversary, c.brand_goal,\n  bn.geloofwaardigheidstest, bn.mechanisme, bn.foe AS narrative_foe,\n  bl.markt_archetype AS branche_archetype\nFROM clients c\nLEFT JOIN brand_narratives bn ON bn.client_id = c.id AND COALESCE(bn.is_current, 1) = 1\nLEFT JOIN branche_library bl ON bl.branche_code = bn.branche_code\nWHERE (NULLIF($1, '') IS NULL OR c.id = $1)\nORDER BY c.name ASC\nLIMIT 1",
            "options": {"queryReplacement": "={{ [ $('Parse Input').item.json.client_id ] }}"},
        },
        "id": "ce07-get-client",
        "name": "Get Client",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1060, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT a.id, COALESCE(a.headline_human, a.headline) AS headline,\n  COALESCE(a.angle_final, a.angle_suggested, 'general') AS angle,\n  COALESCE(a.tone_final, a.tone_suggested, 'b2b') AS tone,\n  COALESCE(a.interaction_views, 0) AS views,\n  COALESCE(a.interaction_likes, 0) AS likes,\n  COALESCE(a.interaction_comments, 0) AS comments,\n  DATE(a.published_at) AS publish_date,\n  hs.contentrol,\n  hs.brand_contribution_score,\n  hs.architecture_risk_flags AS hba_flags,\n  JSON_UNQUOTE(JSON_EXTRACT(hs.raw_llm_response, '$.hoofdoordeel')) AS hba_hoofdoordeel\nFROM articles a\nJOIN content_slots s ON s.id = a.slot_id\nLEFT JOIN hba_scores hs ON hs.id = (\n  SELECT hs2.id FROM hba_scores hs2\n  WHERE hs2.post_id = a.id\n  ORDER BY hs2.scored_at DESC\n  LIMIT 1\n)\nWHERE s.client_id = $1\n  AND (\n    a.published_at IS NULL\n    OR DATE(a.published_at) BETWEEN $2 AND $3\n  )\n  AND COALESCE(a.status, '') IN ('published', 'archived', 'approved', 'draft', 'written')\n  AND ($4 = 'general' OR NULLIF($5, '') IS NULL OR a.id = $5)",
            "options": {
                "queryReplacement": "={{ [ $('Get Client').item.json.id, $('Parse Input').item.json.period_start, $('Parse Input').item.json.period_end, $('Parse Input').item.json.scope, $('Parse Input').item.json.scope_article_id ] }}"
            },
        },
        "id": "ce07-get-articles",
        "name": "Get Articles",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1280, 400],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": COMPUTE_JS},
        "id": "ce07-compute",
        "name": "Compute Metrics",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1500, 400],
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
            "jsonBody": '={{ JSON.stringify({\n  model: "claude-sonnet-4-6",\n  max_tokens: 2048,\n  system: "Je schrijft performance-rapportages voor " + $json.client_name + ". Archetype: " + $json.archetype + ". Tegenkracht/adversary: " + $json.adversary + ". Regel van het merk (geloofwaardigheidstest): " + ($json.geloofwaardigheidstest || "niet ingevuld") + ". Mechanisme: " + ($json.brand_mechanisme || "niet ingevuld") + ".\\n\\nOp strategie = HBA-hoofdoordeel op_merk (niet engagement of kop).\\nEen post met hoofdoordeel regelbreuk wordt nooit aanbevolen om opnieuw te plaatsen, te promoten of als voorbeeld te gebruiken, ongeacht het bereik. Noem zo\\u2019n post als aandachtspunt: wat de post deed en welke grens van het merk hij raakte.\\nEen aanbeveling mag nooit de redenering van de tegenkracht van het merk volgen. Controleer elke aanbeveling tegen de tegenkracht voordat je hem opneemt." + ($json.archetype_same_as_market ? "\\n\\nMerk-archetype en markt-archetype vallen samen: content-adviezen moeten een concreet element van het mechanisme of de regel noemen; warm en menselijk zonder regel of mechanisme is geen merkadvies." : "") + "\\n\\nSchrijf in het Nederlands, concreet en actionable. Geen interne labels (hoofdoordeel, flags, archetype-namen als vakterm).",\n  messages: [{\n    role: "user",\n    content: "Periode: " + $json.period_start + " t/m " + $json.period_end + "\\nScope: " + $json.scope + "\\nPosts totaal: " + $json.article_count_total + " (op strategie/op_merk: " + $json.article_count_strategy + ", overig: " + $json.article_count_general + ")\\nPct op strategie (hoofdoordeel op_merk): " + $json.pct_on_strategy + "%\\nEngagement op-strategie: " + $json.engagement_on_strategy + " vs overig: " + $json.engagement_general + " (verschil " + $json.engagement_diff_pct + "%)\\nTone: " + JSON.stringify($json.tone_distribution) + "\\nHBA per post: " + JSON.stringify($json.hba_posts) + "\\nBeste post (engagement, geen regelbreuk/tegenkracht): " + JSON.stringify($json.best_performing_post) + "\\nOutliers: " + JSON.stringify($json.outlier_posts) + "\\n\\nBeantwoord: wat gebeurde er belangrijks? reageerde op_merk-content beter? welke posts raakten de regel? uitschieters? consistentie merk? concrete aanbevelingen die de regel of het mechanisme versterken."\n  }],\n  tools: [{\n    name: "write_report_insight",\n    description: "Synthese narrative voor performance report",\n    input_schema: {\n      type: "object",\n      properties: { narrative: { type: "string" } },\n      required: ["narrative"]\n    }\n  }],\n  tool_choice: { type: "tool", name: "write_report_insight" }\n}) }}',
            "options": {},
        },
        "id": "ce07-claude",
        "name": "Claude Insight",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [1720, 400],
        "credentials": CLAUDE_CRED,
    },
    {
        "parameters": {"jsCode": PREPARE_SAVE_JS},
        "id": "ce07-prepare-save",
        "name": "Prepare Save",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1940, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "INSERT INTO performance_reports (\n  id, client_id, period_start, period_end, scope, scope_article_id,\n  pct_on_strategy, pct_general, tone_distribution, execution_rate,\n  engagement_on_strategy, engagement_general, engagement_diff_pct,\n  article_count_strategy, article_count_general, article_count_total,\n  median_on_strategy, median_general,\n  outlier_posts, best_performing_post, daily_breakdown, narrative\n) VALUES (\n  $1, $2, $3, $4, $5, NULLIF($6, ''),\n  $7, $8, $9, $10,\n  $11, $12, $13, $14, $15, $16,\n  $17, $18, $19, $20, $21, $22\n)",
            "options": {
                "queryReplacement": "={{ [ $json.report_id, $json.client_id, $json.period_start, $json.period_end, $json.scope, $json.scope_article_id || '', $json.pct_on_strategy, $json.pct_general, JSON.stringify({ ...($json.tone_distribution || {}), _angle: $json.angle_distribution || {} }), $json.execution_rate, $json.engagement_on_strategy, $json.engagement_general, $json.engagement_diff_pct, $json.article_count_strategy, $json.article_count_general, $json.article_count_total, $json.median_on_strategy, $json.median_general, JSON.stringify($json.outlier_posts), JSON.stringify($json.best_performing_post), JSON.stringify($json.daily_breakdown), $json.narrative ] }}"
            },
        },
        "id": "ce07-insert",
        "name": "Insert Report",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [2160, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {"jsCode": RESPOND_JS},
        "id": "ce07-respond-build",
        "name": "Build Response",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2380, 400],
    },
    {
        "parameters": {"respondWith": "json", "responseBody": "={{ JSON.stringify($json) }}", "options": {}},
        "id": "ce07-respond",
        "name": "Respond OK",
        "type": "n8n-nodes-base.respondToWebhook",
        "typeVersion": 1.1,
        "position": [2600, 400],
    },
]

connections = {
    "Webhook Trigger": {"main": [[{"node": "Parse Input", "type": "main", "index": 0}]]},
    "Parse Input": {"main": [[{"node": "Ensure Reports Table", "type": "main", "index": 0}]]},
    "Ensure Reports Table": {"main": [[{"node": "Get Client", "type": "main", "index": 0}]]},
    "Get Client": {"main": [[{"node": "Get Articles", "type": "main", "index": 0}]]},
    "Get Articles": {"main": [[{"node": "Compute Metrics", "type": "main", "index": 0}]]},
    "Compute Metrics": {"main": [[{"node": "Claude Insight", "type": "main", "index": 0}]]},
    "Claude Insight": {"main": [[{"node": "Prepare Save", "type": "main", "index": 0}]]},
    "Prepare Save": {"main": [[{"node": "Insert Report", "type": "main", "index": 0}]]},
    "Insert Report": {"main": [[{"node": "Build Response", "type": "main", "index": 0}]]},
    "Build Response": {"main": [[{"node": "Respond OK", "type": "main", "index": 0}]]},
}

workflow = {
    "name": "CE-07 (Performance Rapportage)",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-07 - Performance Rapportage.json"
sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_workflow_utils import write_workflow

write_workflow(workflow, out)
print("Wrote", out)
