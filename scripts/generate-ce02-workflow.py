#!/usr/bin/env python3
"""Generate CE-02 Research & Inspiratie n8n workflow JSON."""
import json
import os
import sys
from pathlib import Path

MYSQL_CRED = {"mySql": {"id": "taJrWdU0RBkbtU0R", "name": "Content Engine"}}
CLAUDE_CRED = {"httpHeaderAuth": {"id": "GhgOhw7oQpIMZDDo", "name": "Header Auth account"}}

# Pinned test payload for manual "Execute workflow" in n8n (update slot_id when needed).
CE02_PIN_SLOT_ID = "3eef1d6b-0a3f-4073-9e7e-bdde7d4740be"


def js_string(value: str) -> str:
    return json.dumps(value)


sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_env import load_env, target_env, webhook_path
from prompt_fragments import patch_json_body_system_prompt

_env = target_env()
load_env(_env if _env != "production" else None)
CE_PUBLIC_BASE_URL = (os.environ.get("CE_PUBLIC_BASE_URL") or "https://ce.igisnext.com").rstrip("/")
CE_RESET_TOKEN = os.environ.get("CE_RESET_TOKEN", "")
CE02_WEBHOOK_PATH = webhook_path("ce02-research")
TRELLO_CARDS_WEBHOOK_PATH = webhook_path("ce-trello-cards")

PARSE_JS = r"""
const raw = $input.first()?.json ?? {};
let body = raw.body ?? raw;

if (typeof body === 'string') {
  try {
    body = JSON.parse(body);
  } catch {
    body = {};
  }
}

if (body && typeof body === 'object' && body.body && typeof body.body === 'object') {
  body = body.body;
}

const query = raw.query && typeof raw.query === 'object' ? raw.query : {};

function pick(obj, ...keys) {
  if (!obj || typeof obj !== 'object') return '';
  for (const key of keys) {
    const value = obj[key];
    if (value !== undefined && value !== null && String(value).trim() !== '') {
      return String(value).trim();
    }
  }
  return '';
}

const themeId =
  pick(body, 'theme_id', 'themeId') ||
  pick(query, 'theme_id', 'themeId') ||
  pick(raw, 'theme_id', 'themeId');
const slotId =
  pick(body, 'slot_id', 'slotId') ||
  pick(query, 'slot_id', 'slotId') ||
  pick(raw, 'slot_id', 'slotId');

if (!themeId && !slotId) {
  throw new Error(
    'Missing theme_id or slot_id. Stuur JSON: {"theme_id":"..."} of {"slot_id":"..."} (n8n: pin testdata op Webhook-node).'
  );
}

return { json: { theme_id: themeId, slot_id: slotId } };
"""

PREPARE_FEEDS_JS = r"""
const ctx = $('Load Context').item.json;
const preferredFolder = String(ctx.objective_source_folder_id || '').trim();
const rows = $('Get Sources').all()
  .map((i) => i.json)
  .sort((a, b) => {
    const aMatch = preferredFolder && String(a.folder_id || '') === preferredFolder ? 0 : 1;
    const bMatch = preferredFolder && String(b.folder_id || '') === preferredFolder ? 0 : 1;
    return aMatch - bMatch;
  });
const feedUrls = rows
  .filter((row) => (row.category === 'url_rss' || row.category === 'youtube') && row.url)
  .map((row) => ({
    url: String(row.url).trim(),
    source_id: row.id || null,
    source_title: row.title || (row.category === 'youtube' ? 'YouTube' : 'RSS'),
    source_kind: String(row.source_kind || (row.category === 'youtube' ? 'rss' : 'rss')).trim().toLowerCase(),
  }));
const trelloBoards = rows
  .filter((row) => row.category === 'trello' && row.trello_board_id)
  .map((row) => ({
    board_id: String(row.trello_board_id).trim(),
    source_id: row.id || null,
    source_title: row.title || 'Trello',
  }));
const scraperSources = rows
  .filter((row) => row.category === 'scraper')
  .map((row) => ({
    source_id: row.id || null,
    source_title: row.title || 'Scraper',
    max_units_per_run: row.max_units_per_run,
  }));
const blogSources = rows
  .filter((row) => row.category === 'blogs' && row.url)
  .map((row) => ({
    source_id: row.id || null,
    source_title: row.title || 'Blogs',
    url: String(row.url).trim(),
  }));
const documentSources = rows
  .filter((row) => row.category === 'document')
  .map((row) => ({
    source_id: row.id || null,
    source_title: row.title || 'Document',
  }));
const asanaSources = rows
  .filter((row) => row.category === 'asana' && row.asana_project_gid)
  .map((row) => ({
    source_id: row.id || null,
    source_title: row.title || 'Asana',
  }));
const driveSources = rows
  .filter((row) => row.category === 'drive' && row.drive_file_url)
  .map((row) => ({
    source_id: row.id || null,
    source_title: row.title || 'Google Drive',
  }));
const themeOnly = Boolean(String(ctx.theme_id || '').trim()) && !String(ctx.slot_id || '').trim();
const searchLabel = String(ctx.search_label || ctx.theme_title || 'nieuws').trim();
const googleNewsUrl = `https://news.google.com/rss/search?q=${encodeURIComponent(searchLabel)}&hl=nl&gl=NL&ceid=NL:nl`;
const alreadyHasGoogleNews = feedUrls.some((f) =>
  String(f.url || '').toLowerCase().includes('news.google.com/rss/search'),
);
// Backup wanneer de klant geen bronnen heeft, of wel bronnen maar geen RSS/YouTube-feeds.
if (!alreadyHasGoogleNews && (rows.length === 0 || feedUrls.length === 0)) {
  feedUrls.push({
    url: googleNewsUrl,
    source_id: null,
    source_title: 'Google News',
    source_kind: 'rss',
  });
}
return {
  json: {
    ...ctx,
    feed_urls: feedUrls.slice(0, 6),
    trello_boards: trelloBoards,
    scraper_sources: themeOnly ? [] : scraperSources,
    blog_sources: blogSources,
    document_sources: documentSources,
    asana_sources: asanaSources,
    drive_sources: driveSources,
    theme_only: themeOnly,
  },
};
"""

