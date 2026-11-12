#!/usr/bin/env python3
"""Generate CE-01 Theme Director n8n workflow JSON."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_env import load_env, target_env, webhook_path, webhook_url
from prompt_fragments import patch_json_body_system_prompt

MYSQL_CRED = {"mySql": {"id": "taJrWdU0RBkbtU0R", "name": "Content Engine"}}
CLAUDE_CRED = {"httpHeaderAuth": {"id": "GhgOhw7oQpIMZDDo", "name": "Header Auth account"}}


_env = target_env()
load_env(_env if _env != "production" else None)

COMPUTE_CONTEXT_JS = r"""
const client = $('Get Active Clients').item.json;
const existingRows = $('Get Existing Themes').all().map((i) => i.json).filter((r) => r.cycle_week);
const perfRows = $('Get Performance').all().map((i) => i.json).filter((r) => r.headline || r.theme);
const sourceRows = $('Get Sources').all()
  .map((i) => i.json)
  .filter((r) => r.title)
  .filter((r) => {
    const cat = String(r.category || '').toLowerCase();
    return cat !== 'linkedin' && cat !== 'scraper';
  });

function mondayOf(d) {
  const x = new Date(Date.UTC(d.getUTCFullYear(), d.getUTCMonth(), d.getUTCDate()));
  const day = x.getUTCDay() || 7;
  x.setUTCDate(x.getUTCDate() - day + 1);
  return x.toISOString().slice(0, 10);
}

