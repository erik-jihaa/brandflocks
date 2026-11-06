# Plan: Brandflocks-fork maken en opschonen

**Datum:** 5 november 2026
**Besluit (Erik):** Brandflocks wordt een **fork**: een eigen repo, los van CE. ce.igisnext.com blijft draaien zoals het is.
**Doel:** een schone codebasis zonder de restanten van de CE-geschiedenis. Daarop bouwen we het herontwerp (`briefing/cursor-weekplan-herontwerp-2026-11-03.md`).

---

## Uitgangspunten

1. **CE wordt niet aangeraakt.** Opschonen gebeurt alleen in de fork. Bestaande CE-klanten lopen geen risico.
2. **Schone start, geen meegenomen geschiedenis.** De Brandflocks-repo begint met één eerste commit van de opgeschoonde boom, zonder de git-geschiedenis van CE. Dat voorkomt dat oude secrets, pilotbestanden en grote zips in de geschiedenis meegaan. De CE-repo blijft het naslagwerk.
3. **Eerst een vangnet, dan schrappen.** Er bestaan nog geen geautomatiseerde tests. Zonder vangnet wordt er niets verwijderd.
4. **Route weg ≠ functie weg.** Na het verwijderen van een route controleer je of de functie erachter nog ergens anders wordt aangeroepen. Alleen ongebruikte functies verwijderen.
5. **Eerst opschonen, dan het herontwerp.** Zo wordt het herontwerp één keer gebouwd, op de schone basis.

---

## Stap 0 — CE-werk veiligstellen (Cursor, ±30 min)

In de CE-werkmap staan 41 gewijzigde bestanden die niet gecommit zijn. Dat is het campagnewerk van 27 tot 30 oktober: check vooraf, vragen- en feitenbank, Touch/Tell/Sell, slot = thema.
- Commit dat op `ronde-8-preview`, in logische delen. Neem de verwijdering van `web/migrations/add_aanspreekvorm.sql` en `docs/rogier-pakket-ronde-6-pilot-2026-10-19.zip` alleen mee als die bewust zijn; anders terugzetten.
- Zet een tag `bf-forkpunt` op die commit. Vanaf die commit wordt geforkt.

## Stap 1 — Fork maken (Erik + Cursor, ±30 min)

- Maak een nieuwe privé-repo `erik-jihaa/brandflocks` op GitHub.
- Kopieer de werkboom van `bf-forkpunt`, zonder `.git`. Maak `git init` en een eerste commit "Brandflocks: start vanaf CE bf-forkpunt (<hash>)".
- Draai direct `gitleaks detect` op de nieuwe repo. Gevonden secrets eruit halen en in CE **roteren**: die sleutels hebben in de CE-geschiedenis gestaan. Dit mag nu al, los van Fable.
- `.env*` hoort nooit in de repo. Alleen `*.example`-bestanden.

## Stap 2 — Vangnet (Cursor, ±2 uur)

Uit §4 van het weekplan, nu vóór het opschonen:
1. Testset-opzet: PHPUnit (`tests/php/`), Vitest (`frontend/`), `scripts/test-all.sh`.
2. **v1.13-replay:** de 95 opgeslagen scores leveren 19/19 identieke hoofdoordelen op. Kopieer de benodigde runbestanden eerst naar `tests/fixtures/`, want `docs/` wordt in stap 4 opgeruimd.
3. **Campagne-plangenerator:** Lancering, LinkedIn 8 + Instagram 12 → 3/4/4/2/4/3, maximaal één post per kanaal per dag.
4. **Rooktest-lijst** (handmatig op staging van de fork, vastgelegd in `tests/rooktest.md`):
   - inloggen en van account wisselen;
   - een bron toevoegen;
   - een thema → onderzoek → artikel schrijven;
   - de merkarchitectuur opslaan, met de architectuurcheck;
   - een campagne maken en bevestigen;
   - Agenda en Rapport tonen data;
   - publiceren naar WordPress (test);
   - in platformbeheer een tenant aanmaken.

Bij elke opschoonronde in stap 4 moeten slagen: `test-all.sh`, `npm run build` (de TypeScript-check), `php -l` op alle PHP-bestanden en de rooktest.

## Stap 3 — Inventaris (Cursor, ±1 uur; Erik checkt 1 ding op de server)

Laat Cursor een script `scripts/inventaris-dode-code.sh` maken. Dat geeft vier lijsten, zonder iets te wijzigen:
- API-routes in `index.php` zonder aanroep vanuit de frontend, scripts, n8n-JSON of documentatie;
- PHP-functies zonder aanroep;
- frontend-componenten en -pagina's die nergens worden geïmporteerd;
- DB-tabellen en -kolommen zonder lees- of schrijfcode.

