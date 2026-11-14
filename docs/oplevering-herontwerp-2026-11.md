# Oplevering herontwerp — november 2026

## Opdracht 1 — addendum §3 (branch `addendum-s3-kanaaleisen`, PR #1)

### Al op `main` vóór deze branch (delta t.o.v. opdrachttekst 14 nov)

| Opdrachtregel | Stand op `main` |
|---------------|-----------------|
| Tabel letterlijk in PHP | `ce_channel_copy_constraint_catalog()` 13 rijen ≈ addendum §3 |
| `content_textvorm` schrijven + UI | PATCH `article-repository.php`; keuze in `ArticleMetaSidebar` |
| Harde telling tekens/hashtags | `ce_channel_hard_validation_*` + melding op artikel |
| Lichte check postvoorstel | `ce_channel_soft_hints_for_proposal` + copy-modus CTA-hint |
| `hba-open-koppelpunten.php` | Bestaat met null-catalogus (≠ kale comment-only uit opdracht) |

### Gebouwd op branch `addendum-s3-kanaaleisen`

- `GET /api/channel-constraints` — tekstvorm-labels/opties en `hardLimits` uit PHP (één bron).
- Frontend: `useChannelConstraints` + tekstvorm uit API; veld alleen bij >1 keuze per kanaal.
- `tests/php/kanaaleisen.php` + opname in `scripts/test-all.sh`.

### Open gelaten (bewust)

- Zachte structuur/toon-check (niet telbaar) — niet gebouwd (opdracht).
- Oproep-tot-actie vs copy-modus — geen voorrangsregel (B7); beide regels blijven in directive.
- Woord-richtlijnen alleen als soft hints, geen harde telling.

## Opdracht 2 — Navigatie (gemerged op `main`, PR #2)

### Nieuwe zijbalk (klikmodel stap 6)

| Groep | Items (volgorde) |
|---|---|
| Overzicht | Rapport · Te doen |
| Identiteit | Merkarchitectuur · Merktaal |
| Kennis | Bronnen · SEO/GEO · Beeldbank |
| Publicatie | Artikel · Campagne · Kalender · Alle artikelen |
| Instellingen | Planning · Mijn account · Koppelingen · Help · Uitloggen |

**Niet meer in de nav:** Thema's, Onderzoek, Snel artikel, Doelen, Rapport onder Publicatie. Routes blijven; broodkruimel op Onderzoek/Thema/Snel artikel (Publicatie › Artikel › …), `activeNav` = Artikel.

### Oud nav-label / pad → nieuw (routes ongewijzigd)

| Was | Nu (label) | Pad |
|---|---|---|
| Merkelementen | Merktaal | `/styleguide`, `/strategy` |
| Campagnes | Campagne | `/campagnes` |
| Agenda | Kalender | `/agenda` |
| Archief | Alle artikelen | `/archive` |
| Vindbaarheid (copy) | SEO/GEO | `/search` |
| Planning (onder Kennis) | Planning (Instellingen, eerste item) | `/planning` |
| Beeldbank (onder Publicatie) | Beeldbank (Kennis) | `/gallery` |
| Rapport (onder Publicatie) | Rapport (Overzicht) | `/report` |
| Thema's | — (via Artikel-flow) | `/theme`, `/themes/…` |
| Onderzoek | — (via Artikel-flow) | `/research`, … |
| Snel artikel | — (via Artikel-flow) | `/fastlane` |
| Doelen | — (nav-item weg) | `/targets` |

Geen URL-redirects toegevoegd: bestaande bookmarks werken.

### Doelen

Nav-item verwijderd. **`/targets` en API `/objectives` blijven.** `source-folders-repository.php` koppelt bronnenmappen nog aan `objective_id` — daarom geen verdere opschoning van Doelen in deze opdracht.

### Technisch

- `NavBreadcrumb.tsx`, optionele `breadcrumb` op `GridShell`.
- Klanttaal: Merktaal, Kalender, Campagne, Alle artikelen; archief-flow uit Engels naar NL.

## Vragen aan Rogier

- **Merktaal of Merkelementen:** klikmodel = Merktaal; voorstel 31 okt. / weekplan = Merkelementen. Gebouwd als **Merktaal**.
- **B7** Voorrang copy-modus vs kanaaltabel bij CTA.
- **Opdrachtveld `opdracht` per campagnefase** — klantzichtbaar? (leesronde B2-twijfel).
- **hba-open-koppelpunten** — catalogus met null-keys voldoende of strikt leeg bestand?

## Opdracht 3 — Kalender en Help (gemerged `main` PR #3)

### Gebouwd / aangescherpt

