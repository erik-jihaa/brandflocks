# Brandflocks — BUILD-NOTES

**Repo:** [github.com/erik-jihaa/brandflocks](https://github.com/erik-jihaa/brandflocks)  
**Afgesplitst van CE:** tag `bf-forkpunt` op [CE-Cursor](https://github.com/erik-jihaa/CE-Cursor) (`53b3173`). CE (`ce.igisnext.com`) blijft de pilot- en naslagrepo.

## Status

| Fase | Wat |
|------|-----|
| Fork + vangnet | `./scripts/test-all.sh`, fixtures in `tests/fixtures/` |
| Inventaris | `docs/inventaris-dode-code.md` |
| Opschonen | ronde E (merknamen); ronde F (schema-baseline) — `web/schema/brandflocks-baseline.sql`, migraties in `web/migrations/` |
| Herontwerp | `briefing/spec/cursor-weekplan-herontwerp-2026-11-03.md` |

## Deploy

Checklist: `docs/deploy-brandflocks-checklist.md`  
Design tokens: `docs/igisnext-identity.md` (naam/huisstijl wordt Brandflocks-whitelabel).

## Changelog (Brandflocks-repo)

### 2026-11-12 — Addendum slice 1 (copy-modus + per-kanaal)

- `web/api/copy-modus.php`, `channel-copy-constraints.php`; kolommen `themes.copy_modus`, `copy_modus_override`.
- Schrijfpad: `write_directive` + CE-03 meta (`copy_modus`, kanaaleis).
- Frontend: thema-startknop **Schrijf met activatie-toon**; test `tests/php/copy-modus-directive.php`.

### 2026-11-06 — Ronde A repo-vuil

Pilotdocs, Rogier-pakketten en oude briefing verwijderd; actieve specs onder `briefing/spec/`. CE-geschiedenis blijft in CE-Cursor.

Naloop: CE-02/sources-sync n8n-wrappers falen met verwijzing naar CE-Cursor; HBA-architectuur- en weekplan-paden bijgewerkt; diagnose-output naar `tests/output/` (gitignored).

### 2026-11-06 — Ronde B pilot/meet/patch-routes

~35 `/admin/*`-routes uit het fork-plan verwijderd (v1.8–v1.13-pilot, testmerken, eenmalige patches, debug-slots, previews). **Behouden:** `hba-article-hint-slot` (CE-03 n8n), operationele routes voor ronde C.

Repos alleen voor die routes weg: `hba-ijkrun-repository.php`, `hba-stroomtest-brandpunt-repository.php`. Pilot-alert/testmerken-PHP blijft zolang HBA-v2 het laadt.

Scripts die `copy-client-sources` / `import-tenant-section` aanriepen: aangepast of exit — gebruik CE-Cursor voor tenant-sectie-sync tot er een Brandflocks-vervanger is.

### 2026-11-06 — Ronde C operationele admin-routes

Allowlist vastgelegd in `docs/operational-admin-routes.md`. Twijfelroutes zonder repo/n8n-ref verwijderd: `republish-wp`, `hba-status`, `hba-ensure-guardrail`. Inventaris §5 toont nu script-referenties uit de repo.

### 2026-11-06 — Ronde D (deel 1) dode PHP

Verwijderd: `ce03-self-review-repository.php` (geen `require`, pilot self-review rerun was al weg), `tenant-bundle-import.php` (alleen door weggehaalde `import-tenant-section`). `testset-repository.php` ingekort tot `ce_sql_exclude_testset_freeze_slots` (freeze-copy na `testset-freeze`-admin weg). Frontend-“orphans” uit inventaris bleken false positives (wel imports).

### 2026-11-06 — Ronde D (deel 2) HBA pilot testmerken

`hba-testmerken-repository.php` weg (alleen voor admin matrix/stability/provision; routes weg). `ce_hba_testmerk_client_names()` en eenvoudige slot-resolver in pilot-repos gezet zodat hint-Negeer-mail en (CLI) weekpayload blijven werken.

### 2026-11-06 — Ronde D (deel 3) HBA content-score testhelpers + Asana-bron

Verwijderd uit `hba-repository.php`: ongebruikte HBA content-score testhelpers (`ce_hba_*content_score*`). Asana als bron-type en API-routes (`/sources/.../asana-run`, `POST /sources/asana/discover`) verwijderd; frontend-tab en veldhulp weg. LinkedIn- en Drive-bronnen ongewijzigd. Bestaande `sources`-kolommen voor Asana blijven in de database staan (geen schema-drop); `category = 'asana'`-rijen worden uit lijsten/tellingen gehaald en `GET/PATCH` op zo’n id geeft 404.

Naloop: `POST /api/admin/delete-asana-sources` (reset-token + op productie `confirmEnvironment`) verwijdert die rijen echt uit `sources`; optioneel `clientId` voor één tenant. **Niet gedraaid** — bestaande Asana-rijen in de CE-DB blijven staan.

### 2026-11-06 — Ronde E merknamen → Brandflocks

Gebruikerszichtbare ContentFlow / Content Engine / CF_Staging-teksten (UI, mails, browser-tab, support-chat) zijn Brandflocks, of configureerbaar via `CE_PRODUCT_NAME`, `CE_STAGING_LABEL`, `CE_PLATFORM_LABEL` (ook in `/api/health` → `branding`). Interne env-namen (`CE_ENV`, `CE_*`) ongewijzigd. `CE_ENV=brandflocks` is een named install (banner aan, geen productie-guard). Design-tokens (`--igis-*`) en logo-bestanden blijven tot het visuele whitelabel.

### 2026-11-06 — Ronde F database-baseline

- **`web/schema/brandflocks-baseline.sql`** — geconsolideerd schema (afgeleid van CE `docs/schema.sql` forkpunt), **zonder** Asana op `sources` (enum + kolommen), in lijn met ronde D.
- **Migraties:** bron staat in **`web/migrations/`**; oude incrementele CE-bestanden staan in `web/migrations/archive/pre-baseline-2026-11/` (referentie + PHP auto-heal). `/api/admin/run-migration` en `run-migration.sh` gebruiken alleen `web/migrations/*.sql`; deploy synct die map.
- **Bestaande databases:** eenmalig `POST /api/admin/schema-migrations-init` vult `schema_migrations` met alle pre-baseline bestandsnamen (scan van de archive-map), zodat toekomstige `--all` alleen nieuwe migraties draait.

### 2026-11-06 — Deploy-profiel brandflocks-target (staging-submap)

- **`brandflocks-target`** → `.env.brandflocks-target`, `CE_ENV=brandflocks`, andere `FTP_REMOTE_PATH` + sub-URL onder staging. `deploy-ftp.sh` schrijft `ce.local.php` met `CE_ENV` uit het env-bestand (niet de profielnaam). `smoke-test.sh brandflocks-target` vergelijkt `/api/health` met `CE_ENV` in dat bestand.
- **Subpad + gedeelde PHP:** zolang de API op dezelfde vhost draait als hoofd-staging, zet `CE_API_BASE_URL=https://staging.igisnext.com` en `CE_HEALTH_EXPECT_ENV=staging` — `CE_PUBLIC_BASE_URL` blijft de SPA-URL (`…/brandflocks`). `run-migration.sh` gebruikt ook `CE_API_BASE_URL`. `check-env-isolation.sh brandflocks-target` mag zonder `.env` (productie) draaien; dan alleen check tegen `.env.staging`.

### 2026-11-06 — Herontwerp stap 1a (Doelen → merkdoel voor schrijven)

- **`clients.brand_goal`** (Strategy / merkverhaal) voedt nu `write_directive` en CE-03-meta vóór oude `objectives`-records (`ce_resolve_article_objective_for_writing()` in `objectives-repository.php`; `tone-styles.php` gebruikt die voor persist + webhook).
- **CE-03:** optioneel `objective_source=brand_architecture`, `brand_goal=…` wanneer het merkdoel leidend is; `objective_id` wordt dan weggelaten (lege id).
- **Fastlane:** als `brand_goal` gevuld is, wordt een meegegeven legacy `objective`-uuid genegeerd voor DB-koppeling (`themes`/`slots`/`articles.objective_final`) zodat schrijven niet per ongeluk op het oude Targets-doel blijft hangen.

### 2026-11-06 — Herontwerp stap 1b (Campagne-slots = thema)

- **`ce_campagne_preview_generate_slots`:** per slot een **`themes`**-rij + **`content_slots.theme_id`**; geen artikel meer bij generatie. `input_human`-marker bevat **`themeId`**.
- **Agenda:** zonder artikel-row en `origin=campagne_preview` → pipeline-fase **Thema** (`ce_resolve_agenda_phase` + `agenda.php`).
- **`/articles?slot_id=…`:** eerste GET/PATCH voor campagne-preview-slot zonder rij → **`ce_ensure_article_stub_for_slot`** (campagnerationale + platform uit slot voor stub).
- **CE-03-meta:** `ce_campagne_context_meta_from_slot_row()` (o.a. **`campagne_title`**); `ce_ce03_webhook_meta_from_slot` gebruikt die helper. Extra: `ce_monday_of_calendar_date()`.

### 2026-11-06 — C-fix: HBA-advies op `/research/{uuid}`

Oorzaak was **niet** ontbrekende `article_id` op het slot. Na 1b heeft het campagne-slot een `theme_id`; Research stuurde die, waarna `/hba/theme-verdicts` de `slot_id` liet vallen (XOR). INNER JOIN `=` op `theme_id` / `research_item_id` geeft geen rij bij NULL. Campagne-opdracht staat in `themes.rationale`, niet `motivation`.

- GET/POST mergen `theme_id` + `slot_id`; expand slot↔theme; NULL-veilige `<=>`; rationale-fallback.
- Frontend stuurt altijd beide ids. Theme-verdicts try/catch + lege `clientId` → JSON i.p.v. 500.

### 2026-11-06 — C-fix: `/article/{uuid}` toont thema-start i.p.v. leeg artikel

GET `/articles/{uuid}` maakt geen stub meer voor campagne-slots zonder tekst. Response `themeOnly` → thema-weergave met **Schrijf dit thema** (naar Onderzoek). Stub alleen bij PATCH of CE-03 na research. Bestaande lege stubs worden hetzelfde behandeld.
