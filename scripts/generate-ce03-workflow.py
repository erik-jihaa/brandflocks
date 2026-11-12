#!/usr/bin/env python3
"""Patch CE-03 workflow — MySQL $N placeholders + Stap D zelfcontrole (briefing v11 §4.3.4bis)."""
import copy
import json
import os
import sys
import uuid
from pathlib import Path

ROOT = Path(__file__).resolve().parent
sys.path.insert(0, str(ROOT))
from n8n_env import ce_api_base, load_env, target_env  # noqa: E402
from n8n_workflow_utils import patch_workflow_file  # noqa: E402
from prompt_fragments import (  # noqa: E402
    hba_merkbewering_definitie_js_literal,
    hba_regelbreuk_definitie_js_literal,
    nl_avoid_calques_block,
    patch_json_body_system_prompt,
)


def js_string(value: str) -> str:
    return json.dumps(value)


_env = target_env()
load_env(_env if _env != "production" else None)
CE_PUBLIC_BASE_URL = ce_api_base()
CE_RESET_TOKEN = os.environ.get("CE_RESET_TOKEN", "")

JSON = ROOT.parent / "json" / "n8n" / "CE-03 (Rationale & Article Writer).json"
OUT = ROOT.parent / "json" / "n8n" / "CE-03 - Rationale & Article Writer (FIXED v2 - MySQL params).json"

PREPARE_SELF_REVIEW_JS = r"""
const research = $('Get Research').all().map((item) => item.json);
const content = $('Claude Step B').first()?.json?.content?.[0]?.input?.content || '';

const lines = research.map((row) => {
  const reliability = String(row.reliability || '').trim();
  const stance = String(row.stance || '').trim();
  const title = String(row.title || '').trim();
  const rationale = String(row.item_rationale || row.snippet || '').trim();
  if (!title || title.toLowerCase() === 'untitled') {
    return null;
  }
  const meta = [
    reliability !== '' ? `reliability=${reliability}` : null,
    stance !== '' ? `stance=${stance}` : null,
  ].filter(Boolean).join(', ');
  const prefix = meta !== '' ? `[${meta}]` : '[geen metadata]';
  return `${prefix} ${title}: ${rationale}`;
}).filter(Boolean);

const fmtSlot = $('Format Data').first()?.json?.slot ?? {};
const slotRow = { ...($('Get Slot').first()?.json ?? {}), ...fmtSlot };
const regelTest = String(slotRow.hba_geloofwaardigheidstest || '').trim();
let verboden = [];
try {
  const raw = slotRow.hba_verboden_logica;
  const parsed = typeof raw === 'string' && raw.trim() !== '' ? JSON.parse(raw) : raw;
  verboden = Array.isArray(parsed) ? parsed.map((v) => String(v).trim()).filter(Boolean) : [];
} catch (e) {
  verboden = [];
}
let bewijsvelden = [];
try {
  const raw = slotRow.hba_bewijsvelden;
  const parsed = typeof raw === 'string' && raw.trim() !== '' ? JSON.parse(raw) : raw;
  bewijsvelden = Array.isArray(parsed) ? parsed.map((v) => String(v).trim()).filter(Boolean) : [];
} catch (e) {
  bewijsvelden = [];
}
const regelLines = [];
if (regelTest !== '') regelLines.push(`Geloofwaardigheidstest: ${regelTest}`);
if (verboden.length) regelLines.push(`Verboden logica:\n- ${verboden.join('\n- ')}`);
const bewijsText = bewijsvelden.length
  ? bewijsvelden.map((v) => `- ${v}`).join('\n')
  : 'Geen bewijsvelden vastgelegd.';
const vorm = String(slotRow.aanspreekvorm || slotRow.hba_aanspreekvorm || '').trim().toLowerCase();
const aanspreekvormMerk = vorm === 'u' || vorm === 'je' ? vorm : 'niet vastgelegd';

return {
  json: {
    content_ai: String(content),
    research_summary: lines.length ? lines.join('\n') : 'Geen research items gekoppeld.',
    regels_merk: regelLines.length ? regelLines.join('\n') : 'Geen regels vastgelegd.',
    bewijsvelden_merk: bewijsText,
    aanspreekvorm_merk: aanspreekvormMerk,
  },
};
"""

STEP_D_JSON_BODY = r"""={{ JSON.stringify({
  model: "claude-sonnet-4-6",
  max_tokens: 1400,
  system: "Je bent een kritische eindredacteur die het zojuist geschreven artikel controleert tegen de onderliggende research, de bewijsvelden en de merkarchitectuur. Wees specifiek, geen algemene opmerkingen zoals 'het artikel is goed geschreven'.",
  messages: [{
    role: "user",
    content: "ARTIKEL:\n" + $json.content_ai + "\n\nONDERLIGGENDE RESEARCH (de items waarop dit artikel gebaseerd zou moeten zijn):\n" + $json.research_summary + "\n\nREGELS VAN HET MERK:\n" + $json.regels_merk + "\n\nBEWIJSVELDEN VAN HET MERK:\n" + $json.bewijsvelden_merk + "\n\nAANSPREEKVORM VAN HET MERK:\n" + $json.aanspreekvorm_merk + "\n\n__REGELBREUK_DEFINITIE__\n\n__MERKBEWERING_DEFINITIE__\n\nOPDRACHT: Beantwoord kort en concreet:\n1. Welke claim of bewering in het artikel is het minst goed onderbouwd door de research hierboven (geen onderbouwing, of gebaseerd op een item met reliability = low)? Citeer de zin uit het artikel.\n2. Is er een aanname in het artikel die niet expliciet uit de research volgt (het model 'vult zelf in')? Zo ja, welke?\n3. Mist het artikel een tegengeluid dat wel in de research aanwezig was (een item met stance = spreekt_tegen dat niet verwerkt is)?\n4. Breekt het artikel een regel van het merk, volgens de definitie van REGELBREUK hierboven? Zo ja: vul regelbreuk. regel = de geschonden regel als bijzin die grammaticaal past na 'Dit stuk gaat in tegen je regel dat' (bijvoorbeeld: 'je nooit een prijs noemt voordat je het werk ter plekke hebt gezien'), in gewone taal, zonder woorden als geloofwaardigheidstest of verboden logica. citaat = de letterlijke zin uit het artikel die ertegenin gaat. Zo nee, of als er geen regels zijn vastgelegd: regelbreuk = null.\n5. MERKBEWERINGEN: volg de definitie MERKBEWERING hierboven. Beoordeel het artikel als gehele passage in context — geen losse zinnen zonder voorgaande context. Per merkbewering: citaat, bron (merkarchitectuur | bewijsveld | research | geen_bron), en optioneel steunt_op = citaat van een eerdere bewering in dezelfde passage waar deze op voortbouwt (zelfde bron als die eerdere bewering). Laat retoriek, standpunten en lezer-aansprekingen weg.\nAls vraag 1 tot en met 3 niets concreets opleveren, antwoord dan kort dat het artikel goed aansluit bij de research — forceer geen kritiekpunt. Ook bij vraag 4: forceer geen regelbreuk.\nOutput als JSON via de tool."
  }],
  tools: [{
    name: "self_review",
    description: "Zelfcontrole van artikel tegen research, bewijsvelden en merkregels",
    input_schema: {
      type: "object",
      properties: {
        weakest_claim: { type: ["string", "null"] },
        unsupported_assumption: { type: ["string", "null"] },
        missed_counterpoint: { type: ["string", "null"] },
        regelbreuk: {
          type: ["object", "null"],
          properties: {
            regel: { type: "string" },
            citaat: { type: "string" }
          },
          required: ["regel", "citaat"]
        },
        merk_beweringen: {
          type: "array",
          items: {
            type: "object",
            properties: {
              citaat: { type: "string" },
              bron: { type: "string", enum: ["merkarchitectuur", "bewijsveld", "research", "geen_bron"] },
              steunt_op: { type: ["string", "null"] }
            },
            required: ["citaat", "bron"]
          }
        }
      },
      required: ["weakest_claim", "unsupported_assumption", "missed_counterpoint", "regelbreuk", "merk_beweringen"]
    }
  }],
  tool_choice: { type: "tool", name: "self_review" }
}) }}"""

STEP_D_JSON_BODY = patch_json_body_system_prompt(
    STEP_D_JSON_BODY.replace("__REGELBREUK_DEFINITIE__", hba_regelbreuk_definitie_js_literal()).replace(
        "__MERKBEWERING_DEFINITIE__", hba_merkbewering_definitie_js_literal()
    )
)