FETCH_RSS_JS = r"""
const ctx = $('Prepare Feed URLs').item.json;
const feedUrls = ctx.feed_urls || [];
const trelloBoards = ctx.trello_boards || [];
const items = [];

function envVar(name) {
  try {
    return String($env[name] || '').trim();
  } catch (e) {
    return '';
  }
}

const key = envVar('TRELLO_API_KEY');
const token = envVar('TRELLO_TOKEN');
const ceBase = __CE_PUBLIC_BASE_URL__;
const ceResetToken = __CE_RESET_TOKEN__;

async function loadScraperItems(source) {
  const sourceId = String(source?.source_id || '').trim();
  if (!sourceId) return [];
  if (!ceResetToken) return [];

  const payload = {
    theme_id: ctx.theme_id || null,
    slot_id: ctx.slot_id || null,
    search_label: ctx.search_label || ctx.theme_title || '',
  };

  try {
    const res = await this.helpers.httpRequest({
      method: 'POST',
      url: `${ceBase.replace(/\/$/, '')}/api/sources/${encodeURIComponent(sourceId)}/scraper-run`,
      timeout: 180000,
      headers: {
        'User-Agent': 'ContentEngine/1.0',
        'Content-Type': 'application/json',
        'X-CE-Reset-Token': ceResetToken,
      },
      body: payload,
      json: true,
    });

    if (!res?.ok || !Array.isArray(res.items)) {
      return [];
    }

    return res.items.map((row) => ({
      title: String(row.title || '').trim(),
      link: String(row.url || row.link || '').trim(),
      contentSnippet: String(row.contentSnippet || row.snippet || '').trim().slice(0, 500),
      source_id: row.source_id || sourceId,
      source_title: row.source_title || source.source_title || 'Scraper',
      source_type: String(row.source_type || 'scraper'),
    })).filter((row) => row.title);
  } catch (e) {
    return [];
  }
}

async function loadBlogItems(source) {
  const sourceId = String(source?.source_id || '').trim();
  if (!sourceId) return [];
  if (!ceResetToken) return [];

  try {
    const res = await this.helpers.httpRequest({
      method: 'POST',
      url: `${ceBase.replace(/\/$/, '')}/api/sources/${encodeURIComponent(sourceId)}/blog-run`,
      timeout: 180000,
      headers: {
        'User-Agent': 'ContentEngine/1.0',
        'Content-Type': 'application/json',
        'X-CE-Reset-Token': ceResetToken,
      },
      body: {},
      json: true,
    });

    if (!res?.ok || !Array.isArray(res.items)) {
      return [];
    }

    return res.items.map((row) => ({
      title: String(row.title || '').trim(),
      link: String(row.url || row.link || '').trim(),
      contentSnippet: String(row.contentSnippet || row.snippet || '').trim().slice(0, 500),
      source_id: row.source_id || sourceId,
      source_title: row.source_title || source.source_title || 'Blogs',
      source_type: String(row.source_type || 'blog'),
    })).filter((row) => row.title);
  } catch (e) {
    return [];
  }
}

async function loadDocumentItems(source) {
  const sourceId = String(source?.source_id || '').trim();
  if (!sourceId) return [];
  if (!ceResetToken) return [];

  try {
    const res = await this.helpers.httpRequest({
      method: 'POST',
      url: `${ceBase.replace(/\/$/, '')}/api/sources/${encodeURIComponent(sourceId)}/document-run`,
      timeout: 180000,
      headers: {
        'User-Agent': 'ContentEngine/1.0',
        'Content-Type': 'application/json',
        'X-CE-Reset-Token': ceResetToken,
      },
      body: {},
      json: true,
    });

    if (!res?.ok || !Array.isArray(res.items)) {
      return [];
    }

    return res.items.map((row) => ({
      title: String(row.title || '').trim(),
      link: String(row.url || row.link || '').trim(),
      contentSnippet: String(row.contentSnippet || row.snippet || '').trim().slice(0, 500),
      source_id: row.source_id || sourceId,
      source_title: row.source_title || source.source_title || 'Document',
      source_type: String(row.source_type || 'document'),
    })).filter((row) => row.title);
  } catch (e) {
    return [];
  }
}

async function loadAsanaItems(source) {
  const sourceId = String(source?.source_id || '').trim();
  if (!sourceId) return [];
  if (!ceResetToken) return [];

  try {
    const res = await this.helpers.httpRequest({
      method: 'POST',
      url: `${ceBase.replace(/\/$/, '')}/api/sources/${encodeURIComponent(sourceId)}/asana-run`,
      timeout: 180000,
      headers: {
        'User-Agent': 'ContentEngine/1.0',
        'Content-Type': 'application/json',
        'X-CE-Reset-Token': ceResetToken,
      },
      body: {},
      json: true,
    });

    if (!res?.ok || !Array.isArray(res.items)) {
      return [];
    }

    return res.items.map((row) => ({
      title: String(row.title || '').trim(),
      link: String(row.url || row.link || '').trim(),
      contentSnippet: String(row.contentSnippet || row.snippet || '').trim().slice(0, 500),
      source_id: row.source_id || sourceId,
      source_title: row.source_title || source.source_title || 'Asana',
      source_type: String(row.source_type || 'asana'),
    })).filter((row) => row.title);
  } catch (e) {
    return [];
  }
}

async function loadDriveItems(source) {
  const sourceId = String(source?.source_id || '').trim();
  if (!sourceId) return [];
  if (!ceResetToken) return [];

  try {
    const res = await this.helpers.httpRequest({
      method: 'POST',
      url: `${ceBase.replace(/\/$/, '')}/api/sources/${encodeURIComponent(sourceId)}/drive-run`,
      timeout: 180000,
      headers: {
        'User-Agent': 'ContentEngine/1.0',
        'Content-Type': 'application/json',
        'X-CE-Reset-Token': ceResetToken,
      },
      body: {},
      json: true,
    });

    if (!res?.ok || !Array.isArray(res.items)) {
      return [];
    }

    return res.items.map((row) => ({
      title: String(row.title || '').trim(),
      link: String(row.url || row.link || '').trim(),
      contentSnippet: String(row.contentSnippet || row.snippet || '').trim().slice(0, 500),
      source_id: row.source_id || sourceId,
      source_title: row.source_title || source.source_title || 'Google Drive',
      source_type: String(row.source_type || 'drive'),
    })).filter((row) => row.title);
  } catch (e) {
    return [];
  }
}

function trelloCardsWebhookUrl(boardId) {
  let base = '';
  try {
    base = String($env.N8N_API_URL || '').replace(/\/$/, '');
  } catch (e) {
    // n8n Cloud blocks env access in Code nodes
  }
  if (!base) {
    base = 'https://erikjihaa.app.n8n.cloud';
  }
  return `${base}/webhook/ce-trello-cards?board_id=${encodeURIComponent(boardId)}`;
}

async function loadTrelloCards(boardId) {
  if (key && token) {
    try {
      const url = `https://api.trello.com/1/boards/${encodeURIComponent(boardId)}/cards?key=${encodeURIComponent(key)}&token=${encodeURIComponent(token)}&fields=name,desc,url,shortUrl&filter=open`;
      const cards = await this.helpers.httpRequest({
        method: 'GET',
        url,
        timeout: 15000,
        headers: { 'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' },
      });
      if (Array.isArray(cards) && cards.length > 0) {
        return cards;
      }
    } catch (e) {
      // fallback to n8n webhook
    }
  }

  const res = await this.helpers.httpRequest({
    method: 'GET',
    url: trelloCardsWebhookUrl(boardId),
    timeout: 15000,
    headers: { 'User-Agent': 'ContentEngine/1.0' },
  });
  if (Array.isArray(res?.cards)) {
    return res.cards;
  }
  return [];
}

function between(text, startTag, endTag) {
  const start = text.indexOf(startTag);
  if (start < 0) return '';
  const from = start + startTag.length;
  const end = text.indexOf(endTag, from);
  if (end < 0) return '';
  return text.slice(from, end).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
}

function unescapeXml(text) {
  return String(text || '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#39;/g, "'");
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

function extractArticleLink(block, rawLink) {
  const link = unescapeXml(rawLink).trim();
  const desc = unescapeXml(
    between(block, '<description>', '</description>') || between(block, '<summary>', '</summary>'),
  );

  if (desc) {
    const hrefs = [...desc.matchAll(/href=["'](https?:\/\/[^"']+)["']/gi)].map((m) => m[1]);
    for (const href of hrefs) {
      if (!href.toLowerCase().includes('google.com')) return href;
    }
  }

  if (link && !isFeedOrSearchUrl(link)) return link;
  return link;
}

function metaContent(html, attr, value) {
  const re1 = new RegExp(`<meta[^>]+${attr}=["']${value}["'][^>]+content=["']([^"']+)`, 'i');
  const m1 = html.match(re1);
  if (m1) return m1[1].replace(/\\s+/g, ' ').trim();
  const re2 = new RegExp(`<meta[^>]+content=["']([^"']+)["'][^>]+${attr}=["']${value}["']`, 'i');
  const m2 = html.match(re2);
  if (m2) return m2[1].replace(/\\s+/g, ' ').trim();
  return '';
}

function webpageTitle(html) {
  let t = metaContent(html, 'property', 'og:title');
  if (t) return t;
  t = between(html, '<title>', '</title>');
  if (t) return t;
  t = between(html, '<h1>', '</h1>');
  return t;
}

function webpageSnippet(html, fallbackUrl) {
  let s = metaContent(html, 'property', 'og:description');
  if (!s) s = metaContent(html, 'name', 'description');
  if (!s) s = metaContent(html, 'name', 'twitter:description');
  return s || `Webpagina: ${fallbackUrl}`;
}

for (const row of $('Get Newsletter Items').all().map((i) => i.json)) {
  const title = String(row.title || row.subject || '').trim();
  if (!title) continue;
  items.push({
    title,
    link: '',
    contentSnippet: String(row.contentSnippet || row.body_text || '').trim().slice(0, 500),
    source_id: row.source_id || null,
    source_title: row.source_title || 'Mailbox',
    source_type: 'mailbox',
  });
  if (items.length >= 24) break;
}

for (const feed of feedUrls) {
  const kind = String(feed.source_kind || 'rss').toLowerCase();
  if (kind === 'webpage') {
    try {
      const html = String(await this.helpers.httpRequest({
        method: 'GET',
        url: feed.url,
        timeout: 15000,
        headers: { 'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' },
      }));
      const title = webpageTitle(html);
      if (!title) continue;
      items.push({
        title,
        link: feed.url,
        contentSnippet: webpageSnippet(html, feed.url).slice(0, 500),
        source_id: feed.source_id,
        source_title: feed.source_title,
        source_type: 'webpage',
      });
    } catch (e) {
      // skip broken page
    }
    continue;
  }

  try {
    const xml = await this.helpers.httpRequest({
      method: 'GET',
      url: feed.url,
      timeout: 15000,
      headers: { 'User-Agent': 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36' },
    });
    const text = String(xml);
    let pos = 0;
    while (items.length < 24) {
      const itemStart = text.indexOf('<item', pos);
      if (itemStart < 0) break;
      const itemEnd = text.indexOf('</item>', itemStart);
      if (itemEnd < 0) break;
      const block = text.slice(itemStart, itemEnd + 7);
      pos = itemEnd + 7;
      const title = between(block, '<title>', '</title>');
      const rawLink = between(block, '<link>', '</link>');
      const link = extractArticleLink(block, rawLink);
      const snippet = between(block, '<description>', '</description>') || between(block, '<summary>', '</summary>');
      if (!title) continue;
      items.push({
        title,
        link,
        contentSnippet: snippet,
        source_id: feed.source_id,
        source_title: feed.source_title,
        source_type: 'rss',
      });
    }
  } catch (e) {
    // skip broken feed
  }
}

for (const board of trelloBoards) {
  try {
    const cards = await loadTrelloCards.call(this, board.board_id);
    for (const card of cards) {
      const title = String(card.name || '').trim();
      if (!title) continue;
      items.push({
        title,
        link: String(card.shortUrl || card.url || '').trim(),
        contentSnippet: String(card.desc || '').trim().slice(0, 500),
        source_id: board.source_id,
        source_title: board.source_title || 'Trello',
        source_type: 'trello',
      });
      if (items.length >= 24) break;
    }
  } catch (e) {
    // skip broken board
  }
  if (items.length >= 24) break;
}

const scraperSources = ctx.scraper_sources || [];
for (const source of scraperSources) {
  try {
    const scraped = await loadScraperItems.call(this, source);
    for (const row of scraped) {
      items.push(row);
      if (items.length >= 24) break;
    }
  } catch (e) {
    // skip broken scraper
  }
  if (items.length >= 24) break;
}

const blogSources = ctx.blog_sources || [];
for (const source of blogSources) {
  try {
    const posts = await loadBlogItems.call(this, source);
    for (const row of posts) {
      items.push(row);
      if (items.length >= 24) break;
    }
  } catch (e) {
    // skip broken blog overview
  }
  if (items.length >= 24) break;
}

const documentSources = ctx.document_sources || [];
for (const source of documentSources) {
  try {
    const chunks = await loadDocumentItems.call(this, source);
    for (const row of chunks) {
      items.push(row);
      if (items.length >= 24) break;
    }
  } catch (e) {
    // skip broken document source
  }
  if (items.length >= 24) break;
}

const asanaSources = ctx.asana_sources || [];
for (const source of asanaSources) {
  try {
    const tasks = await loadAsanaItems.call(this, source);
    for (const row of tasks) {
      items.push(row);
      if (items.length >= 24) break;
    }
  } catch (e) {
    // skip broken asana source
  }
  if (items.length >= 24) break;
}

const driveSources = ctx.drive_sources || [];
for (const source of driveSources) {
  try {
    const chunks = await loadDriveItems.call(this, source);
    for (const row of chunks) {
      items.push(row);
      if (items.length >= 24) break;
    }
  } catch (e) {
    // skip broken drive source
  }
  if (items.length >= 24) break;
}

async function appendGoogleNewsRssItems() {
  const query = String(ctx.search_label || ctx.theme_title || 'nieuws').trim();
  const url = `https://news.google.com/rss/search?q=${encodeURIComponent(query)}&hl=nl&gl=NL&ceid=NL:nl`;
  try {
    const xml = await this.helpers.httpRequest({
      method: 'GET',
      url,
      timeout: 15000,
      headers: {
        'User-Agent':
          'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
      },
    });
    const text = String(xml);
    let pos = 0;
    while (items.length < 24) {
      const itemStart = text.indexOf('<item', pos);
      if (itemStart < 0) break;
      const itemEnd = text.indexOf('</item>', itemStart);
      if (itemEnd < 0) break;
      const block = text.slice(itemStart, itemEnd + 7);
      pos = itemEnd + 7;
      const title = between(block, '<title>', '</title>');
      const rawLink = between(block, '<link>', '</link>');
      const link = extractArticleLink(block, rawLink);
      const snippet =
        between(block, '<description>', '</description>') || between(block, '<summary>', '</summary>');
      if (!title) continue;
      items.push({
        title,
        link,
        contentSnippet: snippet,
        source_id: null,
        source_title: 'Google News',
        source_type: 'rss',
      });
    }
  } catch (e) {
    // backup mislukt — lege lijst blijft
  }
}

if (items.length === 0) {
  await appendGoogleNewsRssItems.call(this);
}

if (items.length === 0) {
  return [{ json: { title: '', link: '', contentSnippet: '' } }];
}

return items.map((row) => ({ json: row }));
"""