function addDays(iso, days) {
  const d = new Date(iso + 'T12:00:00Z');
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

const existing = new Set(
  existingRows
    .map((r) => String(r.cycle_week || '').slice(0, 10))
    .filter(Boolean),
);

function proposedCountForWeek(isoWeek) {
  return existingRows.filter((r) => {
    const week = String(r.cycle_week || '').slice(0, 10);
    const status = String(r.status || 'proposed').toLowerCase();
    return week === isoWeek && (status === 'proposed' || status === '');
  }).length;
}

const parseInput = $('Parse Run Input').item.json;
const forcedWeeks = Array.isArray(parseInput.refill_cycle_weeks)
  ? parseInput.refill_cycle_weeks.map((w) => String(w).slice(0, 10)).filter(Boolean)
  : [];
const forcedProposalCount = Math.max(
  1,
  Number(parseInput.proposal_count || parseInput.proposalCount || 3),
);

const horizon = Math.max(1, Number(client.planning_horizon_weeks || 4));
const postsPerWeek = Math.max(1, Number(client.posts_per_week || 1));
const today = new Date();
const startMonday = mondayOf(today);

const horizonWeeks = [];
for (let w = 0; w < horizon; w += 1) {
  horizonWeeks.push(addDays(startMonday, w * 7));
}

let missingWeeks;
let weeksToFill;
let themesNeeded;
let proposalCount = postsPerWeek;

if (forcedWeeks.length > 0) {
  proposalCount = forcedProposalCount;
  missingWeeks = forcedWeeks.filter((w) => proposedCountForWeek(w) < proposalCount);
  weeksToFill = missingWeeks.length;
  themesNeeded = missingWeeks.reduce(
    (sum, w) => sum + Math.max(0, proposalCount - proposedCountForWeek(w)),
    0,
  );
} else {
  missingWeeks = horizonWeeks.filter((w) => !existing.has(w));
  weeksToFill = missingWeeks.length;
  themesNeeded = weeksToFill * postsPerWeek;
}

let phases = [];
try {
  phases = typeof client.phases === 'string' ? JSON.parse(client.phases) : client.phases || [];
} catch {
  phases = [];
}

const performance_summary = perfRows.length
  ? perfRows
      .map((r) => {
        const hoofd = r.hba_hoofdoordeel ? `, hoofdoordeel ${r.hba_hoofdoordeel}` : '';
        return `- ${r.headline || r.theme || 'Post'} (engagement ${r.engagement || 0}${hoofd})`;
      })
      .join('\n')
  : 'Geen gepubliceerde posts met engagement-data (testposts uitgesloten).';

const trending_signals_summary = sourceRows.length
  ? sourceRows
      .map((s) => {
        if (s.category === 'url_rss') {
          return `- [rss] ${s.title} (feed — geen artikel-URL gebruiken)`;
        }
        if (s.category === 'blogs') {
          return `- [blogs] ${s.title} (blogoverzicht — onderliggende posts, geen overzichts-URL gebruiken)`;
        }
        if (s.category === 'youtube') {
          return `- [youtube] ${s.title} (kanaal-RSS — video-updates, geen transcripties)`;
        }
        if (s.category === 'document') {
          return `- [document] ${s.title} (eigen PDF — interne kennis, geen extern nieuws)`;
        }
        if (s.category === 'drive') {
          return `- [drive] ${s.title} (eigen Drive-document — interne kennis)`;
        }
        return `- [${s.category || s.source_kind || 'bron'}] ${s.title}${s.url ? ': ' + s.url : ''}`;
      })
      .join('\n')
  : 'Geen actieve bronnen geconfigureerd — baseer voorstellen op fasecyclus en performance.';

const rhythmRow = $('Get Content Rhythm').first()?.json ?? {};
let rhythmRoles = [];
let rhythmPostsPerWeek = postsPerWeek;
try {
  const parsed = typeof rhythmRow.roles === 'string' ? JSON.parse(rhythmRow.roles) : rhythmRow.roles;
  if (Array.isArray(parsed)) rhythmRoles = parsed;
} catch {
  rhythmRoles = [];
}
if (Number(rhythmRow.posts_per_week) > 0) {
  rhythmPostsPerWeek = Number(rhythmRow.posts_per_week);
}

function rhythmLabel(name) {
  const key = String(name || '').toLowerCase();
  if (key === 'autoriteit') return 'Autoriteit';
  if (key === 'inzicht') return 'Inzicht';
  if (key.includes('product')) return 'Product of dienst';
  return String(name || '').trim();
}

const rhythmLines = rhythmRoles.map((role, index) => {
  const name = String(role?.role_name || role?.roleName || '').trim();
  if (!name) return '';
  return `${index + 1}. ${rhythmLabel(name)} (${name})`;
}).filter(Boolean);

const rhythm_summary = rhythmLines.length
  ? `Vast content-ritme (${rhythmRoles.length} rollen per week):\n${rhythmLines.join('\n')}\n\nWijs per week thema 1 aan rol 1, thema 2 aan rol 2, enz. — herhaal het patroon cyclisch.`
  : 'Geen content-ritme geconfigureerd — varieer tussen autoriteit, inzicht en product/dienst.';

return {
  json: {
    client,
    missingWeeks,
    weeksToFill,
    themesNeeded,
    postsPerWeek: Math.max(postsPerWeek, rhythmPostsPerWeek),
    proposalCount,
    existingRows,
    phases,
    performance_summary,
    trending_signals_summary,
    rhythmRoles,
    rhythm_summary,
    skip: themesNeeded <= 0,
  },
};
"""

PREPARE_THEMES_JS = r"""
const ctx = $('Compute Context').item.json;
const claude = $('Claude Theme Director').item.json;
const input = claude?.content?.[0]?.input ?? {};
const rawThemes = Array.isArray(input.themes) ? input.themes : [];

if (ctx.skip) {
  return [{ json: { skip: true, client_id: ctx.client.id, reason: 'Horizon already filled' } }];
}

const { client, missingWeeks, postsPerWeek, proposalCount, existingRows, rhythmRoles = [] } = ctx;

function rhythmRoleForWeekIndex(weekIso, indexInWeek) {
  if (!Array.isArray(rhythmRoles) || rhythmRoles.length === 0) return null;
  const role = rhythmRoles[indexInWeek % rhythmRoles.length];
  return String(role?.role_name || role?.roleName || '').trim() || null;
}

function proposedCountForWeek(isoWeek) {
  return (existingRows || []).filter((r) => {
    const week = String(r.cycle_week || '').slice(0, 10);
    const status = String(r.status || 'proposed').toLowerCase();
    return week === isoWeek && (status === 'proposed' || status === '');
  }).length;
}

const slots = [];
const weekRoleCounts = {};
for (const week of missingWeeks) {
  const perWeek = Math.max(1, Number(proposalCount || postsPerWeek));
  const need = Math.max(0, perWeek - proposedCountForWeek(week));
  for (let p = 0; p < need; p += 1) {
    const roleIndex = weekRoleCounts[week] || 0;
    weekRoleCounts[week] = roleIndex + 1;
    slots.push({ cycle_week: week, slot_index: slots.length, week_role_index: roleIndex });
  }
}

const out = [];
const assignedSlots = new Set();
const usedTitles = new Set();
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

function isFeedOrSearchUrl(url) {
  const u = String(url || '').trim().toLowerCase();
  if (!u) return true;
  if (u.includes('news.google.com/rss/search')) return true;
  if (u.includes('news.google.com/rss/feeds')) return true;
  if (/\/rss(\?|$)/.test(u) && !u.includes('/rss/articles/')) return true;
  if (/\.(xml|rss)(\?|$)/.test(u)) return true;
  return false;
}

function isLinkedInContentUrl(url) {
  const u = String(url || '').trim().toLowerCase();
  if (!u) return false;
  return u.includes('linkedin.com/') || u.includes('lnkd.in/');
}

const sortedThemes = [...rawThemes].sort((a, b) => {
  const wa = Number(a.horizon_week_index || 1);
  const wb = Number(b.horizon_week_index || 1);
  if (wa !== wb) return wa - wb;
  return String(a.title || '').localeCompare(String(b.title || ''), 'nl');
});

for (const t of sortedThemes) {
  const weekIdx = Math.max(1, Number(t.horizon_week_index || 1)) - 1;
  const title = String(t.title || '').trim();
  const titleKey = title.toLowerCase();
  if (titleKey && usedTitles.has(titleKey)) continue;

  let slot = null;
  let slotIndex = -1;
  const targetWeek = missingWeeks[weekIdx];
  for (let i = 0; i < slots.length; i += 1) {
    if (assignedSlots.has(i)) continue;
    if (targetWeek && slots[i].cycle_week !== targetWeek) continue;
    assignedSlots.add(i);
    slotIndex = i;
    slot = slots[i];
    break;
  }
  if (!slot) continue;

  usedTitles.add(titleKey);
  const themeId = makeUuid();
  const phaseKey = String(t.phase_key || isoWeekFromDate(slot.cycle_week) || `week_${weekIdx + 1}`);
  const rhythmRole = rhythmRoleForWeekIndex(slot.cycle_week, slot.week_role_index ?? slotIndex);
  function stripThemeStoryLeadIn(text) {
    let body = String(text || '').trim();
    for (let i = 0; i < 6; i += 1) {
      const next = body
        .replace(/^ritmrol\s*(?:\d+\s*=\s*)?[^.\n]+(?:\.\s*)?/iu, '')
        .replace(/^ritmrol:\s*[^.\n]+\.\s*/iu, '')
        .replace(/^trending signaal:\s*/iu, '')
        .trim();
      if (next === body) break;
      body = next;
    }
    return body;
  }
  const motivation = stripThemeStoryLeadIn(t.motivation);
  const rationale = stripThemeStoryLeadIn(t.rationale);
  out.push({
    json: {
      theme_id: themeId,
      client_id: client.id,
      cycle_week: slot.cycle_week,
      phase_key: phaseKey,
      title: title || `Theme ${slotIndex + 1}`,
      motivation,
      rationale,
      rhythm_role: rhythmRole,
      source_article_url: t.source_article_url && !isFeedOrSearchUrl(t.source_article_url) && !isLinkedInContentUrl(t.source_article_url)
        ? String(t.source_article_url).trim()
        : null,
    },
  });
}

if (out.length === 0) {
  throw new Error('Claude returned no themes while weeks still need filling.');
}

return out;
"""

RESPOND_JS = r"""
const prepared = $('Prepare Theme Rows').all().map((i) => i.json).filter((r) => r.theme_id);
const ctx = $('Compute Context').item.json;
return {
  json: {
    ok: true,
    skipped: false,
    themes_created: prepared.length,
    theme_ids: prepared.map((r) => r.theme_id),
    client_id: ctx.client.id,
    client_name: ctx.client.name,
  },
};
"""

ROUTE_AFTER_CLIENT_JS = r"""
const j = $input.first()?.json ?? {};
const clientId = j.client_id || $('Get Active Clients').item.json.id;
return {
  json: {
    ...j,
    client_id: clientId,
    trigger: $('Parse Run Input').item.json.trigger,
  },
};
"""

PREPARE_CE02_TRIGGERS_JS = r"""
const rows = $('Get Themes For CE-02').all().map((i) => i.json).filter((r) => r.theme_id);
if (!rows.length) {
  return [{ json: { client_id: $('Route After Client').item.json.client_id, phase: 'ce02', skipped: true } }];
}
return rows.map((r) => ({
  json: { theme_id: r.theme_id, client_id: r.client_id, phase: 'ce02' },
}));
"""

PREPARE_CE03_TRIGGERS_JS = """
const rows = $('Get Slots For CE-03').all().map((i) => i.json).filter((r) => r.slot_id);
if (!rows.length) {
  return [{ json: { client_id: $('Route After Client').item.json.client_id, phase: 'ce03', skipped: true } }];
}

return rows.map((r) => ({
  json: { slot_id: r.slot_id, client_id: r.client_id, phase: 'ce03' },
}));
"""

CE02_WEBHOOK = webhook_url("ce02-research")
CE03_WEBHOOK = webhook_url("ce03-write-article")
CE01_WEBHOOK_PATH = webhook_path("ce01-theme-director")

nodes = [
    {
        "parameters": {"httpMethod": "POST", "path": CE01_WEBHOOK_PATH, "responseMode": "responseNode", "options": {}},
        "id": "ce01-webhook",
        "name": "Webhook Trigger",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [400, 300],
        "webhookId": f"{CE01_WEBHOOK_PATH}-webhook",
    },
    {
        "parameters": {"rule": {"interval": [{"field": "weeks", "triggerAtDay": [1], "triggerAtHour": 6}]}},
        "id": "ce01-schedule",
        "name": "Weekly Schedule",
        "type": "n8n-nodes-base.scheduleTrigger",
        "typeVersion": 1.2,
        "position": [400, 480],
    },
    {
        "parameters": {
            "jsCode": "const raw = $input.first()?.json ?? {};\nconst isWebhook = raw.body !== undefined || raw.headers !== undefined;\nconst body = raw.body ?? raw;\nconst clientId = body.client_id ?? body.clientId ?? '';\nconst refillRaw = body.refill_cycle_weeks ?? body.refillCycleWeeks ?? [];\nconst refillCycleWeeks = Array.isArray(refillRaw)\n  ? refillRaw.map((w) => String(w).slice(0, 10)).filter(Boolean)\n  : [];\nconst proposalCount = Math.max(1, Number(body.proposal_count ?? body.proposalCount ?? 3));\nreturn {\n  json: {\n    client_id: String(clientId || '').trim(),\n    trigger: isWebhook ? 'webhook' : 'schedule',\n    refill_cycle_weeks: refillCycleWeeks,\n    proposal_count: proposalCount,\n  },\n};"
        },
        "id": "ce01-parse",
        "name": "Parse Run Input",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [640, 380],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT c.id, c.name, c.archetype, c.archetype_traits, c.adversary, c.brand_goal, c.tone_of_voice, c.writing_guide,\n  COALESCE(c.planning_horizon_weeks, 4) AS planning_horizon_weeks,\n  COALESCE(c.posts_per_week, 1) AS posts_per_week,\n  c.phases,\n  bn.geloofwaardigheidstest, bn.mechanisme,\n  bl.markt_archetype AS branche_archetype\nFROM clients c\nLEFT JOIN brand_narratives bn ON bn.client_id = c.id AND COALESCE(bn.is_current, 1) = 1\nLEFT JOIN branche_library bl ON bl.branche_code = bn.branche_code\nWHERE (NULLIF($1, '') IS NULL OR c.id = $1)\n  AND (\n    NULLIF($1, '') IS NOT NULL\n    OR COALESCE(c.ce01_weekly_schedule, 1) = 1\n  )\nORDER BY c.name ASC",
            "options": {"queryReplacement": "={{ $('Parse Run Input').item.json.client_id }}"},
        },
        "id": "ce01-get-clients",
        "name": "Get Active Clients",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1080, 380],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {"batchSize": 1, "options": {}},
        "id": "ce01-loop-clients",
        "name": "Loop Clients",
        "type": "n8n-nodes-base.splitInBatches",
        "typeVersion": 3,
        "position": [1300, 380],
    },
    {
        "parameters": {},
        "id": "ce01-all-clients-done",
        "name": "All Clients Done",
        "type": "n8n-nodes-base.noOp",
        "typeVersion": 1,
        "position": [1300, 200],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT DATE(cycle_week) AS cycle_week, status FROM themes\nWHERE client_id = $1\n  AND COALESCE(status, 'proposed') NOT IN ('deleted')\n  AND (title IS NULL OR title NOT LIKE '[nulmeting]%')\n  AND cycle_week >= CURDATE() - INTERVAL 1 DAY\nORDER BY cycle_week ASC",
            "options": {"queryReplacement": "={{ [ $json.id ] }}"},
        },
        "id": "ce01-existing-themes",
        "name": "Get Existing Themes",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1520, 480],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT COALESCE(a.headline_human, a.headline) AS headline, s.phase_key, s.theme,\n  (COALESCE(a.interaction_likes, 0) + COALESCE(a.interaction_comments, 0)) AS engagement,\n  JSON_UNQUOTE(JSON_EXTRACT(hs.raw_llm_response, '$.hoofdoordeel')) AS hba_hoofdoordeel\nFROM articles a\nJOIN content_slots s ON s.id = a.slot_id\nLEFT JOIN hba_scores hs ON hs.id = (\n  SELECT hs2.id FROM hba_scores hs2 WHERE hs2.post_id = a.id ORDER BY hs2.scored_at DESC LIMIT 1\n)\nWHERE s.client_id = $1\n  AND COALESCE(s.origin, '') NOT IN ('baseline')\n  AND COALESCE(a.headline, '') NOT LIKE 'HBA-test:%'\n  AND COALESCE(a.headline_human, '') NOT LIKE 'HBA-test:%'\n  AND a.status IN ('published', 'archived')\nORDER BY engagement DESC\nLIMIT 8",
            "options": {"queryReplacement": "={{ [ $('Get Active Clients').item.json.id ] }}"},
        },
        "id": "ce01-performance",
        "name": "Get Performance",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1520, 380],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT title, url, category, source_kind, description, trello_board_id, last_checked_at\nFROM sources\nWHERE client_id = $1 AND COALESCE(active, 1) = 1\nORDER BY last_checked_at DESC, title ASC\nLIMIT 20",
            "options": {"queryReplacement": "={{ [ $('Get Active Clients').item.json.id ] }}"},
        },
        "id": "ce01-sources",
        "name": "Get Sources",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1740, 380],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT posts_per_week, roles FROM content_rhythms\nWHERE client_id = $1 AND active = 1\nORDER BY created_at DESC\nLIMIT 1",
            "options": {"queryReplacement": "={{ [ $('Get Active Clients').item.json.id ] }}"},
        },
        "id": "ce01-content-rhythm",
        "name": "Get Content Rhythm",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1520, 580],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": COMPUTE_CONTEXT_JS},
        "id": "ce01-compute",
        "name": "Compute Context",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1960, 380],
    },
    {
        "parameters": {
            "conditions": {
                "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict"},
                "conditions": [
                    {
                        "id": "skip-check",
                        "leftValue": "={{ $json.skip }}",
                        "rightValue": True,
                        "operator": {"type": "boolean", "operation": "false"},
                    }
                ],
                "combinator": "and",
            },
            "options": {},
        },
        "id": "ce01-if-needs-themes",
        "name": "Needs Themes?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 2,
        "position": [2180, 380],
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
            "jsonBody": '={{ JSON.stringify({\n  model: "claude-sonnet-4-6",\n  max_tokens: 4096,\n  system: "Je bent de content-strateeg voor " + $json.client.name + ".\\nArchetype: " + $json.client.archetype + " (" + ($json.client.archetype_traits || "") + ")\\nTegenstander/adversary: " + $json.client.adversary + "\\nMerkdoel: " + $json.client.brand_goal + "\\nSchrijfwijzer: " + ($json.client.writing_guide || "niet ingevuld") + "\\nRegel van het merk: " + ($json.client.geloofwaardigheidstest || "niet ingevuld") + "\\nMechanisme: " + ($json.client.mechanisme || "niet ingevuld") + (String($json.client.archetype || "").trim() && String($json.client.branche_archetype || "").trim() && String($json.client.archetype).trim().toLowerCase() === String($json.client.branche_archetype).trim().toLowerCase() ? "\\n\\nHet archetype van dit merk is ook het gangbare archetype van de branche. Passen bij het archetype maakt een thema hier niet onderscheidend. Stel alleen een thema voor als versterking van het merk als het een concreet element van het mechanisme of de regel laat zien. Een warm of menselijk thema zonder dat element is hoogstens neutraal." : "") + "\\n\\nVolg de schrijfwijzer strikt: doelgroep, producten en wat je niet post. Bronnen zijn inspiratie, geen vrijbrief om van het merk af te wijken.\\n\\nBeschikbare fasen:\\n" + ($json.phases || []).map(p => "- " + p.key + ": " + p.label + " — " + (p.description || "")).join("\\n") + "\\n\\nGebruik geen interne labels (HBA-patroon, testpost-titels). Baseer je op hoofdoordeel in de performance-lijst, niet op koppen. Het woord regel alleen voor de regel van het merk (geloofwaardigheidstest), niet voor feiten of vakkennis. Beschrijf de regel zoals in de geloofwaardigheidstest; een belofte die op de regel lijkt maar iets anders zegt, is geen uitwerking van de regel.\\n\\nOutput uitsluitend via tool propose_themes.",\n  messages: [\n    {\n      role: "user",\n      content: "Content-ritme:\\n" + ($json.rhythm_summary || "Geen ritme geconfigureerd.") + "\\n\\nBeste presterende content:\\n" + $json.performance_summary + "\\n\\nTrending signalen / bronnen:\\n" + $json.trending_signals_summary + "\\n\\nOPDRACHT: Stel precies " + $json.themesNeeded + " thema(\'s) voor voor de ontbrekende week(en) in de planning-horizon (" + $json.weeksToFill + " week(s), " + $json.postsPerWeek + " per week). Gebruik horizon_week_index (1 = eerste ontbrekende week). Stem elk thema af op de ritmrol voor die positie in de week (thema 1 = rol 1, thema 2 = rol 2, enz.). Begin motivation en rationale niet met Ritmrol of Trending signaal — de app toont de ritmrol apart. Motiveer wel trending vs fasecyclus én ritmrol in de inhoud. Geen datums — die worden in code gezet. source_article_url: directe URL naar één nieuwsartikel bij de uitgever; NOOIT een RSS-feed-URL, news.google.com/rss/search link, of LinkedIn-post/ profiel-URL (eigen gescrapete posts — geen content-loop). Laat leeg als onbekend."\n    }\n  ],\n  tools: [\n    {\n      name: "propose_themes",\n      description: "Thema-voorstellen voor ontbrekende weken",\n      input_schema: {\n        type: "object",\n        properties: {\n          themes: {\n            type: "array",\n            items: {\n              type: "object",\n              properties: {\n                horizon_week_index: { type: "integer", minimum: 1 },\n                phase_key: { type: "string" },\n                title: { type: "string" },\n                motivation: { type: "string" },\n                rationale: { type: "string" },\n                source_article_url: { type: "string" }\n              },\n              required: ["horizon_week_index", "phase_key", "title", "motivation", "rationale"]\n            }\n          }\n        },\n        required: ["themes"]\n      }\n    }\n  ],\n  tool_choice: { type: "tool", name: "propose_themes" }\n}) }}',
            "options": {},
        },
        "id": "ce01-claude",
        "name": "Claude Theme Director",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [2400, 280],
        "credentials": CLAUDE_CRED,
    },
    {
        "parameters": {"jsCode": PREPARE_THEMES_JS},
        "id": "ce01-prepare",
        "name": "Prepare Theme Rows",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2620, 280],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "INSERT INTO themes (id, client_id, cycle_week, phase_key, title, motivation, rationale, source_article_url, status, feedback)\nVALUES ($1, $2, $3, $4, $5, $6, $7, $8, 'proposed', 'none')",
            "options": {
                "queryReplacement": "={{ [ $json.theme_id, $json.client_id, $json.cycle_week, $json.phase_key, $json.title, $json.motivation, $json.rationale, $json.source_article_url ] }}"
            },
        },
        "id": "ce01-insert",
        "name": "Insert Theme",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [2840, 280],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {"aggregate": "aggregateAllItemData", "options": {}},
        "id": "ce01-aggregate",
        "name": "Aggregate Themes",
        "type": "n8n-nodes-base.aggregate",
        "typeVersion": 1,
        "position": [3060, 280],
    },
    {
        "parameters": {"jsCode": RESPOND_JS},
        "id": "ce01-build-response",
        "name": "Build Response",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [3280, 280],
    },
    {
        "parameters": {"jsCode": ROUTE_AFTER_CLIENT_JS},
        "id": "ce01-route-after-client",
        "name": "Route After Client",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [3500, 380],
    },
    {
        "parameters": {
            "conditions": {
                "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict"},
                "conditions": [
                    {
                        "id": "webhook-only",
                        "leftValue": "={{ $json.trigger }}",
                        "rightValue": "webhook",
                        "operator": {"type": "string", "operation": "equals"},
                    }
                ],
                "combinator": "and",
            },
            "options": {},
        },
        "id": "ce01-if-webhook",
        "name": "If Webhook",
        "type": "n8n-nodes-base.if",
        "typeVersion": 2,
        "position": [3940, 280],
    },
    {
        "parameters": {
            "conditions": {
                "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict"},
                "conditions": [
                    {
                        "id": "schedule-only",
                        "leftValue": "={{ $('Parse Run Input').item.json.trigger === 'schedule' }}",
                        "rightValue": True,
                        "operator": {"type": "boolean", "operation": "true"},
                    }
                ],
                "combinator": "and",
            },
            "options": {},
        },
        "id": "ce01-if-schedule",
        "name": "If Run Pipeline Cascade",
        "type": "n8n-nodes-base.if",
        "typeVersion": 2,
        "position": [3940, 520],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT t.id AS theme_id, t.client_id, t.title, DATE(t.cycle_week) AS cycle_week\nFROM themes t\nWHERE t.client_id = $1\n  AND COALESCE(t.status, 'proposed') IN ('proposed', 'draft', 'submitted')\n  AND (t.title IS NULL OR t.title NOT LIKE '[nulmeting]%')\n  AND DATE(t.cycle_week) <= CURDATE()\nORDER BY t.cycle_week ASC",
            "options": {"queryReplacement": "={{ [ $json.client_id ] }}"},
        },
        "id": "ce01-get-themes-ce02",
        "name": "Get Themes For CE-02",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [4160, 520],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": PREPARE_CE02_TRIGGERS_JS},
        "id": "ce01-prepare-ce02",
        "name": "Prepare CE-02 Triggers",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [4380, 520],
    },
    {
        "parameters": {
            "conditions": {
                "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict"},
                "conditions": [
                    {
                        "id": "has-theme",
                        "leftValue": "={{ $json.theme_id }}",
                        "rightValue": "",
                        "operator": {"type": "string", "operation": "notEmpty"},
                    }
                ],
                "combinator": "and",
            },
            "options": {},
        },
        "id": "ce01-if-ce02-target",
        "name": "Has CE-02 Target?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 2,
        "position": [4600, 520],
    },
    {
        "parameters": {
            "method": "POST",
            "url": CE02_WEBHOOK,
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": '={{ JSON.stringify({ theme_id: $json.theme_id, client_id: $json.client_id }) }}',
            "options": {"timeout": 120000},
        },
        "id": "ce01-trigger-ce02-phase",
        "name": "Trigger CE-02 Phase",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [4820, 440],
        "continueOnFail": True,
    },
    {
        "parameters": {"aggregate": "aggregateAllItemData", "options": {}},
        "id": "ce01-aggregate-ce02",
        "name": "Aggregate CE-02 Runs",
        "type": "n8n-nodes-base.aggregate",
        "typeVersion": 1,
        "position": [5040, 440],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT s.id AS slot_id, s.client_id\nFROM content_slots s\nINNER JOIN themes t ON t.id = s.theme_id\nWHERE s.client_id = $1\n  AND COALESCE(s.status, '') IN ('themed', 'research_done')\n  AND COALESCE(s.origin, 'theme') <> 'fastlane'\n  AND (t.title IS NULL OR t.title NOT LIKE '[nulmeting]%')\n  AND (s.input_human IS NULL OR s.input_human NOT LIKE '%testsetFreeze%')\n  AND DATE(t.cycle_week) <= DATE_SUB(CURDATE(), INTERVAL 7 DAY)\n  AND NOT EXISTS (\n    SELECT 1 FROM articles a\n    WHERE a.slot_id = s.id\n      AND COALESCE(a.status, '') NOT IN ('deleted')\n  )\nORDER BY t.cycle_week ASC",
            "options": {"queryReplacement": "={{ [ $('Route After Client').item.json.client_id ] }}"},
        },
        "id": "ce01-get-slots-ce03",
        "name": "Get Slots For CE-03",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [5260, 520],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": PREPARE_CE03_TRIGGERS_JS},
        "id": "ce01-prepare-ce03",
        "name": "Prepare CE-03 Triggers",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [5260, 520],
    },
    {
        "parameters": {
            "conditions": {
                "options": {"caseSensitive": True, "leftValue": "", "typeValidation": "strict"},
                "conditions": [
                    {
                        "id": "has-slot",
                        "leftValue": "={{ $json.slot_id }}",
                        "rightValue": "",
                        "operator": {"type": "string", "operation": "notEmpty"},
                    }
                ],
                "combinator": "and",
            },
            "options": {},
        },
        "id": "ce01-if-ce03-target",
        "name": "Has CE-03 Target?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 2,
        "position": [5480, 520],
    },
    {
        "parameters": {
            "method": "POST",
            "url": CE03_WEBHOOK,
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": '={{ JSON.stringify({ slot_id: $json.slot_id }) }}',
            "options": {"timeout": 180000},
        },
        "id": "ce01-trigger-ce03-phase",
        "name": "Trigger CE-03 Phase",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [5700, 440],
        "continueOnFail": True,
    },
    {
        "parameters": {"aggregate": "aggregateAllItemData", "options": {}},
        "id": "ce01-aggregate-ce03",
        "name": "Aggregate CE-03 Runs",
        "type": "n8n-nodes-base.aggregate",
        "typeVersion": 1,
        "position": [5920, 440],
    },
    {
        "parameters": {
            "jsCode": "return { json: { ok: true, skipped: true, reason: 'Planning horizon already has themes for upcoming weeks', client_id: $('Compute Context').item.json.client.id, client_name: $('Compute Context').item.json.client.name } };"
        },
        "id": "ce01-skip-response",
        "name": "Skip Response",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2400, 520],
    },
    {
        "parameters": {"respondWith": "json", "responseBody": "={{ JSON.stringify($json) }}", "options": {}},
        "id": "ce01-respond",
        "name": "Respond OK",
        "type": "n8n-nodes-base.respondToWebhook",
        "typeVersion": 1.1,
        "position": [4160, 280],
    },
]

connections = {
    "Webhook Trigger": {"main": [[{"node": "Parse Run Input", "type": "main", "index": 0}]]},
    "Weekly Schedule": {"main": [[{"node": "Parse Run Input", "type": "main", "index": 0}]]},
    "Parse Run Input": {"main": [[{"node": "Get Active Clients", "type": "main", "index": 0}]]},
    "Get Active Clients": {"main": [[{"node": "Loop Clients", "type": "main", "index": 0}]]},
    "Loop Clients": {
        "main": [
            [{"node": "All Clients Done", "type": "main", "index": 0}],
            [{"node": "Get Existing Themes", "type": "main", "index": 0}],
        ]
    },
    "Get Existing Themes": {"main": [[{"node": "Get Performance", "type": "main", "index": 0}]]},
    "Get Performance": {"main": [[{"node": "Get Content Rhythm", "type": "main", "index": 0}]]},
    "Get Content Rhythm": {"main": [[{"node": "Get Sources", "type": "main", "index": 0}]]},
    "Get Sources": {"main": [[{"node": "Compute Context", "type": "main", "index": 0}]]},
    "Compute Context": {"main": [[{"node": "Needs Themes?", "type": "main", "index": 0}]]},
    "Needs Themes?": {
        "main": [
            [{"node": "Claude Theme Director", "type": "main", "index": 0}],
            [{"node": "Skip Response", "type": "main", "index": 0}],
        ]
    },
    "Claude Theme Director": {"main": [[{"node": "Prepare Theme Rows", "type": "main", "index": 0}]]},
    "Prepare Theme Rows": {"main": [[{"node": "Insert Theme", "type": "main", "index": 0}]]},
    "Insert Theme": {"main": [[{"node": "Aggregate Themes", "type": "main", "index": 0}]]},
    "Aggregate Themes": {"main": [[{"node": "Build Response", "type": "main", "index": 0}]]},
    "Build Response": {"main": [[{"node": "Route After Client", "type": "main", "index": 0}]]},
    "Skip Response": {"main": [[{"node": "Route After Client", "type": "main", "index": 0}]]},
    "Route After Client": {
        "main": [
            [
                {"node": "If Webhook", "type": "main", "index": 0},
                {"node": "If Run Pipeline Cascade", "type": "main", "index": 0},
            ]
        ]
    },
    "If Webhook": {
        "main": [
            [{"node": "Respond OK", "type": "main", "index": 0}],
            [],
        ]
    },
    "Respond OK": {"main": [[{"node": "Loop Clients", "type": "main", "index": 0}]]},
    "If Run Pipeline Cascade": {
        "main": [
            [{"node": "Get Themes For CE-02", "type": "main", "index": 0}],
            [],
        ]
    },
    "Get Themes For CE-02": {"main": [[{"node": "Prepare CE-02 Triggers", "type": "main", "index": 0}]]},
    "Prepare CE-02 Triggers": {"main": [[{"node": "Has CE-02 Target?", "type": "main", "index": 0}]]},
    "Has CE-02 Target?": {
        "main": [
            [{"node": "Trigger CE-02 Phase", "type": "main", "index": 0}],
            [{"node": "Get Slots For CE-03", "type": "main", "index": 0}],
        ]
    },
    "Trigger CE-02 Phase": {"main": [[{"node": "Aggregate CE-02 Runs", "type": "main", "index": 0}]]},
    "Aggregate CE-02 Runs": {"main": [[{"node": "Get Slots For CE-03", "type": "main", "index": 0}]]},
    "Get Slots For CE-03": {"main": [[{"node": "Prepare CE-03 Triggers", "type": "main", "index": 0}]]},
    "Prepare CE-03 Triggers": {"main": [[{"node": "Has CE-03 Target?", "type": "main", "index": 0}]]},
    "Has CE-03 Target?": {
        "main": [
            [{"node": "Trigger CE-03 Phase", "type": "main", "index": 0}],
            [{"node": "Loop Clients", "type": "main", "index": 0}],
        ]
    },
    "Trigger CE-03 Phase": {"main": [[{"node": "Aggregate CE-03 Runs", "type": "main", "index": 0}]]},
    "Aggregate CE-03 Runs": {"main": [[{"node": "Loop Clients", "type": "main", "index": 0}]]},
}

workflow = {
    "name": "CE-01 (Theme Director)",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
}

out = Path(__file__).resolve().parent.parent / "json" / "n8n" / "CE-01 - Theme Director.json"
from n8n_workflow_utils import write_workflow

for node in nodes:
    if node.get("name") == "Claude Theme Director":
        body = node["parameters"]["jsonBody"]
        node["parameters"]["jsonBody"] = patch_json_body_system_prompt(body)

write_workflow(workflow, out)
print("Wrote", out)
