# CE-04 stap 0 — regelbreuk in review (ronde 2)

**Datum:** 9 oktober 2026  
**Context:** Post 3 (breekt de regel) kreeg bij HBA-score wel `constraint_breach`, maar CE-04 schreef alleen de fallback *Geen specifieke feedback.*

## Diagnose

1. **Prompt en merkregels** — Validate Article vult `regels_merk` uit geloofwaardigheidstest + verboden logica; de gedeelde REGELBREUK-definitie staat in de systemprompt (via `generate-ce04-workflow.py`). Geen aanwijzing dat post 3 zonder regels werd gereviewd.
2. **Waarschijnlijke oorzaak** — In **Build Feedback Rows** werd tool-input alleen gelezen als `claude.content[0].input`. Het Anthropic-antwoord gebruikt `type: "tool_use"` met `input` op dat block; als het eerste content-block geen tool_use is, blijft `input` leeg → geen `regelbreuk`-rij → fallback met lege `summary` → *Geen specifieke feedback.*
3. **Niet geverifieerd in n8n UI** — Executions voor slots Brandpunt / Stelwerk / Duinroos (9 okt staging) zijn niet door Cursor ingezien. Erik kan in n8n controleren of `submit_review` wél `regelbreuk` vult en of Build Feedback Rows daarvóór leeg parsed.

## Herstel (code)

- **Robuuste parser** in `scripts/generate-ce04-workflow.py`: zoek `tool_use` met `name === 'submit_review'`, fallback naar legacy `content[0].input`.
- **Fallback-gedrag:** alleen *Geen specifieke feedback.* als de tool wél gelezen is maar geen regelbreuk/tekstpunt/summary; bij lege tool-output een expliciete foutregel `[CE-04] Review mislukt: tool-output niet gelezen`.
- **Workflow opnieuw genereren** en CE-04 naar staging deployen na Eriks *ja*.

## Klaar-criterium na deploy

CE-04 op post 3 per merk: feedback *Dit stuk gaat in tegen je regel dat …* met excerpt = citaat uit de tekst.