COLLECT_CANDIDATES_JS = r"""
const ctx = $('Merge Context').item.json;
const packSize = 5;
const maxItems = packSize;
const limit = Math.max(Number(ctx.max_items || 8), packSize) * 2;
const themeOnly = Boolean(String(ctx.theme_id || '').trim()) && !String(ctx.slot_id || '').trim();
const preferredFolder = String(ctx.objective_source_folder_id || '').trim();
const sourceRows = $('Get Sources').all().map((item) => item.json);
const folderBySource = new Map(
  sourceRows.map((row) => [String(row.id || ''), String(row.folder_id || '').trim()])
);
const seen = new Set();
const tierBuckets = [
  { trello: [], asana: [], mailbox: [], scraper: [], linkedin: [], blog: [], document: [], drive: [], rss: [], other: [] },
  { trello: [], asana: [], mailbox: [], scraper: [], linkedin: [], blog: [], document: [], drive: [], rss: [], other: [] },
  { trello: [], asana: [], mailbox: [], scraper: [], linkedin: [], blog: [], document: [], drive: [], rss: [], other: [] },
];

function tierFor(row, type) {
  const folderId = folderBySource.get(String(row.source_id || '')) || '';
  if (preferredFolder && folderId === preferredFolder) return 0;
  if (type === 'mailbox') return 1;
  return 2;
}

const bucketOrder = ['trello', 'asana', 'mailbox', 'scraper', 'linkedin', 'blog', 'document', 'drive', 'rss', 'other'];

for (const item of $input.all()) {
  const row = item.json;
  const title = String(row.title || row.headline || '').trim();
  const url = String(row.link || row.url || row.guid || '').trim();
  if (!title || title.toLowerCase() === 'untitled') continue;
  const snippet = String(row.contentSnippet || row.summary || row.description || row.content || '').trim().slice(0, 500);
  if (snippet.length < 80) continue;
  const key = (title + '|' + url).toLowerCase();
  if (seen.has(key)) continue;
  seen.add(key);
  const type = String(row.source_type || 'rss').toLowerCase();
  if (type === 'linkedin') continue;
  if (themeOnly && (type === 'scraper' || type === 'linkedin')) continue;
  const tier = tierFor(row, type);
  const bucket = tierBuckets[tier][type] || tierBuckets[tier].other;
  bucket.push({
    title,
    url,
    snippet,
    source_id: row.source_id || null,
    source_title: row.source_title || type,
    source_type: row.source_type || 'rss',
  });
}

const ordered = [];
for (const buckets of tierBuckets) {
  for (const key of bucketOrder) {
    ordered.push(...buckets[key]);
  }
}
const candidates = ordered.slice(0, limit).map((c, index) => ({
  id: `c${index + 1}`,
  ...c,
}));

if (candidates.length === 0) {
  candidates.push({
    id: 'c1',
    title: String(ctx.theme_title || 'Thema'),
    url: '',
    snippet: String(ctx.theme_rationale || ctx.motivation || '').slice(0, 500),
    source_id: null,
    source_title: 'theme',
    source_type: 'theme',
  });
}

return {
  json: {
    ...ctx,
    candidates,
    max_items: maxItems,
    pack_size: packSize,
  },
};
"""

