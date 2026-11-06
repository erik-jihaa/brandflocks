#!/usr/bin/env python3
"""Regenerate CE-04 Review & Publish workflow JSON with shared MySQL fixes + regelbreuk-check."""
import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent
JSON_DIR = ROOT.parent.parent / "JSON"
OUT = JSON_DIR / "CE-04 - Review & Publish.json"

SOURCE_CANDIDATES = (
    JSON_DIR / "CE-04 - Review & Publish.json",
    JSON_DIR / "CE-04 (Review & Publish).json",
    JSON_DIR / "CE-04 (Review & Publish)(1).json",
)

sys.path.insert(0, str(ROOT))
from n8n_workflow_utils import write_workflow  # noqa: E402
from prompt_fragments import (  # noqa: E402
    hba_regel_letterlijk_eis_js_literal,
    hba_regelbreuk_definitie_js_literal,
)

GET_ARTICLE_SQL = (
    "SELECT a.*, s.phase_key, s.theme, s.goal, s.status AS slot_status, c.name AS client_name, "
    "c.archetype, c.archetype_traits, c.adversary, c.brand_goal, c.tone_of_voice, c.writing_guide, "
    "(SELECT bn.geloofwaardigheidstest FROM brand_narratives bn "
    "WHERE bn.client_id = s.client_id AND bn.is_current = 1 ORDER BY bn.version DESC LIMIT 1) "
    "AS hba_geloofwaardigheidstest, "
    "(SELECT bn.verboden_logica FROM brand_narratives bn "
    "WHERE bn.client_id = s.client_id AND bn.is_current = 1 ORDER BY bn.version DESC LIMIT 1) "
    "AS hba_verboden_logica\n"
    "FROM articles a\n"
    "JOIN content_slots s ON s.id = a.slot_id\n"
    "JOIN clients c ON c.id = s.client_id\n"
    "WHERE a.slot_id = $1\n"
    "ORDER BY a.id DESC\n"
    "LIMIT 1"
)

VALIDATE_ARTICLE_JS = r"""const row = $input.first()?.json ?? {};
const slotId = $('Parse Input').first().json.slot_id;

if (!row.id) {
  throw new Error(`Geen artikel gevonden voor slot_id: ${slotId}`);
}

const contentHuman = row.content_human || '';
const contentAi = row.content_ai || '';
const contentForReview = contentHuman || contentAi;

if (!contentForReview.trim()) {
  throw new Error('Artikel heeft geen content om te reviewen.');
}

const regelTest = String(row.hba_geloofwaardigheidstest || '').trim();
let verboden = [];
try {
  const raw = row.hba_verboden_logica;
  const parsed = typeof raw === 'string' && raw.trim() !== '' ? JSON.parse(raw) : raw;
  verboden = Array.isArray(parsed) ? parsed.map((v) => String(v).trim()).filter(Boolean) : [];
} catch (e) {
  verboden = [];
}
const regelLines = [];
if (regelTest !== '') regelLines.push(`Geloofwaardigheidstest: ${regelTest}`);
if (verboden.length) regelLines.push(`Verboden logica:\n- ${verboden.join('\n- ')}`);

return {
  json: {
    ...row,
    slot_id: slotId,
    article_id: row.id,
    content_for_review: contentForReview,
    content_baseline: contentAi,
    hashtags: row.hashtags_final || row.hashtags_suggested || '',
    tone: row.tone_final || row.tone_suggested || 'b2b',
    angle: row.angle_final || row.angle_suggested || 'general',
    platform: row.platform_final || row.platform_suggested || 'linkedin',
    regels_merk: regelLines.length ? regelLines.join('\n') : 'Geen regels vastgelegd.',
    hba_geloofwaardigheidstest: regelTest,
    hba_verboden_items: verboden
  }
};"""

