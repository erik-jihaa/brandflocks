# HBA v1.10 — stap 4: klanttaal (§6) + A6-screenshots

Datum: 2026-10-17  
Bron: `briefing/hba-input/Voor Erik 2026-10-16/hba-besluiten-en-nulmeting-2026-10-16.md` §6; `briefing/hba-input/klanttaal-A6-beoordeling-rogier-2.md`

## Wat erin zit

1. **CE-04-knoppen:** `Remove` → **Verwijderen** (UI-normalisatie + CE-04 Build Feedback Rows; bestaande DB-rijen via API/frontend).
2. **Contentrollen** (§6.2) in Overzicht/Rapport/portfolio-signalen:

   | Intern | Klant ziet |
   | --- | --- |
   | positionering | Positionering |
   | bewijs | Bewijs |
   | educatie | Kennis delen |
   | activatie | Oproep tot actie |
   | community | Community |
   | service | Service |
   | employer_culture | Team en cultuur |
   | reactief | Reactie op nieuws |

3. **Hoofdoordeel:** labels ongewijzigd (Regelbreuk, Positiebreuk, …); klantzin Positiebreuk = *Hier redeneer je zoals je tegenstander.*
4. **A6 open rijen:**
   - Geen Jung-archetype meer in artikelmeta; toont **merkdoel** (`positioningBrandGoal`).
   - Merkelementen: BrandArchitecture/CE-03 → Merkarchitectuur / de schrijver.
   - Vindbaarheid: Content Engine / Report / SerpAPI weg; alleen schermnamen.
   - Adviesregel: *Past dit onderwerp bij je merk?*

**Niet meegenomen (bewust):** productnaam Brandflocks (§4 A6) — nog geen besluit; geen motornamen tonen.

## CE-04 n8n

`scripts/generate-ce04-workflow.py` schrijft naar `Content Engine/JSON/CE-04 - Review & Publish.json`.  
Na staging-deploy: `./scripts/push-ce04-n8n.sh` (staging) zodat nieuwe runs Verwijderen schrijven. UI toont Verwijderen al via normalisatie.

## A6-screenshotlijst voor Rogier

Elk scherm dat een pilotklant ziet — één screenshot per rij:

| # | Scherm | Route | Check |
| --- | --- | --- | --- |
| 1 | Overzicht | `/` | contentrollen NL; geen archetype-% |
| 2 | Merkarchitectuur | `/brandarchitecture` | geen HBA/CE-codes; persoonlijkheid i.p.v. archetype-label in UI |
| 3 | Merkelementen | `/styleguide` | link “Merkarchitectuur”; geen CE-03 |
| 4 | Thema’s | `/theme` | *Past dit onderwerp bij je merk?* |
| 5 | Onderzoek | `/research` | idem |
| 6 | Artikel (meta) | `/article/…` | Merkarchitectuur-sectie; merkdoel; hoofdoordeel-labels |
| 7 | Feedback | `/article/…/feedback` | knop **Verwijderen** (niet Remove) |
| 8 | Vindbaarheid | `/search` | geen Content Engine / SerpAPI |
| 9 | Planning | `/planning` | NL |
| 10 | Rapport | `/report` | contentrollen NL; Rapport i.p.v. Report |
| 11 | Agenda-signalen | `/agenda` | actielabel Rapport / Merkarchitectuur |

**Klaar als:** Rogier deze screenshots in één keer goedkeurt.

**Bouwrapport (17 okt 2026):** `docs/hba-v1.10-bouwrapport-2026-10-17.md` §5 — alle 11 PNG’s ingebed + checklist. Bestanden: `docs/a6-screenshots-staging-2026-10-17/`.

## Handmatig na deploy

1. Staging-smoke.
2. Push CE-04-workflow naar staging-n8n.
3. Screenshots → Rogier (via bouwrapport §5).