MERGE_CONTEXT_JS = r"""
const ctx = $('Validate Context').item.json;
const recent = $('Load Recent Invalshoeken').all()
  .map((item) => String(item.json.invalshoek || '').trim())
  .filter(Boolean);
return { json: { ...ctx, recente_invalshoeken: recent } };
"""

PREPARE_INSERTS_JS = r"""
const ctx = $('Collect Candidates').item.json;
const claude = $('Claude Research Pack').item.json;
const input = claude?.content?.[0]?.input ?? {};
const items = Array.isArray(input.items) ? input.items : [];
const allowedInvalshoeken = new Set([
  'tegenkracht', 'bewijs', 'contra_intuitief', 'aanpalend', 'praktijk', 'tijd', 'systeem',
]);
const allowedStance = new Set(['bevestigt', 'nuanceert', 'spreekt_tegen']);
const allowedReliability = new Set(['low', 'medium', 'high']);
const byId = new Map((ctx.candidates || []).map((c) => [c.id, c]));

function makeUuid() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
    const r = (Math.random() * 16) | 0;
    const v = c === 'x' ? r : (r & 0x3) | 0x8;
    return v.toString(16);
  });
}

const out = [];
for (const item of items) {
  const base = byId.get(item.id) || {};
  const title = String(item.title || base.title || '').trim();
  const invalshoek = String(item.invalshoek || '').trim();
  const snippet = String(item.snippet || base.snippet || '').trim();
  const stance = String(item.stance || 'bevestigt').trim();
  const reliability = String(item.reliability || 'medium').trim();
  if (!title || title.toLowerCase() === 'untitled') continue;
  if (snippet.length < 80) continue;
  if (!allowedInvalshoeken.has(invalshoek)) {
    throw new Error('Invalid or missing invalshoek on item: ' + title);
  }
  if (!allowedStance.has(stance)) {
    throw new Error('Invalid stance on item: ' + title);
  }
  if (!allowedReliability.has(reliability)) {
    throw new Error('Invalid reliability on item: ' + title);
  }
  out.push({
    json: {
      id: makeUuid(),
      theme_id: ctx.theme_id || null,
      slot_id: ctx.slot_id || null,
      source_id: base.source_id || null,
      source_type: base.source_type || (base.source_type === 'mailbox' ? 'mailbox' : (String(base.source_title || '').toLowerCase() === 'trello' ? 'trello' : 'rss')),
      title,
      snippet,
      url: String(item.url || base.url || '').trim() || null,
      item_rationale: String(item.item_rationale || '').trim(),
      invalshoek,
      stance,
      reliability,
    },
  });
}

const angles = out.map((row) => row.json.invalshoek);
if (out.length !== 5 || new Set(angles).size !== 5) {
  throw new Error('Expected exactly 5 unique invalshoek values, got: ' + angles.join(', '));
}

const grounded = out.filter((row) => {
  if (String(row.json.url || '').trim() !== '') return true;
  return row.json.source_id != null;
}).length;
if (grounded < 2) {
  throw new Error('Expected at least 2 items grounded in a candidate source, got: ' + grounded);
}

if (out.length === 0) {
  throw new Error('Claude returned no research items');
}

return out;
"""