VALIDATE_DATA_JS = r"""
const slot = $('Format Data').first().json.slot;
const stepA = $('Claude Step A').first().json;
const stepB = $('Claude Step B').first().json;
const stepC = $('Claude Step C').first().json;
const stepD = $('Claude Step D').first().json;

function extractSelfReviewInput(response) {
  const content = response?.content;
  if (Array.isArray(content)) {
    for (const block of content) {
      if (
        block
        && block.type === 'tool_use'
        && block.name === 'self_review'
        && block.input
        && typeof block.input === 'object'
      ) {
        return { input: block.input, error: null };
      }
    }
    const legacy = content[0]?.input;
    if (legacy && typeof legacy === 'object') {
      return { input: legacy, error: null };
    }
  }
  const stop = String(response?.stop_reason || 'onbekend');
  const preview = JSON.stringify(response ?? {}).slice(0, 300);
  return {
    input: {},
    error: `CE-03 self_review: tool-output niet gelezen; stop_reason=${stop}; preview=${preview}`,
  };
}

function stripMarkdownHeadings(text, allowPlainSubheads) {
  const lines = String(text || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n');
  const out = [];
  for (const line of lines) {
    const m = line.match(/^\s*#{1,6}\s+(.+?)\s*$/);
    if (m) {
      if (allowPlainSubheads) out.push(String(m[1]).trim());
      continue;
    }
    out.push(line);
  }
  return out.join('\n').replace(/\n{3,}/g, '\n\n').trim();
}

const rationale = stepA?.content?.[0]?.input?.rationale || '';
const headline = stepB?.content?.[0]?.input?.headline || '';
const platformKey = String(
  slot.platform_intended || slot.platform_final || slot.platform_suggested || 'linkedin'
).toLowerCase().trim();
const allowPlainSubheads = platformKey === 'blog';
let content = stepB?.content?.[0]?.input?.content || '';
content = stripMarkdownHeadings(content, allowPlainSubheads);
const suggestions = stepC?.content?.[0]?.input || {};
const selfReviewExtract = extractSelfReviewInput(stepD);
const review = selfReviewExtract.input || {};
const selfReviewParseError = selfReviewExtract.error;

if (!rationale || !headline || !content) {
  throw new Error('Claude Step A of B miste input data.');
}

const rawRegelbreuk = review.regelbreuk;
const regelbreuk = rawRegelbreuk && typeof rawRegelbreuk === 'object'
  && String(rawRegelbreuk.regel || '').trim() !== ''
  ? { regel: String(rawRegelbreuk.regel).trim(), citaat: String(rawRegelbreuk.citaat || '').trim() }
  : null;

const themeMeta = $('Merge Theme Conflict').first()?.json?.theme_conflict_meta || {};
const merkBeweringen = Array.isArray(review.merk_beweringen)
  ? review.merk_beweringen
      .filter((row) => row && typeof row === 'object' && String(row.citaat || '').trim() !== '')
      .map((row) => {
        const out = {
          citaat: String(row.citaat).trim(),
          bron: ['merkarchitectuur', 'bewijsveld', 'research', 'geen_bron'].includes(String(row.bron || '').trim())
            ? String(row.bron).trim()
            : 'geen_bron',
        };
        const steunt = String(row.steunt_op || '').trim();
        if (steunt !== '') out.steunt_op = steunt;
        return out;
      })
  : [];
const selfReviewObj = {
  weakest_claim: review.weakest_claim ?? null,
  unsupported_assumption: review.unsupported_assumption ?? null,
  missed_counterpoint: review.missed_counterpoint ?? null,
  regelbreuk,
  theme_conflict: themeMeta.had_conflict === true,
  theme_conflict_resolution: themeMeta.resolution ?? null,
  theme_conflict_why: themeMeta.why ?? null,
  theme_premisse: themeMeta.theme_premisse === true,
  theme_premisse_wat: themeMeta.theme_premisse_wat ?? null,
  theme_premisse_actie: themeMeta.theme_premisse_actie ?? null,
  merk_beweringen: merkBeweringen,
};

const hasSelfReview = regelbreuk !== null
  || themeMeta.had_conflict === true
  || themeMeta.theme_premisse === true
  || merkBeweringen.length > 0
  || [
  selfReviewObj.weakest_claim,
  selfReviewObj.unsupported_assumption,
  selfReviewObj.missed_counterpoint,
  selfReviewObj.theme_conflict_why,
  selfReviewObj.theme_premisse_wat,
  selfReviewObj.theme_premisse_actie,
].some((value) => value !== null && String(value).trim() !== '');

let selfReviewJson = null;
if (selfReviewParseError) {
  selfReviewJson = JSON.stringify({
    weakest_claim: null,
    unsupported_assumption: null,
    missed_counterpoint: null,
    regelbreuk: null,
    _ce03_self_review_error: selfReviewParseError,
  });
} else if (hasSelfReview) {
  selfReviewJson = JSON.stringify(selfReviewObj);
}

function parseToneOptions(slot) {
  try {
    const raw = slot.tone_options;
    const list = typeof raw === 'string' && raw ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []);
    return list.map((t) => String(t).toLowerCase().trim()).filter(Boolean);
  } catch (e) {
    return [];
  }
}

function resolveToneKey(slot, suggestions) {
  const opts = parseToneOptions(slot);
  const pick = (value) => {
    const key = String(value || '').toLowerCase().trim();
    if (!key) return '';
    if (!opts.length || opts.includes(key)) return key;
    return '';
  };
  return pick(slot.tone_final) || pick(suggestions.tone) || pick(slot.default_tone) || (opts[0] || '');
}

const resolvedTone = resolveToneKey(slot, suggestions);

return {
  json: {
    slot_id: String(slot.id),
    rationale: String(rationale),
    headline: String(headline),
    content_ai: String(content),
    tone_suggested: String(resolvedTone),
    angle_suggested: String($('Format Data').item.json.slot.angle_final || suggestions.angle || 'general'),
    image_ratio_suggested: String($('Format Data').item.json.slot.image_ratio_final || suggestions.image_ratio || 'landscape'),
    platform_suggested: String($('Format Data').item.json.slot.platform_intended || $('Format Data').item.json.slot.platform_final || suggestions.platform || 'linkedin'),
    hashtags_suggested: Array.isArray(suggestions.hashtags) ? suggestions.hashtags.join(', ') : '',
    tone_final: String(resolvedTone),
    angle_final: String($('Format Data').item.json.slot.angle_final || suggestions.angle || 'general'),
    platform_final: String($('Format Data').item.json.slot.platform_intended || $('Format Data').item.json.slot.platform_final || suggestions.platform || 'linkedin'),
    image_ratio_final: String($('Format Data').item.json.slot.image_ratio_final || suggestions.image_ratio || 'landscape'),
    write_directive: String($('Format Data').item.json.slot.write_directive || ''),
    objective_suggested: String(
      suggestions.objective_id
      || $('Format Data').item.json.slot.objective_suggested
      || $('Format Data').item.json.slot.objective_id
      || ''
    ) || null,
    objective_final: String(
      $('Format Data').item.json.slot.objective_final
      || $('Format Data').item.json.slot.objective_id
      || suggestions.objective_id
      || ''
    ) || null,
    self_review: selfReviewJson,
    self_review_parse_error: selfReviewParseError,
    status: 'draft',
  },
};
"""

STEP_B_HASHTAGS = (
    "\\n\\nHASHTAGS (verplicht):\\n"
    "Geen hashtag die woorden_die_niet_passen of verboden_logica uit de brand narrative schendt "
    "(bijv. geen #versgebrand als hoofdclaim zonder de zes-wekenregel). "
    "Hashtags tellen mee als onderdeel van de post."
)

STEP_B_NO_INVENTED_FACTS = (
    "\\n\\nFEITEN OVER HET MERK (verplicht):\\n"
    "Verzin geen feiten over het merk: geen ervaring, samenwerkingen, aantallen, "
    "klanten, resultaten, werkwijze, voorraad of levering die niet in de brand narrative, de bewijsvelden of de research staan. "
    "Ontbreekt een concreet feit, schrijf dan over de werkwijze of de regel zoals die in de brand narrative staat — of laat de zin weg. "
    "Verzin geen proces ('op bestelling', 'kleine batches', 'uitverkocht', aanmeldlijst) als dat geen bewijsveld of research is. "
    "Neem geen resultaat uit het thema over als het merk dat resultaat niet heeft of alleen kan halen met een verboden middel."
)

STEP_B_ANTI_AI_STYLE = (
    "\\n\\nSCHRIJFSTIJL (verplicht — geen AI-generic voice):\\n"
    "- Geen buzzwords: leverage, delve, seamless, holistic, testament, foster, pivot, explore, ensure, game-changer.\\n"
    "- Geen lege openingen zoals 'In today\\'s fast-paced corporate landscape'.\\n"
    "- Wissel zinlengte: lange zinnen afwisselen met korte fragmenten.\\n"
    "- Neem een standpunt in; geen neutrale consensus-taal.\\n"
    "- Geen bullet-lists; schrijf lopende alinea\\'s.\\n"
    "- Geen markdown-koppen: geen regels die beginnen met # of ## (dus ook geen ## Waarom… / ## Wat…).\\n"
    "- Geen mechanische slotvraag of emoji-bait.\\n"
    "- Schrijf alsof een mens met eigen ervaring deelt wat hij geleerd heeft — niet gepolijste AI-copy."
)

STEP_B_USER_SUFFIX = (
    "\\n\\nDe post is een eerste draft voor de redacteur. "
    "Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen."
)

INSERT_SQL = (
    "INSERT INTO articles (id, slot_id, rationale, headline, content_ai, "
    "tone_suggested, angle_suggested, image_ratio_suggested, platform_suggested, "
    "hashtags_suggested, self_review, status, tone_final, angle_final, platform_final, "
    "image_ratio_final, write_directive, objective_suggested, objective_final) "
    "VALUES (UUID(), $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18)"
)

INSERT_REPLACEMENT = (
    "={{ [ $('Validate Data').item.json.slot_id, "
    "$('Validate Data').item.json.rationale, "
    "$('Validate Data').item.json.headline, "
    "$('Validate Data').item.json.content_ai, "
    "$('Validate Data').item.json.tone_suggested, "
    "$('Validate Data').item.json.angle_suggested, "
    "$('Validate Data').item.json.image_ratio_suggested, "
    "$('Validate Data').item.json.platform_suggested, "
    "$('Validate Data').item.json.hashtags_suggested, "
    "$('Validate Data').item.json.self_review, "
    "$('Validate Data').item.json.status, "
    "$('Validate Data').item.json.tone_final, "
    "$('Validate Data').item.json.angle_final, "
    "$('Validate Data').item.json.platform_final, "
    "$('Validate Data').item.json.image_ratio_final, "
    "$('Validate Data').item.json.write_directive, "
    "$('Validate Data').item.json.objective_suggested, "
    "$('Validate Data').item.json.objective_final ] }}"
)

