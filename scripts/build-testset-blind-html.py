#!/usr/bin/env python3
"""Build web/testset-nulmeting/index.html from docs/testset/stap1-nulmeting/*.md (blind codes)."""
from __future__ import annotations

import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
MD_DIR = ROOT / "docs/testset/stap1-nulmeting"
OUT_DIR = ROOT / "web/testset-nulmeting"
OUT_FILE = OUT_DIR / "index.html"

STAP = "1-nulmeting"
SCORERS = ["Erik", "Rogier", "Eva"]

SCORE_QUESTIONS: list[tuple[str, str]] = [
    ("q_clear_claim", "Is er één duidelijke bewering?"),
    ("q_concrete", "Is die onderbouwd met iets concreets?"),
    ("q_brand", "Is het herkenbaar dit merk?"),
    ("q_dutch", "Is het natuurlijk Nederlands?"),
    ("q_would_post", "Zou ik dit posten?"),
]


def build_score_form(code: str) -> str:
    scorer_options = "".join(
        f'<option value="{html.escape(name)}">{html.escape(name)}</option>'
        for name in SCORERS
    )
    rows = []
    for qkey, qlabel in SCORE_QUESTIONS:
        radios = "".join(
            f'<label class="radio-pill"><input type="radio" name="{html.escape(qkey)}" '
            f'value="{n}" required /> {n}</label>'
            for n in range(1, 6)
        )
        rows.append(
            f'<fieldset class="q-row">'
            f'<legend>{html.escape(qlabel)}</legend>'
            f'<div class="radios">{radios}</div>'
            f"</fieldset>"
        )
    return f"""
<form class="score-form" data-code="{html.escape(code)}" novalidate>
  <h3 class="form-title">Jouw score</h3>
  <p class="form-hint">Kies je naam — ieder vult elke code één keer in (1 = zwak, 5 = sterk).</p>
  <label class="scorer-label">Wie ben jij?
    <select name="scorer" required>
      <option value="">— kies —</option>
      {scorer_options}
    </select>
  </label>
  <div class="status-line" aria-live="polite"></div>
  {''.join(rows)}
  <label class="notes-label">Opmerking (optioneel)
    <textarea name="notes" rows="2" maxlength="2000" placeholder="Korte toelichting…"></textarea>
  </label>
  <button type="submit" class="submit-btn">Score opslaan</button>
  <p class="form-msg" hidden></p>
</form>"""


def md_body_to_html(text: str) -> str:
    blocks = re.split(r"\n\n+", text.strip())
    parts: list[str] = []
    for block in blocks:
        block = block.strip()
        if not block:
            continue
        if block.startswith("---"):
            parts.append("<hr />")
            continue
        if "**" in block:
            block = re.sub(
                r"\*\*(.+?)\*\*",
                lambda m: f"<strong>{html.escape(m.group(1))}</strong>",
                block,
            )
        else:
            block = html.escape(block)
        parts.append(f"<p>{block.replace(chr(10), '<br />')}</p>")
    return "\n".join(parts)


def parse_md(path: Path) -> tuple[str, str, str, str]:
    raw = path.read_text(encoding="utf-8")
    code = path.stem
    headline = ""
    body = ""
    self_review = ""
    section = None
    buf: list[str] = []
    for line in raw.splitlines():
        if line.startswith("## Headline"):
            if section == "body":
                body = "\n".join(buf).strip()
            elif section == "self_review":
                self_review = "\n".join(buf).strip()
            section = "headline"
            buf = []
            continue
        if line.startswith("## Tekst"):
            if section == "headline":
                headline = "\n".join(buf).strip()
            section = "body"
            buf = []
            continue
        if line.startswith("## self_review"):
            if section == "body":
                body = "\n".join(buf).strip()
            section = "self_review"
            buf = []
            continue
        if line.startswith("# ") or line.startswith("<!--"):
            continue
        if section:
            buf.append(line)
    if section == "body":
        body = "\n".join(buf).strip()
    elif section == "self_review":
        self_review = "\n".join(buf).strip()
    return code, headline, body, self_review