CLAUDE_REVIEW_JSON_BODY = (
    r"""={{ JSON.stringify({
  model: "claude-sonnet-4-6",
  max_tokens: 4096,
  system: "Je bent senior redacteur voor " + $json.client_name + ".\n\nMERK:\nArchetype: " + $json.archetype + " — " + $json.archetype_traits + "\nTegenstander: " + $json.adversary + "\nMerkdoel: " + $json.brand_goal + "\n\nSchrijfwijzer:\n" + $json.writing_guide + "\n\nTone of voice:\n" + $json.tone_of_voice + "\n\nREGELS VAN HET MERK:\n" + $json.regels_merk + "\n\n__REGELBREUK_DEFINITIE__\n\n__REGEL_LETTERLIJK__\n\nGeef constructieve redactionele feedback op ingediende posts. Wees concreet: citeer korte fragmenten. Max 4 tekstpunten. Geef optioneel 1 beeld-feedback als er geen afbeelding is geüpload. Controleer daarnaast altijd expliciet of de post een regel van het merk breekt, volgens de definitie van REGELBREUK hierboven. Een regelbreuk meld je alleen in het veld regelbreuk, niet ook als tekstpunt.",
  messages: [
    {
      role: "user",
      content: "THEMA: " + $json.theme + "\nFase: " + $json.phase_key + "\n\nHEADLINE:\n" + $json.headline + "\n\nTEKST (human edit):\n" + $json.content_for_review + "\n\nORIGINELE AI-TEKST:\n" + $json.content_baseline + "\n\nHASHTAGS: " + $json.hashtags + "\nTone: " + $json.tone + " | Angle: " + $json.angle + " | Platform: " + $json.platform + "\n\nReview deze ingediende post. Focus op merkfit, helderheid, tone, en afwijkingen t.o.v. het AI-concept. Breekt de ingediende tekst een regel van het merk? Vul dan regelbreuk: regel = exacte kopie (woord voor woord) van de ene geschonden regel uit REGELS VAN HET MERK — meestal de geloofwaardigheidstest-regel, anders één regel uit verboden logica. Geen parafrase, geen samenvatting; citaat = de letterlijke zin uit de ingediende tekst. Geen regelbreuk, of geen regels vastgelegd: regelbreuk = null. Forceer geen regelbreuk. Output via tool."
    }
  ],
  tools: [
    {
      name: "submit_review",
      description: "Redactionele feedback voor de Content Engine UI",
      input_schema: {
        type: "object",
        properties: {
          summary: { type: "string" },
          text_feedback: {
            type: "array",
            items: {
              type: "object",
              properties: {
                comment: { type: "string" },
                excerpt: { type: "string" },
                action_label: { type: "string" }
              },
              required: ["comment"]
            }
          },
          image_feedback: {
            type: "object",
            properties: {
              comment: { type: "string" },
              action_label: { type: "string" }
            }
          },
          regelbreuk: {
            type: ["object", "null"],
            properties: {
              regel: { type: "string" },
              citaat: { type: "string" }
            },
            required: ["regel", "citaat"]
          }
        },
        required: ["summary", "text_feedback", "regelbreuk"]
      }
    }
  ],
  tool_choice: { type: "tool", name: "submit_review" }
}) }}"""
    .replace("__REGELBREUK_DEFINITIE__", hba_regelbreuk_definitie_js_literal())
    .replace("__REGEL_LETTERLIJK__", hba_regel_letterlijk_eis_js_literal())
)