PARSE_INPUT_JS = r"""
const raw = $json.body ?? $json;
const body = raw && typeof raw === 'object' && raw.body && typeof raw.body === 'object' ? raw.body : raw;
const slotId = body?.slot_id ?? raw?.slot_id ?? null;

if (!slotId || String(slotId).trim() === '') {
  throw new Error('Missing slot_id. Stuur { "slot_id": "<uuid>" }.');
}

const out = { slot_id: String(slotId).trim() };
for (const key of [
  'write_directive',
  'tone_final',
  'angle_final',
  'platform_final',
  'platform_intended',
  'platform_label',
  'platform_form_guidelines',
  'grenzen_block',
  'hba_grenzen_block',
  'nl_writing_style_block',
  'image_ratio_final',
  'objective_id',
  'objective_label',
  'objective_description',
  'objective_writing_style',
  'objectives_catalog',
  'default_tone',
]) {
  const v = body?.[key];
  if (v !== undefined && v !== null && String(v).trim() !== '') {
    out[key] = String(v).trim();
  }
}
if (body?.tone_options !== undefined && body?.tone_options !== null) {
  out.tone_options = typeof body.tone_options === 'string'
    ? body.tone_options
    : JSON.stringify(body.tone_options);
}
return { json: out };
"""

VALIDATE_SLOT_JS = r"""
const slot = $input.first()?.json ?? {};
const req = $('Merge CE03 Slot Meta').first()?.json ?? $('Parse Input').first().json;

if (!slot.id) {
  const requested = req.slot_id;
  throw new Error(`Geen content_slot gevonden voor slot_id: ${requested}`);
}

for (const key of [
  'write_directive',
  'tone_final',
  'angle_final',
  'platform_final',
  'platform_intended',
  'platform_label',
  'platform_form_guidelines',
  'grenzen_block',
  'hba_grenzen_block',
  'nl_writing_style_block',
  'image_ratio_final',
  'objective_id',
  'objective_label',
  'objective_description',
  'objective_writing_style',
  'objectives_catalog',
  'tone_options',
  'default_tone',
]) {
  const v = req[key];
  if (v !== undefined && v !== null && String(v).trim() !== '') {
    slot[key] = String(v).trim();
  }
}

return { json: slot };
"""

FORMAT_DATA_JS = r"""
function parseToneOptions(slot) {
  try {
    const raw = slot.tone_options;
    const list = typeof raw === 'string' && raw ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []);
    return list.map((t) => String(t).toLowerCase().trim()).filter(Boolean);
  } catch (e) {
    return [];
  }
}

const slot = { ...($('Validate Slot').first().json) };
const research = $input.all().map(item => item.json);

let research_summary = research.map(r => `[${r.id}] ${r.title}: ${r.snippet} (Rationale: ${r.item_rationale || 'N/A'})`).join('\n\n');
if (!research_summary) research_summary = "Geen specifieke research items gekoppeld.";

const toneOpts = parseToneOptions(slot);
const fallbackTone = String(slot.default_tone || toneOpts[0] || slot.tone_final || '').toLowerCase().trim();
let toneKey = String(slot.tone_final || fallbackTone).toLowerCase().trim();
if (toneOpts.length && toneKey && !toneOpts.includes(toneKey)) {
  toneKey = fallbackTone;
}
if (toneKey) {
  slot.tone_final = toneKey;
}

const mandatoryRules = {
  b2b: 'VERPLICHT (b to b): Schrijf vanuit het bedrijf in wij-vorm ("wij", "ons", "onze klanten") — GEEN ik-vorm en geen persoonlijke anekdotes in de eerste persoon. Zakelijk register voor beslissers/vakgenoten: concreet, benoem impact of resultaat voor hun bedrijf; geen hype.',
  b2c: 'VERPLICHT (b to c): Spreek de lezer rechtstreeks aan met "je"/"jij" — GEEN ik-vorm vanuit het bedrijf en geen formeel "wij benaderen u". Warm en toegankelijk, geen vakjargon.',
  internal: "VERPLICHT (internal): Spreek collega's aan met \"wij\"/\"jullie\" — GEEN ik-vorm vanuit één persoon. Informeel-professioneel als een teamupdate; geen externe marketing- of salestaal.",
  partners: 'VERPLICHT (partners): Schrijf vanuit het bedrijf in wij-vorm, gericht aan partners/allianties ("samen", "gezamenlijk") — GEEN ik-vorm en geen consumenten-toon.',
  personal: 'VERPLICHT (personal): Schrijf de hele post in ik-vorm — gebruik "ik", "mijn", "mij" in opening, midden en slot — GEEN "wij"/"ons" namens het bedrijf. Minimaal drie zinnen moeten expliciet met "ik" of "mijn" beginnen of die woorden bevatten.',
};
const rule = mandatoryRules[toneKey];
if (rule) {
  const dir = String(slot.write_directive || '').trim();
  slot.write_directive = dir && dir.includes('VERPLICHT (' + (toneKey === 'b2b' ? 'b to b' : toneKey === 'b2c' ? 'b to c' : toneKey) + ')')
    ? dir
    : (dir ? dir + '\n' + rule : rule);
}

// Objective uit JOIN of webhook — aanvullen in write_directive als die nog ontbreekt.
const objectiveLabel = String(slot.objective_label || '').trim();
const objectiveDescription = String(slot.objective_description || '').trim();
const objectiveWritingStyle = String(slot.objective_writing_style || '').trim();
if (objectiveLabel || objectiveDescription) {
  let dir = String(slot.write_directive || '').trim();
  if (!dir.includes('DOELSTELLING VAN DEZE POST')) {
    const objLines = [];
    if (objectiveLabel) objLines.push('DOELSTELLING VAN DEZE POST: ' + objectiveLabel + '.');
    if (objectiveDescription) objLines.push(objectiveDescription);
    objLines.push('Richt rationale, boodschap en CTA expliciet op deze doelstelling.');
    if (objectiveWritingStyle) {
      objLines.push('Schrijfstijl bij deze doelstelling: ' + objectiveWritingStyle + '.');
    }
    dir = dir ? dir + '\n' + objLines.join('\n') : objLines.join('\n');
    slot.write_directive = dir;
  }
}

if (!slot.objective_id && slot.objective_final) {
  slot.objective_id = slot.objective_final;
}

const PLATFORM_LABELS = {
  linkedin: 'LinkedIn',
  blog: 'blog',
  instagram: 'Instagram',
  x: 'X',
  facebook: 'Facebook',
};
const PLATFORM_GUIDELINES = {
  linkedin: "Vorm LinkedIn:\n- Circa 120–220 woorden; korte alinea's (1–3 zinnen).\n- Eerste regel is de hook; optioneel één vraag onderaan — geen clickbait.\n- Geen tussenkoppen en geen markdown-koppen (geen ##).\n- Wel witregels tussen alinea's.",
  blog: "Vorm blog:\n- Circa 800–1200 woorden; meerdere alinea's met tussenkoppen.\n- Tussenkoppen als platte regel — geen markdown (# of ##).\n- Opbouw: hook, context, argument, concreet voorbeeld, afsluiting.\n- Geen LinkedIn-fragmenten of emoji-bait.",
  instagram: "Vorm Instagram:\n- Circa 80–150 woorden; scanbaar in korte alinea's.\n- Eerste zin pakt aandacht; maximaal één duidelijke CTA.\n- Geen lange uitlegblokken of blog-tussenkoppen.",
  x: "Vorm X:\n- Circa 40–120 woorden; één kernpunt per post.\n- Scherp en direct; geen blog-opbouw of tussenkoppen.\n- Geen hashtag-spam.",
  facebook: "Vorm Facebook:\n- Circa 150–300 woorden; informele alinea's.\n- Persoonlijk of community-gericht; één duidelijke boodschap.\n- Geen corporate whitepaper-toon.",
};

function resolvePlatformIntended(s) {
  const raw = String(
    s.platform_intended || s.platform_final || s.platform_suggested || s.default_platform || 'linkedin'
  ).toLowerCase().trim();
  return raw || 'linkedin';
}

slot.platform_intended = resolvePlatformIntended(slot);
slot.platform_label = PLATFORM_LABELS[slot.platform_intended] || slot.platform_label || slot.platform_intended;
slot.platform_form_guidelines = PLATFORM_GUIDELINES[slot.platform_intended]
  || slot.platform_form_guidelines
  || ('Vorm ' + slot.platform_label + ':\\n- Lengte en opbouw passend bij het kanaal.\\n- Menselijk en concreet.\\n- Geen copy-paste van andere kanalen.');
slot.grenzen_block = String(slot.grenzen_block || slot.hba_grenzen_block || '').trim();
const NL_WRITING_STYLE_BLOCK = __NL_WRITING_STYLE_BLOCK__;
slot.nl_writing_style_block = String(slot.nl_writing_style_block || NL_WRITING_STYLE_BLOCK || '').trim();
const vormRaw = String(slot.aanspreekvorm || slot.hba_aanspreekvorm || '').toLowerCase().trim();
if (!String(slot.aanspreekvorm_block || '').trim()) {
  if (vormRaw === 'u') {
    slot.aanspreekvorm_block = 'AANSPREEKVORM (verplicht): spreek de lezer aan met u/uw. Gebruik geen je, jij, jou of jouw.';
  } else if (vormRaw === 'je' || vormRaw === 'jij') {
    slot.aanspreekvorm_block = 'AANSPREEKVORM (verplicht): spreek de lezer aan met je/jij/jou/jouw. Gebruik geen u, uw of uwe.';
  }
}
slot.aanspreekvorm_block = String(slot.aanspreekvorm_block || '').trim();
if (vormRaw === 'je' || vormRaw === 'u') {
  slot.aanspreekvorm = vormRaw;
}
if (!slot.platform_final) {
  slot.platform_final = slot.platform_intended;
}

return {
  json: {
    slot: slot,
    research_summary: research_summary
  }
};
"""


def node_id() -> str:
    return str(uuid.uuid4())


def find_node(workflow: dict, name: str) -> dict | None:
    for node in workflow.get("nodes", []):
        if node.get("name") == name:
            return node
    return None


def require_node(workflow: dict, name: str) -> dict:
    node = find_node(workflow, name)
    if node is None:
        raise KeyError(f"Node not found: {name}")
    return node