RESPOND_JS = r"""
const inserted = $('Prepare Inserts').all().map((i) => i.json);
const ctx = $('Collect Candidates').item.json;
return {
  json: {
    ok: true,
    theme_id: ctx.theme_id || null,
    slot_id: ctx.slot_id || null,
    items_created: inserted.length,
    item_ids: inserted.map((r) => r.id),
  },
};
"""

CLAUDE_RESEARCH_JSON_BODY = r"""={{ JSON.stringify({
  model: "claude-sonnet-4-6",
  max_tokens: 4096,
  system: "Je bent research-analist voor " + $json.client_name + ". Archetype: " + $json.archetype + ". Adversary: " + $json.adversary + ". Merkdoel: " + $json.brand_goal + ". Stel een gevarieerde researchset samen voor het thema. Niet de vijf meest voor de hand liggende bronnen, maar vijf verschillende INVALSHOEKEN op hetzelfde thema.\n\nBESCHIKBARE INVALSHOEKEN (kies er precies vijf, elk maximaal één keer):\n  tegenkracht      — het thema vanuit waar dit merk tegen vecht\n  bewijs           — een concreet geval, cijfer of meting\n  contra_intuitief — de stelling die de meeste vakgenoten niet zouden innemen\n  aanpalend        — hetzelfde mechanisme in een andere sector of vakgebied\n  praktijk         — hoe dit er op een gewone werkdag uitziet, voor een mens\n  tijd             — een historische parallel, of wat hier over drie jaar anders is\n  systeem          — de bredere keten of maatschappelijke context\n\nPer item vul je ook stance en reliability in:\n- stance: bevestigt | nuanceert | spreekt_tegen (verhouding tot het thema)\n- reliability: high (onderzoek/cijfers/primaire bron) | medium (vakmedia) | low (mening/blog/ongedateerd)\nLever minstens één item met stance = spreekt_tegen als de kandidaten dat toelaten.\n\nREGELS:\n- Elke invalshoek precies één keer. Vijf verschillende, geen dubbele.\n- Geen item zonder titel of met snippet korter dan 80 tekens.\n- Gebruik een kandidaat-bron waar die past bij de invalshoek. Past er geen enkele bron bij een invalshoek die het thema sterker maakt, formuleer die invalshoek dan zelf en laat url leeg — dat is een aanzet voor de redactie, geen bron.\n- Avontuurlijk in invalshoek, streng in merk. Een verrassende hoek die niet bij dit archetype, dit merkdoel of deze tegenkracht past, is geen goede hoek. Schrap hem dan.\n- Vermijd invalshoeken die de afgelopen weken al gebruikt zijn (zie RECENT).\n- Trello- en Asana-items zijn interne PR-concepten — neem minimaal 1 op als het past. Document- en Drive-fragmenten zijn eigen kennis — gebruik ze als achtergrond, niet als extern nieuws. Output via tool.",
  messages: [{
    role: "user",
    content: "THEMA: " + $json.theme_title + "\nMotivation: " + ($json.motivation || "") + "\nRationale: " + ($json.theme_rationale || "") + "\n\nRECENT GEBRUIKTE INVALSHOEKEN (laatste 6 weken):\n  " + ((($json.recente_invalshoeken || []).length) ? ($json.recente_invalshoeken || []).join(", ") : "(geen)") + "\n\nKANDIDATEN:\n" + ($json.candidates || []).map(c => "[" + c.id + "] (" + (c.source_type || "rss") + ") " + c.title + "\nURL: " + c.url + "\nRAW: " + (c.snippet || "").slice(0, 300)).join("\n\n") + "\n\nLever exact vijf items, elk met een andere invalshoek. Minimaal drie ervan moeten op een echte kandidaat-bron rusten; de rest mag een zelf geformuleerde aanzet zijn. Gebruik kandidaat-id's waar mogelijk."
  }],
  tools: [{
    name: "pack_research_items",
    description: "Geselecteerde research items met snippet, rationale en invalshoek",
    input_schema: {
      type: "object",
      properties: {
        items: {
          type: "array",
          items: {
            type: "object",
            properties: {
              id: { type: "string" },
              title: { type: "string" },
              url: { type: "string" },
              snippet: { type: "string" },
              item_rationale: { type: "string" },
              invalshoek: { type: "string", enum: ["tegenkracht","bewijs","contra_intuitief","aanpalend","praktijk","tijd","systeem"] },
              stance: { type: "string", enum: ["bevestigt","nuanceert","spreekt_tegen"] },
              reliability: { type: "string", enum: ["low","medium","high"] }
            },
            required: ["id", "title", "snippet", "item_rationale", "invalshoek", "stance", "reliability"]
          }
        }
      },
      required: ["items"]
    }
  }],
  tool_choice: { type: "tool", name: "pack_research_items" }
}) }}"""