- Weergave **Overzicht | Lijst** (klikmodel-label Overzicht i.p.v. dubbele “Kalender”).
- **Kanaal- en maandfilter** in beide weergaven; tenant-kanalen uit agenda-data (niet hardcoded LI/IG).
- Lijst **Op campagne**: inklapbare campagne/thema-groepen (bestaand, `AgendaCampaignList`).
- Lijst **Op datum**: nieuw `AgendaDateList` — per dag, reeksnaam + post (één bron, zelfde filters).
- **Lege maand:** `Geen posts gepland in deze maand.` (klikmodel, zonder demo-zin).
- **Op campagne / Op datum** alleen in Lijst (zoals klikmodel).

### Vastlopers groepering/API (follow-up 3A)

- **Overzicht** negeert Lijst-`groupMode`: toont altijd alle posts + campagnalegenda (niet meer “Op campagne”-subset na toggle).
- Lege-maand op Overzicht telt **alle** gefilterde posts, niet alleen campagne.
- Kanaalknoppen = alleen platforms die in `/api/agenda` voorkomen; ongeldige selectie valt terug op Alle kanalen.

### Niet gebouwd (opdracht)

- **Campagnekleuren uit DB:** gebouwd in stap 8 PR 1. `campaigns.calendar_color` (0–3). Bestaande campagnes krijgen bij de eerste preview-request de kleur die `colorIndex` nu al toont: eerste keer dat de campagne in de agendarijen voorkomt (`phase_key`, `theme`, `id`), index `% 3`. Kleur 3 is alleen een nieuwe keuze. De legenda leest de opgeslagen kleur; zonder kleur blijft de oude rotatie.
- **Kanaalverschuiving addendum §5** — alleen datamodelnotitie hieronder.

### Datamodel — één artikel, meerdere kanalen (voor §5)

Elk kanaal is een **eigen `content_slots`-rij** met eigen `platform` / `publish_date` (en vaak eigen `article_id` per slot). Er is geen gedeeld slot met meerdere kanalen op één datum in het huidige schema; campagneplanning maakt meerdere slots (zie campagne-repositories).

### Deel B — Help (Sonnet-extract, 14 nov)

Letterlijke FAQ uit klikmodel `faqData` (10 onderwerpen, 102 vragen):

- `frontend/src/data/helpFaq.klikmodel.ts` — voor UI-wiring (Composer)
- `docs/help-faq-klikmodel-extract-2026-11-14.json` —zelfde data
- `docs/help-faq-mismatches-extract-2026-11-14.md` — antwoorden die de app (nog) niet doet / verouderde producten; **tekst niet aangepast**

**Help UI (14 nov):** `SupportPage` — onderwerp-pills + accordion (`HelpFaqSection`, data uit `HELP_FAQ`), los blok **Stel hier jouw vraag** (`HelpOwnQuestionForm`, escalate/mailto); oude Engelstalige bullet-FAQ verwijderd; kostenregel dichter bij klikmodel.

## Opdracht — weekplan stap 7 (Artikel-wizard)

### Gebouwd

- Nav **Artikel** → `/article` redirect naar **7-stappen-wizard** (`/article/nieuw/stap/1` … `7`), voortgangsbalk + klikmodel-copy.
- Stap 1: Eén / Meerdere / Ik heb het artikel al; eigen tekst → `Fastlane` met prefill.
- Stap 2: Thema + extra gedachten (draft `sessionStorage` v3).
- Stap 3–4: merkonderdelen, feitenbank, SEO/GEO-leespunt, hashtags; kanalen + LinkedIn posts/artikelen.
- Stap 5: invalshoeken per artikel (lokaal voorgesteld uit thema).
- Stap 6: publicatiedatum per artikel + `PeriodAgendaOverview`.
- Stap 7: planoverzicht + **Artikelen bevestigen** → `POST /fastlane` per artikel (thema+slot); 1 slot → Onderzoek, meerdere → Kalender.

### Nog open / afwijkingen