def remove_editor_questions_step(workflow: dict) -> None:
    """HBA-laag staat altijd aan: geen redacteur-vragen-stap meer. Stap B → Stap C direct."""
    require_node(workflow, "Claude Step B")
    require_node(workflow, "Claude Step C")

    removed = {"HBA Skip Editor Questions?", "Claude Editor Questions"}
    workflow["nodes"] = [n for n in workflow.get("nodes", []) if n.get("name") not in removed]

    connections = workflow.setdefault("connections", {})
    for name in removed:
        connections.pop(name, None)
    connections["Claude Step B"] = {
        "main": [[{"node": "Claude Step C", "type": "main", "index": 0}]]
    }


def patch_ce03_nl_writing_style(workflow: dict) -> None:
    """NL-calque-blok in system prompts (Stap A/B/C) + fallback in Format Data."""
    for name in ("Claude Step A", "Claude Step B", "Claude Step C"):
        node = find_node(workflow, name)
        if node is None:
            continue
        body = node["parameters"].get("jsonBody", "")
        patched = patch_json_body_system_prompt(body)
        if patched != body:
            node["parameters"]["jsonBody"] = patched


def patch_ce03_step_b_platform_grenzen(workflow: dict) -> None:
    """§1 platform vóór stap B + §2 GRENZEN-blok (HBA)."""
    import re

    step_b = find_node(workflow, "Claude Step B")
    if step_b is None:
        return

    body = step_b["parameters"].get("jsonBody", "")

    opdracht_expr = (
        '"\\n\\nOPDRACHT:\\n" + "Schrijf een " + $(\'Format Data\').item.json.slot.platform_label '
        '+ "-bijdrage die de rationale vertaalt naar een concreet verhaal voor het gekozen onderwerp.\\n'
        'Lengte, opbouw en vorm volgens de conventies van " + $(\'Format Data\').item.json.slot.platform_label + ".\\n\\n"'
        '+ $(\'Format Data\').item.json.slot.platform_form_guidelines '
        '+ (($(\'Format Data\').item.json.slot.grenzen_block || \'\').trim() '
        '? "\\n\\n" + $(\'Format Data\').item.json.slot.grenzen_block : "") '
        '+ (($(\'Format Data\').item.json.slot.nl_writing_style_block || \'\').trim() '
        '? "\\n\\n" + $(\'Format Data\').item.json.slot.nl_writing_style_block : "") '
        '+ (($(\'Format Data\').item.json.slot.aanspreekvorm_block || \'\').trim() '
        '? "\\n\\n" + $(\'Format Data\').item.json.slot.aanspreekvorm_block : "") '
        '+ "\\n\\nDe bijdrage is een eerste draft voor de redacteur. Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen.'
        ' Verzin geen feiten over het merk: geen ervaring, samenwerkingen, aantallen, klanten, resultaten, werkwijze, voorraad of levering die niet in de brand narrative, de bewijsvelden of de research staan. Ontbreekt een concreet feit, schrijf dan over de werkwijze of de regel zoals die in de brand narrative staat — of laat de zin weg. Verzin geen proces (op bestelling, kleine batches, uitverkocht, aanmeldlijst) als dat geen bewijsveld of research is."'
    )

    if (
        'platform_label + "-bijdrage' in body
        and 'platform_form_guidelines' in body
        and 'grenzen_block' in body
        and 'nl_writing_style_block' in body
        and 'aanspreekvorm_block' in body
        and 'Verzin geen feiten over het merk' in body
        and '+ Schrijf een " +' not in body
        and '+ Schrijf een "' not in body
    ):
        return

    replaced = False
    patterns = [
        r'"\n\\nOPDRACHT:\\n" \+ Schrijf een " \+.*?(De bijdrage is een eerste draft voor de redacteur\. Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen\.)"',
        r'"\n\\nOPDRACHT:\\nSchrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal voor het gekozen onderwerp\.\\n\\nDe (?:post|bijdrage) is een eerste draft voor de redacteur\. Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen\."',
        r'"\n\\nOPDRACHT:\\n" \+ "Schrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal voor het gekozen onderwerp\.\\n\\nDe (?:post|bijdrage) is een eerste draft voor de redacteur\. Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen\."',
    ]
    for pattern in patterns:
        new_body, count = re.subn(pattern, opdracht_expr, body, count=1)
        if count:
            body = new_body
            replaced = True
            break

    if not replaced:
        for legacy in (
            '"\\n\\nOPDRACHT:\\nSchrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal '
            'voor het gekozen onderwerp.\\n\\nDe post is een eerste draft voor de redacteur. '
            'Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen."',
            '"\\n\\nOPDRACHT:\\nSchrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal '
            'voor het gekozen onderwerp.\\n\\nDe bijdrage is een eerste draft voor de redacteur. '
            'Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen."',
            '"\\n\\nOPDRACHT:\\n" + "Schrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal '
            'voor het gekozen onderwerp.\\n\\nDe post is een eerste draft voor de redacteur. '
            'Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen."',
        ):
            if legacy in body:
                body = body.replace(legacy, opdracht_expr, 1)
                replaced = True
                break

    if 'aanspreekvorm_block' not in body and 'nl_writing_style_block' in body:
        needle = (
            "+ (($('Format Data').item.json.slot.nl_writing_style_block || '').trim() "
            '? "\\n\\n" + $(\'Format Data\').item.json.slot.nl_writing_style_block : "") '
        )
        inject = (
            needle
            + "+ (($('Format Data').item.json.slot.aanspreekvorm_block || '').trim() "
            '? "\\n\\n" + $(\'Format Data\').item.json.slot.aanspreekvorm_block : "") '
        )
        if needle in body:
            body = body.replace(needle, inject, 1)
            replaced = True

    if replaced:
        step_b["parameters"]["jsonBody"] = body


def apply_writing_patches(workflow: dict) -> None:
    """MessageFatigue-stijl + redacteur-vragen in eerste draft."""
    patch_ce03_webhook_nodes(workflow)
    patch_ce03_slot_query(workflow)
    patch_ce03_objective_prompts(workflow)
    strip_ce03_poster_fields(workflow)
    patch_ce03_tone_prompts(workflow)
    patch_ce03_nl_writing_style(workflow)
    step_b = require_node(workflow, "Claude Step B")
    validate = require_node(workflow, "Validate Data")

    body = step_b["parameters"].get("jsonBody", "")
    tone_replace_from = "$('Format Data').item.json.slot.tone_of_voice,"
    tone_facts = STEP_B_ANTI_AI_STYLE + STEP_B_NO_INVENTED_FACTS + STEP_B_HASHTAGS
    tone_replace_to = f"$('Format Data').item.json.slot.tone_of_voice + \"{tone_facts}\","
    if tone_replace_from in body and STEP_B_ANTI_AI_STYLE not in body:
        body = body.replace(tone_replace_from, tone_replace_to)
    elif STEP_B_ANTI_AI_STYLE in body and STEP_B_NO_INVENTED_FACTS not in body:
        body = body.replace(STEP_B_ANTI_AI_STYLE, tone_facts, 1)

    meta_anchor = (
        "Schrijf alsof een mens met eigen ervaring deelt wat hij geleerd heeft — niet gepolijste AI-copy.\""
    )
    meta_inject = (
        "Schrijf alsof een mens met eigen ervaring deelt wat hij geleerd heeft — niet gepolijste AI-copy.\""
        + ' + (($(\'Format Data\').item.json.slot.write_directive || \'\').trim() '
        + '? "\\n\\nMETA (redacteur — leidend, overschrijft Schrijfwijzer bij conflict):\\n" '
        + '+ $(\'Format Data\').item.json.slot.write_directive : "")'
    )
    if meta_anchor in body and "META (redacteur" not in body:
        body = body.replace(meta_anchor, meta_inject)

    opdracht_anchor = (
        "Schrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal voor het gekozen onderwerp."
    )
    draft_tail = (
        "voor het gekozen onderwerp.\\n\\nDe post is een eerste draft voor de redacteur. "
        "Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen. Output als JSON.\""
    )
    if opdracht_anchor in body and "eerste draft voor de redacteur" not in body:
        body = body.replace(
            "voor het gekozen onderwerp. Output als JSON.\"",
            draft_tail,
            1,
        )

    opdracht_user_old = (
        "OPDRACHT:\\nSchrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal "
        "voor het gekozen onderwerp.\\n\\nDe post is een eerste draft voor de redacteur. "
        "Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen. Output als JSON.\""
    )
    opdracht_user_new = (
        "OPDRACHT:\\nSchrijf een LinkedIn-post die de rationale vertaalt naar een concreet verhaal "
        "voor het gekozen onderwerp.\\n\\nDe post is een eerste draft voor de redacteur. "
        "Schrijf menselijk en concreet; vermijd frictieloze tussenwoorden en lege managementzinnen.\" "
        "+ (($('Format Data').item.json.slot.write_directive || '').trim() "
        "? \"\\n\\n\" + $('Format Data').item.json.slot.write_directive : \"\") + \" Output als JSON.\""
    )
    if opdracht_user_old in body and opdracht_user_new not in body:
        body = body.replace(opdracht_user_old, opdracht_user_new, 1)

    step_b["parameters"]["jsonBody"] = body
    validate["parameters"]["jsCode"] = VALIDATE_DATA_JS
    patch_ce03_step_b_platform_grenzen(workflow)
    patch_ce03_self_review_merk_beweringen(workflow)
    add_theme_conflict_check(workflow)


def patch_ce03_self_review_merk_beweringen(workflow: dict) -> None:
    """Stap 3: bron per merkbewering in Prepare Self Review + Stap D."""
    prepare = find_node(workflow, "Prepare Self Review")
    if prepare is not None:
        prepare["parameters"]["jsCode"] = PREPARE_SELF_REVIEW_JS
    step_d = find_node(workflow, "Claude Step D")
    if step_d is not None:
        step_d["parameters"]["jsonBody"] = STEP_D_JSON_BODY


