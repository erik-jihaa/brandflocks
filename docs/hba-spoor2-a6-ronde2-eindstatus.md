# Spoor 2 — A6 ronde 2 (eindstatus)

**Briefing:** `briefing/cursor-ronde-7-v113-en-a6-ronde-2.md`  
**Datum:** 20 oktober 2026  
**Scoring:** niet geraakt (weergave + CE-04 + bewijs).

| Punt | Rogier-criterium | Status | Bewijs |
| --- | --- | --- | --- |
| **A** | nl-NL op Agenda (lijst + kalender); geen Engelse maanden | **Klaar** (kop `—` na deploy `AgendaPage`) | `docs/a6-screenshots-staging-2026-10-20/11-agenda-lijst.png`, `12-agenda-kalender.png`; code `localeDates.ts`, `agendaDates.ts`, `mappers.php`; test `php scripts/test-ce-agenda-nl-dates.php` |
| **B** | CE-04 regel letterlijk, 3/3 merken | **Klaar** (na herimport v1.13) | `docs/a6-screenshots-staging-2026-10-20/07-ce04-post3-poll.json` (`regelFeedbackOk: true` ×3, `promptVersion: v1.13`); screenshot oude slot — **vernieuwen** na deploy: `/article/b9f67061-c2e5-43db-8951-823decb25c78/feedback` |
| **C** | Hashtag `#versgebrand` in score/CE-03 | **Klaar** | Hashtags in gescoorde posttekst (`postTextIncludesHashtagLine`); JSON `08-hashtag-bewijs-brandpunt-categorietaal.json` (`bewijsOk: true`); fragment + `docs/hba-a6-ronde2-stap-C-hashtag-bewijs.md` |

## A — overige schermen

Overzicht, Rapport, Archief en Planning gebruiken geen Engelse **maandnamen** in de UI (weeklabels blijven `Week N`). Archief/rapport-datums via `formatDayMonthYearLong` / `formatArchiveSearchDate`.

## B — herimport 20 okt (avond)

HBA-testposts opnieuw geïmporteerd voor v1.13 → **nieuwe slot-ids**. CE-04 opnieuw getriggerd (`./scripts/run-hba-r0-ce04-post3-staging.sh 90`); poll opgeslagen.

## Open voor zip naar Rogier (spoor 3)

- Screenshot CE-04 op **nieuw** Brandpunt-slot (B).
- Optioneel: agenda-screenshots opnieuw na kop `Agenda —` (kleine UI-fix).
- Bundel met `hba-a6-ronde2-stap-A-B.md`, `-C-`, stopbericht-map.