- LLM-voorstellen invalshoeken nog niet; lokale seed. Onderzoek-kaarten en artikel-detail zijbalk + CTA staan live (PR #10).
- Multi-kanaal: één slot per artikel op primair kanaal (geen fan-out per kanaal/offsets).
- Systeemcheck ritme/plan-check nog niet live (placeholders in `ArticleStartStepKlopt`).

## Opdracht — weekplan stap 8, PR 1 (campagne stap 1 en 2)

### Gebouwd

- Stap 1: pulldown Touch/Tell/Sell blijft op de pagina (geen automatische sprong). Letterlijke hint over lancering/reeks. Vier kalenderkleuren; vervaagd = al in gebruik bij concept, bevestigd of loopt. Verder + Ongedaan maken.
- Stap 2: titel “Waar gaat de campagne over?”, thema + extra gedachten, bronnen (link controleren → toevoegen, PDF/Word). Sell houdt de product-URL en de bestaande sell-fit-hint.
- Opslag: `calendar_color`, `extra_thoughts`, `source_folder_id`. Links in de campagnemap bij aanmaken of bijwerken. Bestanden uploaden zodra het plan bestaat. Migratie dropt niets.

## Opdracht — weekplan stap 8, PR 2 (campagne stap 3)

### Gebouwd

- Stap 3 volgt klikmodel `p-campagne3`: **Merkonderdelen** (labels Rol/Doel/…) → **Feiten** → **SEO- en GEO-vragen** (weergave + link naar SEO/GEO, overslaan mag) → **Hashtags**.
- Weg: aparte Vragen-sectie en komma-woordenveld op deze stap (zelfde lijn als artikel-stap 3).
- Opslag: `hashtags_json` op de campagne; GEO-ankers blijven via `geoQuestionIds`. Hashtags gaan nog niet automatisch mee naar gegenereerde slots (alleen bewaard op de campagne).

### Niet in deze PR

- Invalshoeken als doorgeefluik, en de gedeelde Systeemcheck zijn latere PR’s.
- De overstap van posts per kanaal naar artikelen op meerdere kanalen is één aparte slice. Erik (14 nov): ja, een campagne plant artikelen die op meerdere kanalen verschijnen. Gevolg, nog niet gebouwd: fase-aandelen over artikelen (elk artikel één fase, kanaalversies erven die; minimum = aantal fases); een LinkedIn-artikel (`artikel`) krijgt altijd één of meer promotieposts (`promotie`) die de fase erven, niet meetellen in de verdeling, wel in de 100-cap, en op of na de publicatiedag vallen — aantal en timing volgen nog van Erik. `tests/php/campagne-plan-generator.php` wordt dan 12 Instagram-artikelen waarvan 8 ook op LinkedIn. Bestaande campagnes blijven posts per kanaal. Addendum §5 hangt hieraan.

### Vraag aan Rogier

Het fasescherm blijft in de wizard. Tickets 2 en 3 van 30 oktober vragen om merkonderdeel per fase en om fases weg te laten of te verschuiven (`phasePlan.disabledKeys` wordt al opgeslagen). Het klikmodel heeft dat scherm niet. Mag de fase-bewerking uit de wizard, of moet het klikmodel die alsnog krijgen?

Help (`helpFaq.klikmodel.ts`) zet bronnen nog op stap 3 en belooft een verschuivingsvoorstel. Het klikmodel zet bronnen op stap 2; de ritmecheck is een waarschuwing zonder verschuiving. Help is niet herschreven (stap 10).

## Opdracht — weekplan stap 5 (Branche-koppeling)

### Gebouwd (PR #8 + deze branch)

- Import: `php scripts/import-branchebibliotheek.php` (CSV 102 + 20 JSON). Catalog-CSV ook in `web/data/branchelijst.csv` als fallback vóór DB-import.
- Wizard: pulldown 102, labels (gecureerd)/(automatisch afgeleid), markt-archetype op stap 1 en 8.
- Niet-gecureerd: live afleider, cache per tenant+branche (`hba_branche_tenant_cache`), logboek 1e (`hba_derived_branche_log`).
- Platform › Settings: logboek + CSV-export in de vorm van `branchelijst.csv` (+ aantal, laatste datum).

### Nog te doen op de server

- `php scripts/import-branchebibliotheek.php` draait niet lokaal (geen MySQL). Eenmalig na deploy op de omgeving.

### Follow-up Claude-feedback (14 nov) — klanttaal + drempels

- Rapport: “Rapport/SEO/GEO-rapport opgeslagen bij Alle artikelen” (geen Report/Findability/archief).
- Alle artikelen-bevestiging: “Geen concept gevonden.”
- Drempels thema-onderbouwing: `theme-onderbouwing.php` + UI-waarschuwing Bronnen toevoegen / Toch doorgaan; label “licht onderbouwd” bovenaan Onderzoek; test `tests/php/theme-onderbouwing.php`.

## Pilot-fixes leesronde (14 nov)

- **B1:** verbodslijst-guard op thema-oordelen, research-oordelen, artikel-hints, campagne-plan-check (`hba-client-output-guard.php`).
- **B2:** “jullie” → “je” in zeven klantteksten (leesronde-lijst).
- **B3:** `StatusRow` knoppen Bewerken / Verwijderen.

## Eerdere opdrachten

- **Stap 3 §9 kanaalfilters:** `main`, commit `070903f` (prod).
- **Opdracht 2 navigatie:** gemerged op `main` (PR #2).
- **Opdracht 1 §3 kanaaleisen:** gemerged met pilot-branch (was PR #1).
- **Opdracht 4 zoekpunt 5:** `docs/zoekpunt5-natrek-grok-2026-11.md`.