CE03_META_WEBHOOK_KEYS = [
    "write_directive",
    "tone_final",
    "angle_final",
    "platform_final",
    "platform_intended",
    "platform_label",
    "platform_form_guidelines",
    "grenzen_block",
    "hba_grenzen_block",
    "nl_writing_style_block",
    "image_ratio_final",
    "objective_id",
    "objective_label",
    "objective_description",
    "objective_writing_style",
    "objectives_catalog",
    "tone_options",
    "default_tone",
    "aanspreekvorm",
    "aanspreekvorm_block",
]

PREPARE_CE03_SLOT_META_JS = """
const req = $('Parse Input').first().json;
const slotId = String(req.slot_id || '').trim();
const ceBase = __CE_PUBLIC_BASE_URL__;
const ceResetToken = __CE_RESET_TOKEN__;

function hasText(value) {
  return value !== undefined && value !== null && String(value).trim() !== '';
}

const missing = [];
if (!hasText(req.grenzen_block) && !hasText(req.hba_grenzen_block)) {
  missing.push('grenzen_block');
}
if (!hasText(req.write_directive)) {
  missing.push('write_directive');
}
if (!hasText(req.nl_writing_style_block)) {
  missing.push('nl_writing_style_block');
}
if (!hasText(req.aanspreekvorm_block)) {
  missing.push('aanspreekvorm_block');
}

const fetchMeta = missing.length > 0 && slotId !== '' && ceResetToken !== '';

return {
  json: {
    ...req,
    slot_id: slotId,
    _fetchMeta: fetchMeta,
    _missingMeta: missing,
    url: `${ceBase}/api/ce03/slot-meta`,
    token: ceResetToken,
  },
};
""".replace("__CE_PUBLIC_BASE_URL__", js_string(CE_PUBLIC_BASE_URL)).replace(
    "__CE_RESET_TOKEN__", js_string(CE_RESET_TOKEN)
)

MERGE_CE03_SLOT_META_JS = r"""
const base = $('Prepare CE03 Slot Meta').first().json;
const fetchedWrap = $input.first()?.json ?? {};
const meta = fetchedWrap.meta && typeof fetchedWrap.meta === 'object' ? fetchedWrap.meta : {};

const out = { ...base };
for (const key of ['_fetchMeta', '_missingMeta', 'url', 'token']) {
  delete out[key];
}

const mergeKeys = __CE03_META_KEYS__;
for (const key of mergeKeys) {
  if (!String(out[key] || '').trim() && meta[key] !== undefined && meta[key] !== null) {
    const value = typeof meta[key] === 'string' ? meta[key] : JSON.stringify(meta[key]);
    if (String(value).trim() !== '') {
      out[key] = String(value).trim();
    }
  }
}

out.meta_merge_source = fetchedWrap.ok === true ? 'api' : 'webhook_only';
return { json: out };
""".replace("__CE03_META_KEYS__", json.dumps(CE03_META_WEBHOOK_KEYS))


def add_ce03_slot_meta_fetch(workflow: dict) -> None:
    """CE-01 stuurt alleen slot_id — vul GRENZEN, write_directive en NL-blok via de app-API aan."""
    if find_node(workflow, "Merge CE03 Slot Meta") is not None:
        return

    parse = require_node(workflow, "Parse Input")
    get_slot = require_node(workflow, "Get Slot")
    px, py = parse["position"]

    prepare = {
        "parameters": {"jsCode": PREPARE_CE03_SLOT_META_JS},
        "id": node_id(),
        "name": "Prepare CE03 Slot Meta",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [px + 112, py],
    }
    if_gate = {
        "parameters": {
            "conditions": {
                "boolean": [
                    {
                        "value1": "={{$json._fetchMeta}}",
                        "value2": True,
                    }
                ]
            }
        },
        "id": node_id(),
        "name": "CE03 Meta Needed?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 1,
        "position": [px + 224, py],
    }
    fetch = {
        "parameters": {
            "method": "POST",
            "url": "={{$json.url}}",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "Content-Type", "value": "application/json"},
                    {"name": "X-CE-Reset-Token", "value": "={{$json.token}}"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": '={{ JSON.stringify({ slot_id: $json.slot_id }) }}',
            "options": {
                "timeout": 60000,
                "response": {"response": {"neverError": True}},
            },
        },
        "id": node_id(),
        "name": "Fetch CE03 Slot Meta",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [px + 336, py],
    }
    merge = {
        "parameters": {"jsCode": MERGE_CE03_SLOT_META_JS},
        "id": node_id(),
        "name": "Merge CE03 Slot Meta",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [px + 448, py],
    }

    workflow["nodes"].extend([prepare, if_gate, fetch, merge])

    connections = workflow.setdefault("connections", {})
    connections["Parse Input"] = {"main": [[{"node": "Prepare CE03 Slot Meta", "type": "main", "index": 0}]]}
    connections["Prepare CE03 Slot Meta"] = {
        "main": [[{"node": "CE03 Meta Needed?", "type": "main", "index": 0}]]
    }
    connections["CE03 Meta Needed?"] = {
        "main": [
            [{"node": "Fetch CE03 Slot Meta", "type": "main", "index": 0}],
            [{"node": "Merge CE03 Slot Meta", "type": "main", "index": 0}],
        ]
    }
    connections["Fetch CE03 Slot Meta"] = {
        "main": [[{"node": "Merge CE03 Slot Meta", "type": "main", "index": 0}]]
    }
    connections["Merge CE03 Slot Meta"] = {
        "main": [[{"node": get_slot["name"], "type": "main", "index": 0}]]
    }


def patch_ce03_webhook_nodes(workflow: dict) -> None:
    parse = find_node(workflow, "Parse Input")
    if parse is not None:
        parse["parameters"]["jsCode"] = PARSE_INPUT_JS

    validate = find_node(workflow, "Validate Slot")
    if validate is not None:
        validate["parameters"]["jsCode"] = VALIDATE_SLOT_JS

    fmt = find_node(workflow, "Format Data")
    if fmt is not None:
        fmt["parameters"]["jsCode"] = FORMAT_DATA_JS.replace(
            "__NL_WRITING_STYLE_BLOCK__",
            js_string(nl_avoid_calques_block()),
        )

    insert = find_node(workflow, "Insert Article")
    if insert is not None:
        insert["parameters"]["query"] = INSERT_SQL
        insert["parameters"].setdefault("options", {})["queryReplacement"] = INSERT_REPLACEMENT

    add_ce03_slot_meta_fetch(workflow)
    add_post_insert_hooks(workflow)


PREPARE_HBA_SCORE_JS = """
const slotId = String($('Validate Data').first()?.json?.slot_id || '').trim();
const ceBase = __CE_PUBLIC_BASE_URL__;
const ceResetToken = __CE_RESET_TOKEN__;

if (!slotId || !ceResetToken) {
  return { json: { skip: true, reason: 'missing_slot_or_token' } };
}

return {
  json: {
    skip: false,
    slot_id: slotId,
    url: `${ceBase}/api/hba/score`,
    token: ceResetToken,
  },
};
""".replace("__CE_PUBLIC_BASE_URL__", js_string(CE_PUBLIC_BASE_URL)).replace(
    "__CE_RESET_TOKEN__", js_string(CE_RESET_TOKEN)
)


def add_post_insert_hooks(workflow: dict) -> None:
    remove_auto_publish_after_insert(workflow)
    add_hba_score_after_insert(workflow)
    add_hba_article_hint_after_insert(workflow)


def add_hba_score_after_insert(workflow: dict) -> None:
    if find_node(workflow, "HBA Score Article") is not None:
        return

    insert = find_node(workflow, "Insert Article")
    if insert is None:
        return

    prepare = {
        "parameters": {"jsCode": PREPARE_HBA_SCORE_JS},
        "id": node_id(),
        "name": "Prepare HBA Score",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [insert["position"][0] + 220, insert["position"][1] + 120],
    }

    gate = {
        "parameters": {
            "conditions": {
                "boolean": [
                    {
                        "value1": "={{$json.skip}}",
                        "value2": False,
                    }
                ]
            }
        },
        "id": node_id(),
        "name": "HBA Score Enabled?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 1,
        "position": [insert["position"][0] + 400, insert["position"][1] + 120],
    }

    http = {
        "parameters": {
            "method": "POST",
            "url": "={{$json.url}}",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "Content-Type", "value": "application/json"},
                    {"name": "X-CE-Reset-Token", "value": "={{$json.token}}"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={{ JSON.stringify({ slot_id: $json.slot_id, source: 'ce03' }) }}",
            "options": {
                "timeout": 120000,
                "response": {"response": {"neverError": True}},
            },
        },
        "id": node_id(),
        "name": "HBA Score Article",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [insert["position"][0] + 620, insert["position"][1] + 120],
    }

    workflow["nodes"].extend([prepare, gate, http])
    connections = workflow.setdefault("connections", {})
    insert_targets = connections.get("Insert Article", {}).get("main", [[]])[0]
    if not any(node.get("node") == "Prepare HBA Score" for node in insert_targets):
        insert_targets.append({"node": "Prepare HBA Score", "type": "main", "index": 0})
        connections["Insert Article"] = {"main": [insert_targets]}
    connections["Prepare HBA Score"] = {
        "main": [[{"node": "HBA Score Enabled?", "type": "main", "index": 0}]]
    }
    connections["HBA Score Enabled?"] = {
        "main": [
            [{"node": "HBA Score Article", "type": "main", "index": 0}],
            [{"node": "Prepare HBA Article Hint", "type": "main", "index": 0}],
        ]
    }
    connections["HBA Score Article"] = {
        "main": [[{"node": "Prepare HBA Article Hint", "type": "main", "index": 0}]]
    }