nodes = [
    {
        "parameters": {
            "httpMethod": "POST",
            "path": CE02_WEBHOOK_PATH,
            "responseMode": "onReceived",
            "options": {},
        },
        "id": "ce02-webhook",
        "name": "Webhook Trigger",
        "type": "n8n-nodes-base.webhook",
        "typeVersion": 2,
        "position": [400, 400],
        "webhookId": f"{CE02_WEBHOOK_PATH}-webhook",
    },
    {
        "parameters": {"jsCode": PARSE_JS},
        "id": "ce02-parse",
        "name": "Parse Input",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [620, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "CREATE TABLE IF NOT EXISTS research_items (\n  id CHAR(36) NOT NULL PRIMARY KEY,\n  theme_id CHAR(36) NULL,\n  slot_id CHAR(36) NULL,\n  source_id CHAR(36) NULL,\n  source_type VARCHAR(50) DEFAULT 'rss',\n  title VARCHAR(500) NOT NULL,\n  snippet TEXT NULL,\n  url VARCHAR(500) NULL,\n  item_rationale TEXT NULL,\n  feedback VARCHAR(20) DEFAULT 'none',\n  engagement_likes INT NULL,\n  engagement_comments INT NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  KEY idx_theme (theme_id),\n  KEY idx_slot (slot_id)\n);\nALTER TABLE research_items MODIFY COLUMN source_type VARCHAR(50) DEFAULT 'rss';\nCREATE TABLE IF NOT EXISTS newsletter_items (\n  id CHAR(36) NOT NULL PRIMARY KEY,\n  source_id CHAR(36) NOT NULL,\n  client_id CHAR(36) NOT NULL,\n  subject VARCHAR(500) NOT NULL,\n  body_text MEDIUMTEXT NULL,\n  received_at DATETIME NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  KEY idx_source (source_id),\n  KEY idx_client (client_id)\n);\nCREATE TABLE IF NOT EXISTS scrape_runs (\n  id CHAR(36) NOT NULL PRIMARY KEY,\n  source_id CHAR(36) NOT NULL,\n  client_id CHAR(36) NULL,\n  theme_id CHAR(36) NULL,\n  slot_id CHAR(36) NULL,\n  status VARCHAR(30) NOT NULL DEFAULT 'ok',\n  units_used DECIMAL(10,4) NULL,\n  cost_eur DECIMAL(10,4) NULL,\n  items_count INT NULL,\n  apify_run_id VARCHAR(100) NULL,\n  message TEXT NULL,\n  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n  KEY idx_source_created (source_id, created_at),\n  KEY idx_client_created (client_id, created_at)\n)",
            "options": {},
        },
        "id": "ce02-ensure-table",
        "name": "Ensure Research Table",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [840, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT\n  COALESCE(t.id, th2.id) AS theme_id,\n  CASE WHEN NULLIF($2, '') IS NOT NULL THEN $2 ELSE NULL END AS slot_id,\n  COALESCE(t.title, th2.title, s.theme) AS theme_title,\n  COALESCE(t.motivation, '') AS motivation,\n  COALESCE(t.rationale, s.goal, '') AS theme_rationale,\n  COALESCE(t.client_id, s.client_id) AS client_id,\n  c.name AS client_name,\n  c.archetype,\n  c.adversary,\n  c.brand_goal,\n  COALESCE(c.max_research_items_per_theme, 8) AS max_items,\n  COALESCE(t.title, s.theme, s.search_terms) AS search_label,\n  COALESCE(NULLIF(s.objective_id, ''), NULLIF(t.objective_id, ''), NULLIF(th2.objective_id, '')) AS objective_id,\n  obj.source_folder_id AS objective_source_folder_id\nFROM clients c\nLEFT JOIN themes t ON t.id = NULLIF($1, '') AND t.client_id = c.id\nLEFT JOIN content_slots s ON s.id = NULLIF($2, '') AND s.client_id = c.id\nLEFT JOIN themes th2 ON th2.id = s.theme_id\nLEFT JOIN objectives obj ON obj.id = COALESCE(NULLIF(s.objective_id, ''), NULLIF(t.objective_id, ''), NULLIF(th2.objective_id, ''))\n  AND obj.client_id = COALESCE(t.client_id, s.client_id)\nWHERE (NULLIF($1, '') IS NOT NULL AND t.id IS NOT NULL)\n   OR (NULLIF($2, '') IS NOT NULL AND s.id IS NOT NULL)\nLIMIT 1",
            "options": {
                "queryReplacement": "={{ [ $('Parse Input').item.json.theme_id, $('Parse Input').item.json.slot_id ] }}"
            },
        },
        "id": "ce02-load-context",
        "name": "Load Context",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1060, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "jsCode": "const row = $input.first()?.json ?? {};\nif (!row.client_id) {\n  throw new Error('Theme or slot not found');\n}\nreturn { json: row };"
        },
        "id": "ce02-validate-context",
        "name": "Validate Context",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1280, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT DISTINCT ri.invalshoek AS invalshoek\nFROM research_items ri\nLEFT JOIN themes t ON t.id = ri.theme_id\nLEFT JOIN content_slots s ON s.id = ri.slot_id\nWHERE ri.invalshoek IS NOT NULL\n  AND TRIM(ri.invalshoek) != ''\n  AND ri.created_at > DATE_SUB(NOW(), INTERVAL 6 WEEK)\n  AND COALESCE(t.client_id, s.client_id) = $1",
            "options": {
                "queryReplacement": "={{ [ $('Validate Context').item.json.client_id ] }}"
            },
        },
        "id": "ce02-load-recent-invalshoeken",
        "name": "Load Recent Invalshoeken",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1390, 400],
        "credentials": MYSQL_CRED,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": MERGE_CONTEXT_JS},
        "id": "ce02-merge-context",
        "name": "Merge Context",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [1500, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "DELETE FROM research_items WHERE COALESCE(source_type, '') != 'manual_fastlane' AND (($1 != '' AND theme_id = $1) OR ($2 != '' AND slot_id = $2))",
            "options": {
                "queryReplacement": "={{ [ $('Merge Context').item.json.theme_id || '', $('Merge Context').item.json.slot_id || '' ] }}"
            },
        },
        "id": "ce02-clear-old",
        "name": "Clear Old Research",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1610, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT id, title, url, category, source_kind, trello_board_id, asana_project_gid, drive_file_url, max_units_per_run, folder_id\nFROM sources\nWHERE client_id = $1 AND COALESCE(active, 1) = 1\n  AND (\n    (category = 'url_rss' AND url IS NOT NULL AND url != '')\n    OR (category = 'youtube' AND url IS NOT NULL AND url != '')\n    OR (category = 'blogs' AND url IS NOT NULL AND url != '')\n    OR (category = 'document' AND document_chunk_count IS NOT NULL AND document_chunk_count > 0)\n    OR (category = 'trello' AND trello_board_id IS NOT NULL AND trello_board_id != '')\n    OR (category = 'asana' AND asana_project_gid IS NOT NULL AND asana_project_gid != '')\n    OR (category = 'drive' AND drive_file_url IS NOT NULL AND drive_file_url != '')\n    OR (category IN ('scraper', 'linkedin') AND scraper_api_token_ref IS NOT NULL AND scraper_api_token_ref != '')\n    OR (category = 'linkedin' AND scraper_linkedin_profile IS NOT NULL AND scraper_linkedin_profile != '')\n  )\nORDER BY\n  CASE WHEN NULLIF($2, '') IS NOT NULL AND folder_id = $2 THEN 0 ELSE 1 END,\n  category ASC, title ASC\nLIMIT 20",
            "options": {
                "queryReplacement": "={{ [ $('Merge Context').item.json.client_id, $('Merge Context').item.json.objective_source_folder_id || '' ] }}"
            },
        },
        "id": "ce02-get-sources",
        "name": "Get Sources",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1720, 400],
        "credentials": MYSQL_CRED,
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "SELECT ni.subject AS title, ni.body_text AS contentSnippet, ni.source_id, s.title AS source_title\nFROM newsletter_items ni\nINNER JOIN sources s ON s.id = ni.source_id AND s.category = 'mailbox'\nWHERE s.client_id = $1\n  AND COALESCE(s.active, 1) = 1\nORDER BY ni.received_at DESC\nLIMIT 15",
            "options": {
                "queryReplacement": "={{ [ $('Validate Context').item.json.client_id ] }}"
            },
        },
        "id": "ce02-get-newsletters",
        "name": "Get Newsletter Items",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [1940, 400],
        "credentials": MYSQL_CRED,
        "continueOnFail": True,
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": PREPARE_FEEDS_JS},
        "id": "ce02-prepare-feeds",
        "name": "Prepare Feed URLs",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2160, 400],
    },
    {
        "parameters": {"jsCode": FETCH_RSS_JS},
        "id": "ce02-fetch-rss",
        "name": "Fetch Source Items",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2380, 400],
        "alwaysOutputData": True,
    },
    {
        "parameters": {"jsCode": COLLECT_CANDIDATES_JS},
        "id": "ce02-collect",
        "name": "Collect Candidates",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [2600, 400],
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
            "jsonBody": CLAUDE_RESEARCH_JSON_BODY,
            "options": {},
        },
        "id": "ce02-claude",
        "name": "Claude Research Pack",
        "type": "n8n-nodes-base.httpRequest",
        "typeVersion": 4.1,
        "position": [2600, 400],
        "credentials": CLAUDE_CRED,
    },
    {
        "parameters": {"jsCode": PREPARE_INSERTS_JS},
        "id": "ce02-prepare-inserts",
        "name": "Prepare Inserts",
        "type": "n8n-nodes-base.code",
        "typeVersion": 2,
        "position": [3040, 400],
    },
    {
        "parameters": {
            "operation": "executeQuery",
            "query": "INSERT INTO research_items (id, theme_id, slot_id, source_id, source_type, title, snippet, url, item_rationale, invalshoek, stance, reliability, feedback)\nVALUES ($1, NULLIF($2, ''), NULLIF($3, ''), NULLIF($4, ''), $5, $6, $7, NULLIF($8, ''), $9, NULLIF($10, ''), NULLIF($11, ''), NULLIF($12, ''), 'none')",
            "options": {
                "queryReplacement": "={{ [ $json.id, $json.theme_id || '', $json.slot_id || '', $json.source_id || '', $json.source_type, $json.title, $json.snippet, $json.url || '', $json.item_rationale, $json.invalshoek || '', $json.stance || '', $json.reliability || '' ] }}"
            },
        },
        "id": "ce02-insert",
        "name": "Insert Research Item",
        "type": "n8n-nodes-base.mySql",
        "typeVersion": 2.1,
        "position": [3260, 400],
        "credentials": MYSQL_CRED,
    },
]

