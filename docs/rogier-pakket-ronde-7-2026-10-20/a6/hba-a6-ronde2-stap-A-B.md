# A6 ronde 2 — stap A + B (20 okt 2026)

Bron: `briefing/hba-input/hba-a6-restpunten-ronde-2-2026-10-20.md`

## A — Agenda nl-NL

**Gedaan (code):**
- PHP: `ce_today_agenda_label`, `ce_week_range_label` → Nederlandse maanden (`web/api/mappers.php`).
- Frontend: `frontend/src/utils/localeDates.ts`; `agendaDates.ts` week/kop; Agenda gebruikt client-format i.p.v. API-EN.
- Archief: `archiveDates.ts`, `ArchiveArticlePage`, `ArchivePostPage` → nl.

**Test:** `php scripts/test-ce-agenda-nl-dates.php`

**Staging 20 okt (avond):** deploy `index-C5jw7aEA.js`; screenshots ververst in `docs/a6-screenshots-staging-2026-10-20/` (lijst + kalender + terugkoppeling). Spot-check Overzicht/Rapport/Archief/Planning: geen Engelse maandnamen gezien.

## B — CE-04 regel letterlijk

**Gedaan (code):**
- Prompt + Build Feedback Rows: `scripts/generate-ce04-workflow.py` (regel tussen aanhalingstekens + uitleg met citaat).
- PHP helpers: `ce_hba_resolve_literal_brand_regel`, `ce_hba_ce04_regelbreuk_comment` in `hba-hint-verificatie.php`.
- Admin CE-04 post3 herkent nieuw comment-prefix.

**Test:** `php scripts/test-ce04-regel-letterlijk.php`

**Staging 20 okt (avond, na v1.13-herimport):** post3 opnieuw getriggerd — **3/3** `regelFeedbackOk`, `promptVersion: v1.13`. JSON: `docs/a6-screenshots-staging-2026-10-20/07-ce04-post3-poll.json`. Brandpunt feedback-slot: `b9f67061-c2e5-43db-8951-823decb25c78` (screenshot `07-feedback-terugkoppeling.png` nog op oud slot — vernieuwen voor zip).

**A — kop:** `AgendaPage` gebruikt ` — ` i.p.v. `_` (Rogier ronde 7); na deploy staging opnieuw screenshotten.

**C — hashtag-bewijs:** `docs/hba-a6-ronde2-stap-C-hashtag-bewijs.md`, JSON in `docs/a6-screenshots-staging-2026-10-20/08-hashtag-bewijs-*.json`.

**D — stopbericht ronde 6:** `docs/hba-a6-ronde2-stap-D-stopbericht.md` → map `docs/rogier-stopbericht-ronde-6-2026-10-19/` (incl. `04-stabiliteit-v1.12.md` + JSON).