PREPARE_HBA_ARTICLE_HINT_JS = """
const slotId = String($('Validate Data').first()?.json?.slot_id || '').trim();
const ceBase = __CE_PUBLIC_BASE_URL__;
const ceResetToken = __CE_RESET_TOKEN__;

if (!slotId || !ceResetToken) {
  return { json: { skip: true, reason: 'missing_slot_or_token' } };
}

return {
  json: {
    skip: false,
    slot_id: slotId,
    url: `${ceBase}/api/admin/hba-article-hint-slot`,
    token: ceResetToken,
  },
};
""".replace("__CE_PUBLIC_BASE_URL__", js_string(CE_PUBLIC_BASE_URL)).replace(
    "__CE_RESET_TOKEN__", js_string(CE_RESET_TOKEN)
)


def add_hba_article_hint_after_insert(workflow: dict) -> None:
    if find_node(workflow, "HBA Article Hint") is not None:
        return

    score_node = find_node(workflow, "HBA Score Article")
    insert = find_node(workflow, "Insert Article")
    anchor = score_node or insert
    if anchor is None:
        return

    prepare = {
        "parameters": {"jsCode": PREPARE_HBA_ARTICLE_HINT_JS},
        "id": node_id(),
        "name": "Prepare HBA Article Hint",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [anchor["position"][0] + 120, anchor["position"][1] + 220],
    }

    gate = {
        "parameters": {
            "conditions": {
                "boolean": [
                    {
                        "value1": "={{$json.skip}}",
                        "value2": False,
                    }
                ]
            }
        },
        "id": node_id(),
        "name": "HBA Article Hint Enabled?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 1,
        "position": [anchor["position"][0] + 300, anchor["position"][1] + 220],
    }

    http = {
        "parameters": {
            "method": "POST",
            "url": "={{$json.url}}",
            "sendHeaders": True,
            "headerParameters": {
                "parameters": [
                    {"name": "Content-Type", "value": "application/json"},
                    {"name": "X-CE-Reset-Token", "value": "={{$json.token}}"},
                ]
            },
            "sendBody": True,
            "specifyBody": "json",
            "jsonBody": "={{ JSON.stringify({ slot_id: $json.slot_id, source: 'ce03' }) }}",
            "options": {
                "timeout": 120000,
                "response": {"response": {"neverError": True}},
            },
        },
        "id": node_id(),
        "name": "HBA Article Hint",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [anchor["position"][0] + 520, anchor["position"][1] + 220],
    }

    workflow["nodes"].extend([prepare, gate, http])
    connections = workflow.setdefault("connections", {})

    if score_node is None and insert is not None:
        insert_targets = connections.get("Insert Article", {}).get("main", [[]])[0]
        if not any(node.get("node") == "Prepare HBA Article Hint" for node in insert_targets):
            insert_targets.append({"node": "Prepare HBA Article Hint", "type": "main", "index": 0})
            connections["Insert Article"] = {"main": [insert_targets]}

    connections["Prepare HBA Article Hint"] = {
        "main": [[{"node": "HBA Article Hint Enabled?", "type": "main", "index": 0}]]
    }
    connections["HBA Article Hint Enabled?"] = {
        "main": [
            [{"node": "HBA Article Hint", "type": "main", "index": 0}],
            [],
        ]
    }


def remove_auto_publish_after_insert(workflow: dict) -> None:
    """Automatisch publiceren bestaat niet meer: verwijder de auto-publish-keten na Insert Article."""
    removed = {"Prepare Auto Publish", "Auto Publish Enabled?", "Auto Publish Article"}
    workflow["nodes"] = [n for n in workflow.get("nodes", []) if n.get("name") not in removed]

    connections = workflow.setdefault("connections", {})
    for name in removed:
        connections.pop(name, None)
    insert_main = connections.get("Insert Article", {}).get("main")
    if insert_main:
        connections["Insert Article"]["main"] = [
            [target for target in branch if target.get("node") not in removed] for branch in insert_main
        ]


def patch_ce03_slot_query(workflow: dict) -> None:
    get_slot = find_node(workflow, "Get Slot")
    if get_slot is None:
        return
    get_slot["parameters"]["query"] = (
        "SELECT s.*, c.name as client_name, c.archetype, c.archetype_traits, c.adversary, c.brand_goal, "
        "c.tone_of_voice, c.writing_guide, c.default_tone, c.default_angle, c.default_image_ratio, "
        "c.default_platform, a.tone_final, a.angle_final, a.platform_final, a.platform_suggested, a.write_directive, "
        "a.objective_final, a.objective_suggested, "
        "COALESCE(a.objective_final, a.objective_suggested, s.objective_id, t.objective_id) AS objective_id, "
        "obj.label AS objective_label, obj.description AS objective_description, "
        "obj.writing_style AS objective_writing_style, "
        "(SELECT bn.geloofwaardigheidstest FROM brand_narratives bn "
        "WHERE bn.client_id = s.client_id AND bn.is_current = 1 ORDER BY bn.version DESC LIMIT 1) "
        "AS hba_geloofwaardigheidstest, "
        "(SELECT bn.verboden_logica FROM brand_narratives bn "
        "WHERE bn.client_id = s.client_id AND bn.is_current = 1 ORDER BY bn.version DESC LIMIT 1) "
        "AS hba_verboden_logica, "
        "(SELECT bn.bewijsvelden FROM brand_narratives bn "
        "WHERE bn.client_id = s.client_id AND bn.is_current = 1 ORDER BY bn.version DESC LIMIT 1) "
        "AS hba_bewijsvelden, "
        "(SELECT bn.aanspreekvorm FROM brand_narratives bn "
        "WHERE bn.client_id = s.client_id AND bn.is_current = 1 ORDER BY bn.version DESC LIMIT 1) "
        "AS hba_aanspreekvorm "
        "FROM content_slots s "
        "JOIN clients c ON s.client_id = c.id "
        "LEFT JOIN themes t ON t.id = s.theme_id "
        "LEFT JOIN articles a ON a.slot_id = s.id AND a.status IN ('draft', 'feedback') "
        "LEFT JOIN objectives obj ON obj.id = COALESCE(a.objective_final, a.objective_suggested, s.objective_id, t.objective_id) "
        "WHERE s.id = $1"
    )


def patch_ce03_objective_prompts(workflow: dict) -> None:
    """Objective in Stap A (rationale) + Stap C (suggested objective_id)."""
    step_a = find_node(workflow, "Claude Step A")
    if step_a is not None:
        body = step_a["parameters"].get("jsonBody", "")
        anchor = 'Merkdoel: " + $json.slot.brand_goal'
        inject = (
            'Merkdoel: " + $json.slot.brand_goal'
            '+ (($json.slot.objective_label || "").trim() '
            '? "\\n\\nDOELSTELLING VAN DEZE POST: " + $json.slot.objective_label '
            '+ (($json.slot.objective_description || "").trim() ? "\\n" + $json.slot.objective_description : "") '
            '+ "\\nDe rationale moet aansluiten op deze doelstelling." : "")'
        )
        if anchor in body and "DOELSTELLING VAN DEZE POST" not in body:
            step_a["parameters"]["jsonBody"] = body.replace(anchor, inject, 1)

    step_c = find_node(workflow, "Claude Step C")
    if step_c is None:
        return
    body = step_c["parameters"].get("jsonBody", "")
    if "objective_id" in body and "BESCHIKBARE DOELSTELLINGEN" in body:
        return

    props_anchor = 'hashtags: { type: "array", items: { type: "string" } }'
    props_new = (
        'hashtags: { type: "array", items: { type: "string" } }, '
        'objective_id: { type: "string", description: "ID van de meest passende objective uit de catalogus, of leeg" }'
    )
    if props_anchor in body and "objective_id:" not in body:
        body = body.replace(props_anchor, props_new, 1)

    catalog_inject = (
        '" + (function(){ '
        'try { const raw = $(\'Format Data\').item.json.slot.objectives_catalog; '
        'const list = typeof raw === "string" && raw ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []); '
        'if (!list.length) return ""; '
        'return "\\n\\nBESCHIKBARE DOELSTELLINGEN:\\n" + list.map(o => '
        '"- " + o.id + ": " + o.label + (o.description ? " (" + o.description + ")" : "")).join("\\n") '
        '+ "\\nKies objective_id die het beste past bij rationale en artikel."; '
        '} catch (e) { return ""; } })() + "'
    )
    marker = "Output ALLEEN geldige JSON via de tool."
    if marker in body and "BESCHIKBARE DOELSTELLINGEN" not in body:
        body = body.replace(marker, catalog_inject + marker, 1)

    step_c["parameters"]["jsonBody"] = body