connections = {
    "Webhook Trigger": {"main": [[{"node": "Parse Input", "type": "main", "index": 0}]]},
    "Parse Input": {"main": [[{"node": "Ensure Research Table", "type": "main", "index": 0}]]},
    "Ensure Research Table": {"main": [[{"node": "Load Context", "type": "main", "index": 0}]]},
    "Load Context": {"main": [[{"node": "Validate Context", "type": "main", "index": 0}]]},
    "Validate Context": {"main": [[{"node": "Load Recent Invalshoeken", "type": "main", "index": 0}]]},
    "Load Recent Invalshoeken": {"main": [[{"node": "Merge Context", "type": "main", "index": 0}]]},
    "Merge Context": {"main": [[{"node": "Clear Old Research", "type": "main", "index": 0}]]},
    "Clear Old Research": {"main": [[{"node": "Get Sources", "type": "main", "index": 0}]]},
    "Get Sources": {"main": [[{"node": "Get Newsletter Items", "type": "main", "index": 0}]]},
    "Get Newsletter Items": {"main": [[{"node": "Prepare Feed URLs", "type": "main", "index": 0}]]},
    "Prepare Feed URLs": {"main": [[{"node": "Fetch Source Items", "type": "main", "index": 0}]]},
    "Fetch Source Items": {"main": [[{"node": "Collect Candidates", "type": "main", "index": 0}]]},
    "Collect Candidates": {"main": [[{"node": "Claude Research Pack", "type": "main", "index": 0}]]},
    "Claude Research Pack": {"main": [[{"node": "Prepare Inserts", "type": "main", "index": 0}]]},
    "Prepare Inserts": {"main": [[{"node": "Insert Research Item", "type": "main", "index": 0}]]},
    "Insert Research Item": {"main": [[]]},
}