**Erik checkt op de CE-server:** de crontab en de n8n-instantie. Welke `/admin/*`-routes worden daar automatisch aangeroepen? De repo bevat geen crontab, dus dat is van hieruit niet te zien. Het gaat vooral om `mailbox-sync`, `analytics-sync`, `scraper-sync`, `llm-visibility-sync`, `poll-n8n-failures`, `import-support-emails`, `ce02-trigger` en `hygiene`.

## Stap 4 — Opschonen in rondes (Cursor, ±½–1 dag)

Elke ronde is één commit, gevolgd door het vangnet uit stap 2.

### Ronde A — Repo-vuil (veilig, mechanisch)
- `backups/` helemaal weg. Daarin staat onder meer een n8n-export van september.
- `docs/`: weg met pilotrapporten, Rogier-pakketten, ijkrun-logs en -json, screenshots, zips en alle *"(erik's conflicted copy …)"*-bestanden van Dropbox. Houd alleen een nieuwe `docs/BUILD-NOTES.md` (vers gestart, met een verwijzing naar CE) en de architectuurdocumentatie die nog klopt.
- `briefing/`: houd alleen de actieve specificatie, in `briefing/spec/`:
  - het klikmodel;
  - het voorstel herontwerp;
  - Hero Brand Core Logic v2.3;
  - de beide augustus-addenda en `hba-kit-extract/`;
  - de branchebibliotheek;
  - de campagnes-aanpassingen van 30 oktober en ons antwoord;
  - `drempels-thema-onderbouwing.md`;
  - het weekplan en dit plan.
  De rest blijft in CE.
- `scripts/`: weg met `build-rogier-*.sh`, `build-testset-blind-html.py`, `generate-ce02-workflow.py` en `generate-ce-sources-sync-workflow.py` (kijk wel eerst of n8n in Brandflocks daar opnieuw mee wordt opgezet), `run-hba-r0-ce04-post3-staging.sh` en `__pycache__/`.
- `.DS_Store` overal weg en in `.gitignore`.

### Ronde B — Eenmalige pilot-, meet- en patchroutes
Deze `/admin/*`-routes horen bij de v1.8–v1.13-pilot of zijn eenmalige datapatches. Ze worden nergens meer aangeroepen. Weg, mét de bestanden die alleen voor die route bestaan:

- **Meten en testen:** `hba-ijkrun`, `hba-testmerken-matrix`, `hba-testmerken-stability`, `hba-ce04-post3`, `hba-stroomtest-brandpunt`, `hba-v0-verleiding`, `hba-constraint-breach-verificatie-test`, `hba-pilot-negeer-alert-test`, `hba-content-score-model-test`, `hba-ce03-self-review-rerun`, `ce03-phase2-test`, `hygiene-acceptance`, `testset-freeze`, `hba-baseline`, `hba-phase1`, `hba-phase3`.
- **Testmerken en testposts:** `provision-hba-testmerk`, `import-hba-testposts`.
- **Eenmalige patches:** `hba-aanspreekvorm-patch`, `hba-woorden-patch`, `hba-narrative-repair`, `hba-kernformuleringen`, `hba-hashtag-bewijs`, `theme-source-backfill`, `fix-slot-status`, `copy-client-sources`, `import-tenant-section`.
- **Debug per slot:** `hba-research-verdict-slot`, `hba-theme-verdict-slot`, `debug-article`.
- **Uitzondering `hba-article-hint-slot`:** wordt aangeroepen door de CE-03-workflow in n8n (`generate-ce03-workflow.py` + `JSON/CE-03`). Verwijder pas nadat die workflow-stap is uitgeschakeld of verplaatst naar een niet-admin endpoint.
- **Previews en tussenstappen:** `campagne-preview-accept`, `agenda-preview`, `hba-wizard-document-refresh`.
- **Alleen voor de CE-pilot:** `hba-pilot-weekly-report` en de e-mailalert voor betwiste tegenkracht (A3). Brandflocks heeft geen pilot.

Twijfelgevallen, eerst checken in stap 3 of ze runtime-logica bevatten: `hba-ensure-guardrail`, `hba-status`, `prune-support-service`, `republish-wp`.

### Ronde C — Operationeel: houden
Gebruikt in BUILD-NOTES of nodig voor beheer. Deze blijven:
- `db-dump`, `wp-diagnose`, `theme-refill`, `run-migration`, `schema-migrations-init`, `schema-export`;
- `mailbox-sync`, `analytics-sync`, `scraper-sync`, `llm-visibility-sync`, `poll-n8n-failures`, `ce02-trigger`;
- `agenda-repair`, `list-slots`, `debug-research`, `reset-content`, `reset-client-content`;
- `create-client`, `update-client`, `delete-client`, `export-tenant`, `import-tenant`;
- `set-password`, `set-platform-password`, `create-platform-user`, `events`, `hygiene`, `import-support-emails`.