# Legacy Stap C-fragmenten (schema + promptregels) van de verwijderde titelposter.
POSTER_STEP_C_FRAGMENTS = (
    ', poster_headline: { type: "string", description: "Pakkende posterregel als reactie op de kop, max 10 woorden, max 2 zinnen" }, poster_keyword_pair: { type: "object", description: "Extreemste compressie tot twee woorden, of null", properties: { word_main: { type: ["string", "null"] }, word_struck: { type: ["string", "null"] } } }',
    ', poster_headline: { type: "string", description: "Korte posterregel, max 10 woorden, max 2 zinnen, voor typografisch beeld" }',
    'poster_headline: { type: "string", description: "Pakkende posterregel als reactie op de kop, max 10 woorden, max 2 zinnen" }, poster_keyword_pair: { type: "object", description: "Extreemste compressie tot twee woorden, of null", properties: { word_main: { type: ["string", "null"] }, word_struck: { type: ["string", "null"] } } }',
    'poster_headline: { type: "string", description: "Korte posterregel, max 10 woorden, max 2 zinnen, voor typografisch beeld" }',
    '\\n\\nposter_headline: schrijf een posterregel voor het beeld bij deze LinkedIn-post. Dit is geen ingekorte titel — het is een eigen, pakkende uitspraak die op de kop reageert, hem uitdaagt, omdraait of overtreft. Denk als een copywriter die de kop net heeft gelezen en er in het voorbijgaan een puntige reactie op geeft, niet als een editor die hem moet inkorten.\\nDENKSTAPPEN (voer ze uit, geef alleen het eindresultaat):\\n1. Wat is de kern-bewering of het kern-inzicht van het artikel, in gewone mensentaal?\\n2. Wat is de meest voor de hand liggende, saaie manier om dat te zeggen? (nooit gebruiken)\\n3. Wat is de wending, de omdraai, of de directe aanspreking die dezelfde bewering ineens raak maakt?\\n4. Schrijf die wending op — dat is de posterregel.\\nREGELS: max 10 woorden, max 2 zinnen; spanning tussen twee delen; richt je waar het kan tot jij/je; spreektaal; geen jargon, geen dubbele punt, geen vraagteken als truc; geen aanhalingstekens; behoud merktoon en feitelijke kern. Is de titel al een puntige uitspraak van 10 woorden of korter: gebruik hem letterlijk.\\nVOORBEELDEN kop → posterregel (de tweede is een reactie, geen samenvatting):\\nWaarom een goede lunch belangrijker is dan je denkt tijdens BBQ-season → De grill kan wachten. Je lunch niet.\\nVijf manieren om je merkpositionering te bewaken in dagelijkse content → Elke post bouwt je merk. Of sloopt het.\\nDe nieuwe cao-regels voor flexwerkers uitgelegd → De regels zijn duidelijker. Jouw contract nog niet.\\nWaarom klanten steeds vaker zelf research doen voor ze kopen → Je klant weet al meer dan je denkt. Vertel hem iets nieuws.\\nDe boterham heeft geen upgrade nodig. Jij misschien wel. → ongewijzigd (al puntig).\\n\\nposter_keyword_pair: naast de posterregel de extreemste compressie tot twee woorden. Format: {word_main, word_struck}. word_main = één zelfstandig naamwoord, hoofdletters, max 20 tekens. word_struck = één woord dat de verwachting weerlegt of overtreft. Geen werkwoorden, bijvoeglijke naamwoorden of lidwoorden. Geen duidelijk kernobject (bijv. lijstje): beide velden null. Voorbeeld: boterham/upgrade → BOTERHAM / UPGRADE.',
    '\\n\\nposter_headline: herschrijf de titel als posterregel (max 10 woorden, max 2 zinnen, spreektaal, geen aanhalingstekens). Is de titel al kort en krachtig: gebruik letterlijk.',
)


def strip_ce03_poster_fields(workflow: dict) -> None:
    """Titelposter verwijderd: haal poster_headline / poster_keyword_pair uit schema en prompt van Stap C."""
    step_c = find_node(workflow, "Claude Step C")
    if step_c is None:
        return
    body = step_c["parameters"].get("jsonBody", "")

    for fragment in POSTER_STEP_C_FRAGMENTS:
        body = body.replace(fragment, "")
    for required in (', "poster_headline"', '"poster_headline", ', ', "poster_keyword_pair"', '"poster_keyword_pair", '):
        body = body.replace(required, "")

    step_c["parameters"]["jsonBody"] = body


def patch_ce03_tone_prompts(workflow: dict) -> None:
    """Stap C: tone uit schrijfwijzer (webhook tone_options), niet vaste b2b-enum."""
    step_c = find_node(workflow, "Claude Step C")
    if step_c is None:
        return
    body = step_c["parameters"].get("jsonBody", "")

    tone_prop = 'tone: { type: "string", description: "Db-key uit schrijfwijzer (tone_options)" }'
    for old in (
        'tone: { type: "string", enum: ["b2b", "b2c", "internal", "partners", "personal", "archetype", "adversary"] }',
        'tone: { type: "string", enum: ["b2b", "b2c", "internal", "partners", "personal"] }',
    ):
        if old in body:
            body = body.replace(old, tone_prop, 1)

    old_system = (
        'Waarden voor tone: b2b, b2c, internal, partners, personal. Waarden voor angle:'
    )
    tone_system_inject = (
        'Waarden voor tone: " + (function(){ '
        'try { const raw = $(\'Format Data\').item.json.slot.tone_options; '
        'const list = typeof raw === "string" && raw ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []); '
        'return list.length ? list.join(", ") : "zie schrijfwijzer"; '
        '} catch (e) { return "zie schrijfwijzer"; } })() + ". Waarden voor angle:'
    )
    if old_system in body and "tone_options" not in body:
        body = body.replace(old_system, tone_system_inject, 1)

    marker = "Output ALLEEN geldige JSON via de tool."
    tone_user_inject = (
        '" + (function(){ '
        'try { const raw = $(\'Format Data\').item.json.slot.tone_options; '
        'const list = typeof raw === "string" && raw ? JSON.parse(raw) : (Array.isArray(raw) ? raw : []); '
        'if (!list.length) return ""; '
        'return "\\n\\nBESCHIKBARE TONES (schrijfwijzer): " + list.join(", ") '
        '+ "\\nKies alleen een tone uit deze lijst."; '
        '} catch (e) { return ""; } })() + "'
    )
    if marker in body and "BESCHIKBARE TONES" not in body:
        body = body.replace(marker, tone_user_inject + marker, 1)

    step_c["parameters"]["jsonBody"] = body


CE03_SLOT_CONTEXT_NODE = "Merge Theme Conflict"

PREPARE_THEME_CONFLICT_JS = r"""
const fmt = $('Format Data').first().json;
const slot = fmt.slot || {};
const theme = String(slot.theme || slot.title || '').trim();
const regelTest = String(slot.hba_geloofwaardigheidstest || '').trim();
let verboden = [];
try {
  const raw = slot.hba_verboden_logica;
  const parsed = typeof raw === 'string' && raw.trim() !== '' ? JSON.parse(raw) : raw;
  verboden = Array.isArray(parsed) ? parsed.map((v) => String(v).trim()).filter(Boolean) : [];
} catch (e) {
  verboden = [];
}
let bewijsvelden = [];
try {
  const raw = slot.hba_bewijsvelden;
  const parsed = typeof raw === 'string' && raw.trim() !== '' ? JSON.parse(raw) : raw;
  bewijsvelden = Array.isArray(parsed) ? parsed.map((v) => String(v).trim()).filter(Boolean) : [];
} catch (e) {
  bewijsvelden = [];
}
const regelLines = [];
if (regelTest !== '') regelLines.push(`Geloofwaardigheidstest: ${regelTest}`);
if (verboden.length) regelLines.push(`Verboden logica:\n- ${verboden.join('\n- ')}`);
const regelsMerk = regelLines.length ? regelLines.join('\n') : '';
const bewijsText = bewijsvelden.length ? bewijsvelden.map((v) => `- ${v}`).join('\n') : 'Geen bewijsvelden vastgelegd.';
const researchText = String(fmt.research_summary || '').trim() || 'Geen research items gekoppeld.';
const skipThemeConflict = regelsMerk === '' || theme === '';

return {
  json: {
    ...fmt,
    theme_title: theme,
    regels_merk: regelsMerk,
    bewijsvelden_merk: bewijsText,
    research_summary: researchText,
    skip_theme_conflict: skipThemeConflict,
  },
};
"""

THEME_CONFLICT_JSON_BODY = r"""={{ JSON.stringify({
  model: "claude-sonnet-4-6",
  max_tokens: 1400,
  temperature: 0,
  system: "Je bent strategisch redacteur. Je beoordeelt of een gepland thema botst met vaste merkregels, en of het thema een feit over het merk aanneemt dat het merk niet heeft. Gebruik gewone klanttaal — geen jargon (geen geloofwaardigheidstest, verboden logica). Standaardkeuze bij conflict: schrijf de versie die de regel verdedigt (resolution a), tenzij geen geloofwaardige regelversie bestaat (resolution b). Neem nooit een resultaat, samenwerking of cijfer over dat niet in de merkfeiten of de research staat.",
  messages: [{
    role: "user",
    content: "THEMA VOOR DE POST:\n" + $json.theme_title + "\n\nREGELS VAN HET MERK:\n" + $json.regels_merk + "\n\nBEWIJSVELDEN VAN HET MERK:\n" + $json.bewijsvelden_merk + "\n\nRESEARCH BIJ DIT SLOT:\n" + $json.research_summary + "\n\nVRAAG 1 — CONFLICT: Kan dit thema alleen goed uitgewerkt worden door iets te doen of te beloven wat de regel verbiedt?\n- conflict = true als het thema de schrijver verleidt de regel te breken (bijv. kortingactie terwijl het merk nooit kort, partnerschap met leverancier terwijl het merk onafhankelijk blijft, wachtlijst weggewerkt terwijl het merk geen invalkrachten inzet).\n- conflict = false als het thema ook vanuit de regel te schrijven is zonder de regel te breken.\n\nVRAAG 2 — PREMISSE: neemt het thema iets aan over het merk als feit (een resultaat, een samenwerking, een prestatie, een cijfer) dat niet in de brand narrative, de bewijsvelden of de research staat? Of is dat resultaat alleen te halen met een middel dat de regel verbiedt?\nZo ja: theme_premisse = true. Neem die premisse niet over. Schrijf over de regel zelf: waarom het merk dit niet doet, en wat het in plaats daarvan doet. Vervang nooit alleen het middel terwijl je het resultaat uit het thema laat staan.\nVoorbeelden:\n- Fout: \"Onze wachtlijst is in één maand opgelost, door één cliënt door te verwijzen.\" Dat is hetzelfde resultaat met een ander middel, en het klopt niet.\n- Goed: \"We hebben een wachtlijst. Daarom hebben we vorige maand twee nieuwe aanvragen doorverwezen. Liever dat dan een vijfde gezicht aan de deur.\"\n- Fout: \"Wij werken samen met een ERP-leverancier, zonder contract.\" Het merk heeft die samenwerking niet.\n- Goed: \"Er werd ons een partnerschap aangeboden. We hebben nee gezegd, en dit is waarom.\"\n- theme_premisse = false alleen als het thema geen zo'n onbewezen feit of verboden resultaat als gegeven aanneemt.\n\nBij conflict=true of theme_premisse=true en resolution=a: geef write_instruction — concrete schrijfopdracht. Zelfde thema als kapstok, maar: verdedig de regel, neem de premisse niet over, verzin geen samenwerking/resultaat/cijfer dat niet in de bewijsvelden of research staat.\nBij conflict=true of theme_premisse=true en resolution=b: geef user_message — korte zin in klanttaal vóór het schrijven.\nVul theme_premisse_wat: in één zin welke premisse het thema aannam (of leeg als theme_premisse=false).\nVul theme_premisse_actie: wat de schrijver moet doen (bijv. \"niet overnemen; schrijven waarom we geen partnerschap aangaan\"), of leeg als theme_premisse=false.\nOutput via tool."
  }],
  tools: [{
    name: "theme_conflict_check",
    description: "Thema vs merkregels en premisse vóór het schrijven",
    input_schema: {
      type: "object",
      properties: {
        conflict: { type: "boolean" },
        theme_premisse: { type: "boolean" },
        theme_premisse_wat: { type: "string" },
        theme_premisse_actie: { type: "string" },
        resolution: { type: "string", enum: ["a", "b"] },
        why: { type: "string" },
        write_instruction: { type: "string" },
        user_message: { type: "string" }
      },
      required: ["conflict", "theme_premisse", "resolution", "why"]
    }
  }],
  tool_choice: { type: "tool", name: "theme_conflict_check" }
}) }}"""