workflow = {
    "name": "CE-02 (Research & Inspiratie)",
    "nodes": nodes,
    "connections": connections,
    "settings": {"executionOrder": "v1"},
    "active": False,
    "pinData": {
        "Webhook Trigger": [
            {
                "json": {
                    "headers": {
                        "content-type": "application/json",
                    },
                    "params": {},
                    "query": {},
                    "body": {
                        "slot_id": CE02_PIN_SLOT_ID,
                    },
                },
            },
        ],
    },
}

out = Path(__file__).resolve().parent.parent.parent / "JSON" / "CE-02 - Research & Inspiratie.json"
sys.path.insert(0, str(Path(__file__).resolve().parent))
from n8n_workflow_utils import write_workflow

fetch_rss_js = (
    FETCH_RSS_JS.replace("__CE_PUBLIC_BASE_URL__", js_string(CE_PUBLIC_BASE_URL))
    .replace("__CE_RESET_TOKEN__", js_string(CE_RESET_TOKEN))
    .replace("/webhook/ce-trello-cards?", f"/webhook/{TRELLO_CARDS_WEBHOOK_PATH}?")
)
if CE_RESET_TOKEN == "":
    print("WARNING: CE_RESET_TOKEN not set — CE-02 scraper calls will be skipped", file=sys.stderr)

for node in nodes:
    if node.get("name") == "Fetch Source Items":
        node["parameters"]["jsCode"] = fetch_rss_js
    if node.get("name") == "Claude Research Pack":
        body = node["parameters"]["jsonBody"]
        node["parameters"]["jsonBody"] = patch_json_body_system_prompt(body)

write_workflow(workflow, out)
print("Wrote", out)
