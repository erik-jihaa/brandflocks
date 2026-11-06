# HBA v1.12 — stap 3b (productiebevindingen 20 okt)

Referentie-artikel: `44190153-c336-4666-9324-5c173a76f786` (iGiSNext tenant, week 44).  
Briefing: `briefing/cursor-ronde-6-afronden-en-stoppen.md` §3b.

## 3b-1 — Ontbrekende merkbeoordeling

### Oorzaak (code)

CE-03 roept na **Insert Article** `POST /api/hba/score` aan met `{ slot_id }` (system token).  
De resolver in `web/api/hba.php` koos het artikel met **`ORDER BY updated_at DESC` alleen**, zonder voorkeur voor rijen met `content_ai`.

Bij **Snel artikel / fastlane** kan een lege stub en de CE-03-rij naast elkaar bestaan (kort). De stub kon dan als “nieuwste” worden gescoord → **404 Geen posttekst** (RuntimeException). De UI toont wél het primaire artikel (`ce_fetch_article_by_slot` sorteert wél op gevulde `content_ai`), dus: **zelfcontrole + hint op het goede artikel, geen score in het paneel**.

### Fix

- `ce_fetch_primary_article_row_for_slot()` — zelfde keuze als Article-UI (consolidate + content-pref + order).
- `hba.php` gebruikt die helper voor CE-03/system calls.
- **Back-up:** `ArticlePage` start één keer `POST /hba/score` als er tekst is, architectuur compleet is, en er nog geen score is (na mislukte CE-03-hook).

### Diagnose op productie (handmatig)

1. **Fastlane?**  
   `SELECT origin FROM content_slots WHERE id = (SELECT slot_id FROM articles WHERE id = '44190153-c336-4666-9324-5c173a76f786');`  
   `origin = 'fastlane'` → zelfde pad als Duinroos V0.

2. **Score ontbreekt vs verkeerde post_id?**  
   `SELECT id, post_id, status, scored_at FROM hba_scores WHERE post_id = '44190153-c336-4666-9324-5c173a76f786';`

3. **Achterstand tellen** (zelfcontrole, geen score op huidige narrative):  
   Query via `scripts/hba-count-missing-scores.php production` (na deploy).

4. **Event log CE-03 score:**  
   Zoek op `endpoint` / `hba` rond `scored_at` van het artikel; 404 op `/api/hba/score` bevestigt de hook-fout.

## 3b-2 — Methodiektaal in self_review

### Fix

- `web/api/self-review-verbodslijst.php` — termen incl. `stance`, `reliability`, `spreekt_tegen`, enum-waarden.
- CE-03 stap D: retry + strip + `event_log` `self_review_verbodslijst` (zelfde patroon als architectuur).
- `ce_parse_self_review()` filtert bestaande JSON bij **API-lees** (direct effect op productie-artikelen zonder CE-03-rerun).

### Klaar als

- Geen klantzichtbaar aandachtspunt meer met veldnamen op referentie-artikel (na deploy + refresh).
- Nieuwe CE-03-runs houden verbodslijst aan.