MERGE_THEME_CONFLICT_JS = r"""
const prep = $('Prepare Theme Conflict').first().json;
const slot = { ...(prep.slot || {}) };
let themeConflictMeta = {
  had_conflict: false,
  resolution: null,
  why: null,
  theme_premisse: false,
  theme_premisse_wat: null,
  theme_premisse_actie: null,
};

function extractThemeConflict(response) {
  const content = response?.content;
  if (!Array.isArray(content)) return null;
  for (const block of content) {
    if (block?.type === 'tool_use' && block.name === 'theme_conflict_check' && block.input) {
      return block.input;
    }
  }
  return null;
}

if (!prep.skip_theme_conflict) {
  const input = extractThemeConflict($('Claude Theme Conflict').first()?.json) || {};
  const hasConflict = input.conflict === true;
  const hasPremisse = input.theme_premisse === true;
  themeConflictMeta.theme_premisse = hasPremisse;
  themeConflictMeta.theme_premisse_wat = String(input.theme_premisse_wat || '').trim() || null;
  themeConflictMeta.theme_premisse_actie = String(input.theme_premisse_actie || '').trim() || null;
  if (hasConflict || hasPremisse) {
    const resolution = String(input.resolution || 'a').toLowerCase() === 'b' ? 'b' : 'a';
    themeConflictMeta.had_conflict = hasConflict;
    themeConflictMeta.resolution = resolution;
    themeConflictMeta.why = String(input.why || '').trim() || null;
    if (resolution === 'a') {
      const block = String(input.write_instruction || '').trim();
      if (block !== '') {
        const prefix = 'THEMA, REGEL EN PREMISSE (verplicht — schrijf vanuit de regel, neem de premisse van het thema niet over als feit, breek de regel niet):\n';
        slot.write_directive = [String(slot.write_directive || '').trim(), prefix + block]
          .filter(Boolean)
          .join('\n\n');
      }
    } else {
      const msg = String(input.user_message || input.why || '').trim();
      if (msg !== '') {
        slot.write_directive = [String(slot.write_directive || '').trim(), 'CONFLICT MET MERKREGEL (melding vóór de post):\n' + msg]
          .filter(Boolean)
          .join('\n\n');
      }
    }
  }
}

return {
  json: {
    slot,
    research_summary: prep.research_summary,
    theme_conflict_meta: themeConflictMeta,
  },
};
"""


def patch_ce03_slot_context_refs(workflow: dict, context_node: str) -> None:
    """Verwijs Stap A/B/C naar Merge Theme Conflict i.p.v. Format Data (zelfde slot-shape)."""
    old = "$('Format Data')"
    new = f"$('{context_node}')"
    skip_names = {
        "Format Data",
        "Prepare Theme Conflict",
        "Theme Conflict Skip?",
        "Claude Theme Conflict",
        context_node,
    }
    for node in workflow.get("nodes", []):
        if node.get("name") in skip_names:
            continue
        params = node.setdefault("parameters", {})
        for key in ("jsCode", "jsonBody"):
            val = params.get(key)
            if isinstance(val, str) and old in val:
                params[key] = val.replace(old, new)


def add_theme_conflict_check(workflow: dict) -> None:
    """HBA v1.10 stap 2: conflict- + premissecheck vóór Stap A, default schrijven vanuit de regel (a)."""
    existing = find_node(workflow, CE03_SLOT_CONTEXT_NODE)
    if existing is not None:
        prep = find_node(workflow, "Prepare Theme Conflict")
        if prep is not None:
            prep["parameters"]["jsCode"] = PREPARE_THEME_CONFLICT_JS
        existing["parameters"]["jsCode"] = MERGE_THEME_CONFLICT_JS
        claude_tc = find_node(workflow, "Claude Theme Conflict")
        if claude_tc is not None:
            claude_tc["parameters"]["jsonBody"] = THEME_CONFLICT_JSON_BODY
        patch_ce03_slot_context_refs(workflow, CE03_SLOT_CONTEXT_NODE)
        return

    fmt = require_node(workflow, "Format Data")
    step_a = require_node(workflow, "Claude Step A")
    step_c = require_node(workflow, "Claude Step C")
    fx, fy = fmt["position"]

    prepare = {
        "parameters": {"jsCode": PREPARE_THEME_CONFLICT_JS},
        "id": node_id(),
        "name": "Prepare Theme Conflict",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [fx + 112, fy],
    }
    if_gate = {
        "parameters": {
            "conditions": {
                "boolean": [{"value1": "={{$json.skip_theme_conflict}}", "value2": True}],
            }
        },
        "id": node_id(),
        "name": "Theme Conflict Skip?",
        "type": "n8n-nodes-base.if",
        "typeVersion": 1,
        "position": [fx + 224, fy],
    }
    claude_tc = {
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
            "jsonBody": THEME_CONFLICT_JSON_BODY,
            "options": {},
        },
        "id": node_id(),
        "name": "Claude Theme Conflict",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [fx + 336, fy - 80],
        "credentials": step_c.get("credentials"),
    }
    merge = {
        "parameters": {"jsCode": MERGE_THEME_CONFLICT_JS},
        "id": node_id(),
        "name": CE03_SLOT_CONTEXT_NODE,
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [fx + 448, fy],
    }

    workflow["nodes"].extend([prepare, if_gate, claude_tc, merge])

    connections = workflow.setdefault("connections", {})
    connections["Format Data"] = {"main": [[{"node": "Prepare Theme Conflict", "type": "main", "index": 0}]]}
    connections["Prepare Theme Conflict"] = {
        "main": [[{"node": "Theme Conflict Skip?", "type": "main", "index": 0}]]
    }
    connections["Theme Conflict Skip?"] = {
        "main": [
            [{"node": CE03_SLOT_CONTEXT_NODE, "type": "main", "index": 0}],
            [{"node": "Claude Theme Conflict", "type": "main", "index": 0}],
        ]
    }
    connections["Claude Theme Conflict"] = {
        "main": [[{"node": CE03_SLOT_CONTEXT_NODE, "type": "main", "index": 0}]]
    }
    connections[CE03_SLOT_CONTEXT_NODE] = {
        "main": [[{"node": step_a["name"], "type": "main", "index": 0}]]
    }

    patch_ce03_slot_context_refs(workflow, CE03_SLOT_CONTEXT_NODE)


def add_step_d(workflow: dict) -> None:
    if find_node(workflow, "Claude Step D") is not None:
        return

    step_c = require_node(workflow, "Claude Step C")
    validate = require_node(workflow, "Validate Data")
    insert = require_node(workflow, "Insert Article")

    prepare = {
        "parameters": {"jsCode": PREPARE_SELF_REVIEW_JS},
        "id": node_id(),
        "name": "Prepare Self Review",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [step_c["position"][0] + 112, step_c["position"][1]],
    }

    step_d = {
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
            "jsonBody": STEP_D_JSON_BODY,
            "options": {},
        },
        "id": node_id(),
        "name": "Claude Step D",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [step_c["position"][0] + 224, step_c["position"][1]],
        "credentials": step_c.get("credentials"),
    }

    validate["position"] = [step_d["position"][0] + 112, step_d["position"][1]]

    insert["parameters"]["query"] = INSERT_SQL
    insert["parameters"]["options"]["queryReplacement"] = INSERT_REPLACEMENT

    workflow["nodes"].extend([prepare, step_d])

    connections = workflow.setdefault("connections", {})
    connections["Claude Step C"] = {"main": [[{"node": "Prepare Self Review", "type": "main", "index": 0}]]}
    connections["Prepare Self Review"] = {"main": [[{"node": "Claude Step D", "type": "main", "index": 0}]]}
    connections["Claude Step D"] = {"main": [[{"node": "Validate Data", "type": "main", "index": 0}]]}

    # Step C tone enum — briefing v11 (7 tone opties)
    step_c_body = step_c["parameters"].get("jsonBody", "")
    if '"b2b", "b2c", "internal", "partners", "personal"' in step_c_body and "archetype" not in step_c_body:
        step_c["parameters"]["jsonBody"] = step_c_body.replace(
            '"tone": { type: "string", enum: ["b2b", "b2c", "internal", "partners", "personal"] }',
            '"tone": { type: "string", enum: ["b2b", "b2c", "internal", "partners", "personal", "archetype", "adversary"] }',
        )


def main() -> None:
    if not JSON.exists():
        raise SystemExit(f"Missing {JSON}")

    workflow = json.loads(JSON.read_text(encoding="utf-8"))
    add_step_d(workflow)
    remove_editor_questions_step(workflow)
    apply_writing_patches(workflow)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(workflow, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    patch_workflow_file(OUT)
    print("Wrote", OUT)


if __name__ == "__main__":
    main()