BUILD_FEEDBACK_ROWS_JS = r"""const article = $('Validate Article').first().json;
// Input is Clear Old Feedback (MySQL { success: true }); Anthropic response lives on Claude Review.
const claude = $('Claude Review').first().json;

function extractSubmitReviewInput(response) {
  const content = response?.content;
  if (Array.isArray(content)) {
    for (const block of content) {
      if (
        block
        && block.type === 'tool_use'
        && block.name === 'submit_review'
        && block.input
        && typeof block.input === 'object'
      ) {
        return block.input;
      }
    }
    const legacy = content[0]?.input;
    if (legacy && typeof legacy === 'object') {
      return legacy;
    }
  }
  return {};
}

const input = extractSubmitReviewInput(claude);
const toolOutputMissing = !input || Object.keys(input).length === 0;
const stopReason = String(claude?.stop_reason || 'onbekend');
const rawPreview = JSON.stringify(claude ?? {}).slice(0, 300);

const textItems = Array.isArray(input.text_feedback) ? input.text_feedback : [];
const imageFb = input.image_feedback?.comment ? input.image_feedback : null;
const rawRegelbreuk = input.regelbreuk && typeof input.regelbreuk === 'object' ? input.regelbreuk : null;

const rows = [];

function normalizeRegelWs(text) {
  return String(text || '').replace(/\s+/g, ' ').trim();
}

function jullieRegelsLines(articleRow) {
  const raw = String(articleRow.hba_geloofwaardigheidstest || '').trim();
  if (!raw) return [];
  return raw
    .split(/\r\n|\r|\n/)
    .map((line) => normalizeRegelWs(line))
    .filter(Boolean);
}

function brandRegelPool(articleRow) {
  return jullieRegelsLines(articleRow);
}

function resolveLiteralBrandRegel(candidate, pool) {
  const c = normalizeRegelWs(candidate);
  if (!pool.length) return c;
  for (const line of pool) {
    if (c && (line === c || line.includes(c) || c.includes(line))) return line;
  }
  if (c) {
    const lower = c.toLowerCase();
    for (const line of pool) {
      const l = line.toLowerCase();
      if (l.includes(lower) || lower.includes(l)) return line;
    }
  }
  return pool[0];
}

function buildRegelbreukComment(literalRegel, citaat) {
  const regel = normalizeRegelWs(literalRegel).replace(/\.+$/, '');
  let comment = `Dit stuk gaat in tegen je regel: "${regel}."`;
  const excerpt = String(citaat || '').trim();
  if (excerpt) {
    comment += ` In je tekst staat: «${excerpt}».`;
  }
  return comment;
}

const regelCandidate = normalizeRegelWs(rawRegelbreuk?.regel).replace(/^dat\s+/i, '');
if (regelCandidate !== '') {
  const citaat = String(rawRegelbreuk?.citaat || '').trim();
  const pool = brandRegelPool(article);
  const literalRegel = resolveLiteralBrandRegel(regelCandidate, pool);
  rows.push({
    slot_id: article.slot_id,
    article_id: article.article_id,
    feedback_type: 'text',
    reviewer_name: 'Hoofdredacteur',
    comment: buildRegelbreukComment(literalRegel, citaat),
    action_label: 'Verwijderen',
    excerpt: citaat !== '' ? citaat : null,
  });
}

function normalizeActionLabel(raw, fallback) {
  const label = raw ? String(raw).trim() : '';
  if (label === '' || label === 'Remove') return fallback;
  if (label === 'New image') return 'Nieuwe afbeelding';
  return label;
}

for (const item of textItems) {
  if (!item?.comment) continue;
  rows.push({
    slot_id: article.slot_id,
    article_id: article.article_id,
    feedback_type: 'text',
    reviewer_name: 'Hoofdredacteur',
    comment: String(item.comment).trim(),
    action_label: normalizeActionLabel(item.action_label, 'Verwijderen'),
    excerpt: item.excerpt ? String(item.excerpt) : null,
  });
}

if (imageFb) {
  rows.push({
    slot_id: article.slot_id,
    article_id: article.article_id,
    feedback_type: 'image',
    reviewer_name: 'Hoofdredacteur',
    comment: String(imageFb.comment).trim(),
    action_label: normalizeActionLabel(imageFb.action_label, 'Nieuwe afbeelding'),
    excerpt: null,
  });
}

if (rows.length === 0) {
  const summary = String(input.summary || '').trim();
  let comment;
  if (toolOutputMissing) {
    comment = `[CE-04] Review mislukt: tool-output niet gelezen. stop_reason=${stopReason}. Raw (300 tekens): ${rawPreview}`;
  } else if (summary !== '') {
    comment = summary;
  } else {
    comment = 'Geen specifieke feedback.';
  }
  rows.push({
    slot_id: article.slot_id,
    article_id: article.article_id,
    feedback_type: 'text',
    reviewer_name: 'Hoofdredacteur',
    comment,
    action_label: null,
    excerpt: null,
    ce04_parse_error: toolOutputMissing,
  });
}

return rows.map((row) => ({ json: row }));"""


def resolve_ce04_source() -> Path:
    for path in SOURCE_CANDIDATES:
        if path.is_file():
            return path
    listed = ", ".join(p.name for p in SOURCE_CANDIDATES)
    raise SystemExit(f"Missing CE-04 source JSON in {JSON_DIR} (tried: {listed})")


def require_node(workflow: dict, name: str) -> dict:
    for node in workflow.get("nodes", []):
        if node.get("name") == name:
            return node
    raise SystemExit(f"CE-04: node '{name}' ontbreekt")


def patch_ce04_regelbreuk(workflow: dict) -> None:
    require_node(workflow, "Get Article")["parameters"]["query"] = GET_ARTICLE_SQL
    require_node(workflow, "Validate Article")["parameters"]["jsCode"] = VALIDATE_ARTICLE_JS
    require_node(workflow, "Claude Review")["parameters"]["jsonBody"] = CLAUDE_REVIEW_JSON_BODY
    require_node(workflow, "Build Feedback Rows")["parameters"]["jsCode"] = BUILD_FEEDBACK_ROWS_JS


source = resolve_ce04_source()
workflow = json.loads(source.read_text(encoding="utf-8"))
patch_ce04_regelbreuk(workflow)
write_workflow(workflow, OUT)
print(f"Patched {source.name}")
print("Wrote", OUT)