def main() -> None:
    md_files = sorted(MD_DIR.glob("*.md"))
    if not md_files:
        raise SystemExit(f"Geen .md in {MD_DIR}")

    articles: list[tuple[str, str, str, str]] = []
    for path in md_files:
        if path.name in ("README.md",):
            continue
        articles.append(parse_md(path))
    articles.sort(key=lambda x: x[0])

    sections_html: list[str] = []
    for code, headline, body, self_review in articles:
        sections_html.append(
            f"""
<section class="article" id="{html.escape(code)}">
  <p class="code">{html.escape(code)}</p>
  <h2>{html.escape(headline)}</h2>
  <div class="body">{md_body_to_html(body)}</div>
  <details class="self-review">
    <summary>Aandachtspunten (self_review) — optioneel meelezen</summary>
    <div>{md_body_to_html(self_review) if self_review.strip() else "<p><em>(leeg)</em></p>"}</div>
  </details>
  {build_score_form(code)}
</section>"""
        )

    nav = "".join(
        f'<a href="#{html.escape(c)}">{html.escape(c)}</a>' for c, _, _, _ in articles
    )

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    OUT_FILE.write_text(
        f"""<!DOCTYPE html>
<html lang="nl">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Testset nulmeting — blind lezen</title>
  <style>
    :root {{
      --bg: #f5f0e8;
      --text: #3d3429;
      --accent: #0066cc;
      --muted: #6b6358;
    }}
    * {{ box-sizing: border-box; }}
    body {{
      margin: 0;
      font-family: "Figtree", system-ui, sans-serif;
      background: var(--bg);
      color: var(--text);
      line-height: 1.55;
      font-size: 17px;
    }}
    .wrap {{ max-width: 42rem; margin: 0 auto; padding: 2rem 1.25rem 4rem; }}
    h1 {{ font-size: 1.35rem; font-weight: 600; margin: 0 0 0.5rem; }}
    .intro {{ color: var(--muted); margin-bottom: 1.5rem; font-size: 0.95rem; }}
    nav {{
      display: flex; flex-wrap: wrap; gap: 0.5rem 1rem;
      margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid #ddd5c8;
    }}
    nav a {{ color: var(--accent); text-decoration: none; font-weight: 600; }}
    nav a:hover {{ text-decoration: underline; }}
    .article {{
      margin-bottom: 3rem; padding-bottom: 2rem; border-bottom: 1px solid #ddd5c8;
    }}
    .code {{
      font-size: 0.85rem; font-weight: 700; letter-spacing: 0.08em;
      color: var(--muted); margin: 0 0 0.35rem;
    }}
    h2 {{ font-size: 1.2rem; font-weight: 600; margin: 0 0 1rem; line-height: 1.35; }}
    .body p {{ margin: 0 0 1rem; }}
    .self-review {{
      margin-top: 1.25rem; font-size: 0.92rem; color: var(--muted);
    }}
    .score-form {{
      margin-top: 1.5rem; padding: 1.25rem; background: #ebe4d9; border-radius: 8px;
      font-size: 0.95rem;
    }}
    .form-title {{ margin: 0 0 0.35rem; font-size: 1.05rem; }}
    .form-hint {{ margin: 0 0 1rem; color: var(--muted); font-size: 0.88rem; }}
    .scorer-label {{ display: block; font-weight: 600; margin-bottom: 1rem; }}
    .scorer-label select {{
      display: block; margin-top: 0.35rem; font: inherit; padding: 0.4rem 0.5rem;
      min-width: 10rem; border-radius: 4px; border: 1px solid #ccc;
    }}
    .q-row {{ border: 0; margin: 0 0 0.85rem; padding: 0; }}
    .q-row legend {{ font-weight: 500; margin-bottom: 0.35rem; padding: 0; }}
    .radios {{ display: flex; flex-wrap: wrap; gap: 0.35rem 0.5rem; }}
    .radio-pill {{
      display: inline-flex; align-items: center; gap: 0.25rem;
      background: #f5f0e8; padding: 0.25rem 0.45rem; border-radius: 4px; cursor: pointer;
    }}
    .notes-label {{ display: block; margin: 0.75rem 0; font-weight: 500; }}
    .notes-label textarea {{
      display: block; width: 100%; margin-top: 0.35rem; font: inherit; padding: 0.5rem;
      border-radius: 4px; border: 1px solid #ccc; resize: vertical;
    }}
    .submit-btn {{
      font: inherit; font-weight: 600; padding: 0.55rem 1.1rem;
      background: var(--accent); color: #fff; border: 0; border-radius: 6px; cursor: pointer;
    }}
    .submit-btn:disabled {{ opacity: 0.55; cursor: wait; }}
    .form-msg {{ margin: 0.75rem 0 0; font-weight: 600; }}
    .form-msg.ok {{ color: #1a6b3c; }}
    .form-msg.err {{ color: #a32; }}
    .status-line {{ font-size: 0.85rem; color: var(--muted); min-height: 1.2em; margin-bottom: 0.5rem; }}
    .status-line .done {{ color: #1a6b3c; font-weight: 600; }}
  </style>
</head>
<body>
  <div class="wrap">
    <h1>Testset — nulmeting (stap 1)</h1>
    <p class="intro">
      Alleen codes (geen tenant of slot). <strong>Erik, Rogier en Eva:</strong> vul onder elk artikel je score in.
      Open pas daarna docs/testset/sleutel.md. Dit is géén Agenda.
    </p>
    <nav aria-label="Codes">{nav}</nav>
    {"".join(sections_html)}
  </div>
  <script>
(function () {{
  const STAP = {STAP!r};
  const API = '/api/testset-nulmeting/scores?stap=' + encodeURIComponent(STAP);
  const SCORERS = {SCORERS!r};
  const QKEYS = {json.dumps([k for k, _ in SCORE_QUESTIONS])};

  let entries = [];

  function entryKey(code, scorer) {{
    return code + ':' + scorer;
  }}

  function mapEntries(list) {{
    const m = new Map();
    for (const e of list) {{
      m.set(entryKey(e.code, e.scorer), e);
    }}
    return m;
  }}

  function fillForm(form, entry) {{
    if (!entry) return;
    const sel = form.querySelector('[name=scorer]');
    if (sel) sel.value = entry.scorer;
    for (const k of QKEYS) {{
      const v = entry.scores && entry.scores[k];
      if (!v) continue;
      const radio = form.querySelector('[name="' + k + '"][value="' + v + '"]');
      if (radio) radio.checked = true;
    }}
    const notes = form.querySelector('[name=notes]');
    if (notes && entry.notes) notes.value = entry.notes;
  }}

  function updateStatusLines(map) {{
    document.querySelectorAll('.score-form').forEach((form) => {{
      const code = form.dataset.code;
      const line = form.querySelector('.status-line');
      const scorer = form.querySelector('[name=scorer]')?.value;
      if (!line) return;
      const parts = SCORERS.filter((s) => map.has(entryKey(code, s))).map(
        (s) => '<span class="done">' + s + ' ✓</span>'
      );
      line.innerHTML = parts.length
        ? 'Ingevuld: ' + parts.join(', ')
        : '';
      if (scorer && map.has(entryKey(code, scorer))) {{
        fillForm(form, map.get(entryKey(code, scorer)));
      }}
    }});
  }}

  async function loadScores() {{
    const res = await fetch(API);
    const data = await res.json();
    if (!data.ok) throw new Error('Laden mislukt');
    entries = data.entries || [];
    updateStatusLines(mapEntries(entries));
  }}

  document.querySelectorAll('.score-form').forEach((form) => {{
    form.querySelector('[name=scorer]')?.addEventListener('change', () => {{
      const code = form.dataset.code;
      const scorer = form.querySelector('[name=scorer]').value;
      const hit = entries.find((e) => e.code === code && e.scorer === scorer);
      form.querySelectorAll('input[type=radio]').forEach((r) => {{ r.checked = false; }});
      const notes = form.querySelector('[name=notes]');
      if (notes) notes.value = '';
      if (hit) fillForm(form, hit);
    }});

    form.addEventListener('submit', async (ev) => {{
      ev.preventDefault();
      const msg = form.querySelector('.form-msg');
      const btn = form.querySelector('.submit-btn');
      const code = form.dataset.code;
      const scorer = form.querySelector('[name=scorer]').value;
      if (!scorer) {{
        if (msg) {{ msg.hidden = false; msg.className = 'form-msg err'; msg.textContent = 'Kies eerst je naam.'; }}
        return;
      }}
      const scores = {{}};
      for (const k of QKEYS) {{
        const checked = form.querySelector('[name="' + k + '"]:checked');
        if (!checked) {{
          if (msg) {{ msg.hidden = false; msg.className = 'form-msg err'; msg.textContent = 'Vul alle vijf vragen in (1–5).'; }}
          return;
        }}
        scores[k] = Number(checked.value);
      }}
      const notes = (form.querySelector('[name=notes]')?.value || '').trim();
      btn.disabled = true;
      if (msg) msg.hidden = true;
      try {{
        const res = await fetch('/api/testset-nulmeting/scores', {{
          method: 'POST',
          headers: {{ 'Content-Type': 'application/json' }},
          body: JSON.stringify({{ stap: STAP, code, scorer, scores, notes }}),
        }});
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || data.message || 'Opslaan mislukt');
        await loadScores();
        if (msg) {{
          msg.hidden = false;
          msg.className = 'form-msg ok';
          msg.textContent = 'Opgeslagen voor ' + scorer + ' — code ' + code + '.';
        }}
      }} catch (err) {{
        if (msg) {{
          msg.hidden = false;
          msg.className = 'form-msg err';
          msg.textContent = err.message || 'Opslaan mislukt';
        }}
      }} finally {{
        btn.disabled = false;
      }}
    }});
  }});

  loadScores().catch(() => {{
    document.querySelectorAll('.status-line').forEach((el) => {{
      el.textContent = 'Scores laden mislukt — ververs de pagina.';
    }});
  }});
}})();
  </script>
</body>
</html>
""",
        encoding="utf-8",
    )
    print(f"Wrote {OUT_FILE} ({len(articles)} artikelen)")


if __name__ == "__main__":
    main()