Wat uit stap 3 (crontab/n8n) niet gebruikt blijkt, mag alsnog weg.

### Ronde D — Ongebruikte code
Wat het inventarisscript als "geen aanroep" meldt aan PHP-functies, frontend-componenten en hulpbestanden: weg, in kleine commits per map.

### Ronde E — CE-specifiek naar Brandflocks
- Merknamen en domeinen: igisnext / ContentFlow / CE-teksten in UI, e-mails en config → Brandflocks, of configureerbaar per bureau. Whitelabel is al besloten: logo, kleuren en productnaam per bureau.
- Env-namen (`CE_ENV` e.d.) mogen blijven als interne naam. Hernoemen alleen waar het zichtbaar is voor gebruikers.
- De staging-banner "CF_Staging" wordt een configureerbaar label.

### Ronde F — Database-basis
- Maak één geconsolideerde `web/schema/brandflocks-baseline.sql` uit de huidige migraties, zonder de tabellen en kolommen die in ronde B tot D zijn weggevallen. Een nieuwe Brandflocks-installatie start daarmee, niet met het afspelen van alle CE-migraties.
- Migraties ná de baseline beginnen opnieuw in `web/migrations/`.
- **Niet droppen** van tabellen die nog data kunnen bevatten bij een import uit CE. Weglaten uit de baseline is genoeg.

## Stap 5 — Beslissen vóór schrappen (Erik / Rogier)

Deze zaken niet weghalen zonder besluit:

| Onderdeel | Omvang | Vraag |
|---|---|---|
| LinkedIn-koppeling (OAuth, sync, analyse) | 89 bestanden | Niet in het klikmodel. Blijft dit (publiceren/meten via LinkedIn), of valt het af? |
| Google Drive | 15 bestanden | Niet in het klikmodel. Bron-input houden of weg? |
| Asana | 21 bestanden | Niet in het klikmodel. Weg? |
| Joomla-publicatie | klein | Het klikmodel noemt "WordPress, Joomla of FTP": houden |
| Doelen (`objectives`) | 3 afhankelijkheden | Valt in het herontwerp weg; de ombouw zit in weekplan stap 1a. Pas daarna verwijderen |
| Snel artikel, Thema's-pagina, Planning-pagina | frontend | Gaan weg via het herontwerp (weekplan stap 6), niet via dit opschonen |
| Platformlaag (multi-tenant beheer) | — | Houden: per bureau één installatie met meerdere klantaccounts en een overzichtsdashboard |

Mijn advies: LinkedIn voorlopig **houden** (te groot om blind te schrappen, en waarschijnlijk nodig voor publiceren). Asana weg. Drive voorlegen aan Rogier.

## Stap 6 — Daarna

- Het herontwerp (weekplan stap 1–11) bouwen op de opgeschoonde fork.
- Deploy-doel: een omgeving `brandflocks` toevoegen aan `scripts/deploy-ftp.sh`, met `.env.brandflocks.example` en de bestaande isolatiecontrole. Dat is voor de gedeelde testserver bij Antagonist; vaste klanten krijgen later een eigen VPS.

---

## Modeladvies

| Werk | Model in Cursor |
|---|---|
| Stap 0 en 1 (commits, fork, gitleaks) | Standaard (Sonnet-klasse) |
| Stap 2 (vangnet), stap 3 (inventarisscript) | Zwaar (Opus-klasse): de tests moeten de echte werking vangen |
| Ronde A, ronde E (mechanisch) | Snel (Haiku-klasse / Composer) |
| Ronde B, ronde D (wat kan echt weg, aanroepketens volgen) | Zwaar (Opus-klasse) |
| Ronde F (schema-baseline) | Zwaar (Opus-klasse) |

## Controlemomenten

| Moment | Wat moet kloppen |
|---|---|
| Na stap 1 | gitleaks schoon (of gevonden secrets geroteerd), fork bouwt (`npm run build`) |
| Na stap 2 | Vangnet groen op de onaangeroerde fork. Dat is de nulmeting |
| Na elke ronde A–F | `test-all.sh`, `npm run build`, `php -l`, rooktest groen. Bij rood: die commit terugdraaien, niet doorgaan |
| Eind | Erik doorloopt de rooktest zelf op staging van de fork. Daarna start het herontwerp |

## Tijd en gevolg voor deze week

Realistisch is dit 1 tot 1,5 dag werk. Daarna komt het herontwerp. Een upload naar het Brandflocks-domein deze vrijdag zit er niet meer in. Plan de upload pas na het herontwerp en het testplan.

---
🤖 Generated with [Claude Code](https://claude.com/claude-code)
