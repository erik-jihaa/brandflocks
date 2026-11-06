# LinkedIn Scan — bouwstatus

**Productie:** [https://linkedin-analyse.igisnext.com](https://linkedin-analyse.igisnext.com)  
**Laatste update:** 9 augustus 2026 (namiddag)  
**Briefing:** `briefing/cursor-linkedin-scan-tool.md`, `briefing/cursor-linkedin-scan-narrative.md`

Losstaande PHP/MySQL-tool voor acquisitie: LinkedIn-bedrijfspagina scannen → vormgegeven e-mailrapport kopiëren en versturen. Geen CE-tenant, geen React-build.

---

## Waar we staan

| Onderdeel | Status |
|---|---|
| Apify-koppeling (bedrijfspagina-posts) | ✅ Actor `apimaestro~linkedin-company-posts-batch-scraper-no-cookies` |
| Apify-koppeling (schoolpagina-posts) | ✅ Zelfde actor als bedrijven — `apimaestro~linkedin-company-posts-batch-scraper-no-cookies` accepteert `/school/`-URL's |
| Analyzer (dedupe + metrieken + observaties) | ✅ GrowthElements-testcase (17 posts uit 21 rijen) |
| Narratief-laag (Claude Haiku) | ✅ Merkverhaal, consistentie, distinctiveness (met branche), intro/case line |
| Rapport-renderer (e-mailfragment) | ✅ GrowthElements-stijl, geen underlines, sentimentbolletjes |
| Verkopend e-mailbegin | ✅ Pitch + case line op maat (narrative of kwantitatieve fallback) |
| Kostenbewaking | ✅ max 30 posts, cache 7 dagen, daglimiet scans + USD-budget |
| Login (Erik + Eva) | ✅ `app_users` in DB (fallback config bij eerste seed) |
| Forgot password | ✅ `forgot-password.php` + reset-mail (zelfde flow als CE) |
| Deploy | ✅ `./scripts/deploy-linkedin-analyse.sh production` |
| Backup | ✅ `./scripts/backup-linkedin-analyse.sh` → `backups/linkedin-analyse-latest.zip` |
| Scan-historie op startpagina | ✅ Laatste 40 voltooide scans, branche-label, View \| Delete |
| iGiSNext identity CSS | ✅ `@import igisnext-identity.css` — zie `docs/igisnext-identity.md` |
| Dataretentie | ✅ Ruwe Apify-posts (`raw_data`) max. 90 dagen; postfragmenten in metrics en publieke rapportlink max. 180 dagen; mislukte inlogpogingen max. 30 dagen. Opruimen hooguit 1×/dag (`cleanup.php` cron of eerste `scan.php` van de dag) |

---

## Designreferentie

| Bestand | Rol |
|---|---|
| `docs/igisnext-identity.md` | Goedgekeurde beslissingen (kleuren, pills, fields, lijsten, notices) |
| `docs/igisnext-identity.css` | Canonical CSS — tokens + componentclasses |
| `linkedin-analyse/assets/igisnext-identity.css` | Kopie voor FTP-deploy (sync vóór deploy) |
| `linkedin-analyse/assets/app.css` | Alleen pagina-layout; importeert identity |
| CE live showcase | `/design` in Content Engine-app |
| Cursor rule | `.cursor/rules/igisnext-identity.mdc` |

**Twee designlagen:**

| Laag | Vormgeving |
|---|---|
| App (`index.php`, login, scan-lijst) | iGiSNext identity — groene pills, pink fields, Agenda LIST_ROW |
| E-mailrapport | GrowthElements documentstijl, beige `#EAE6DC`, tabel-layout |

---

## Architectuur

```
linkedin-analyse/
├── index.php              URL-formulier + login + scan-historie
├── scan.php               Scan draaien / rapport tonen / klembord
├── forgot-password.php    Reset-mail aanvragen
├── reset-password.php     Nieuw wachtwoord instellen
├── cleanup.php            Cron: raw_data retention
├── api/
│   ├── apify-client.php           Apify run + wachten + kosten
│   ├── analyzer.php               Kwantitatieve metrieken + observaties
│   ├── narrative-analyzer.php     Claude Haiku — merkverhaal
│   ├── report-renderer.php        E-mail HTML + intro + narrative-sectie
│   ├── scan-repository.php        DB, cache, limieten, lijst, delete
│   ├── auth-repository.php        Login + wachtwoord in DB
│   ├── password-reset-repository.php
│   └── mail-helper.php            Reset-mail
├── assets/
│   ├── igisnext-identity.css      Sync uit docs/
│   └── app.css                    Pagina-layout
├── includes/
│   ├── bootstrap.php              Auth, PDO, requires
│   ├── branche-library.php        Statische kopie CE branche_library (6 branches)
│   └── context-profiles.php       Statische kopie HBA_CONTEXT_PROFILES (6 profielen)
│   └── config.local.php.example
├── sql/
│   ├── schema.sql
│   ├── add_narrative_cost.sql
│   ├── add_auth_tables.sql
│   └── add_branche_column.sql
│   └── add_scan_context_fields.sql
├── fixtures/                      GrowthElements-testdataset
└── scripts/                       test-analyzer, test-narrative, run-live-scan
```

**Stack:** PHP 8 + MySQL. Rendering server-side.

---

## Database

Tabel `scans` — één rij per Apify-run (of cache-hit):

| Kolom | Doel |
|---|---|
| `raw_data` | Apify JSON — hergebruik zonder nieuwe kosten (cache) |
| `metrics` | Berekende uitkomsten + optioneel `narrative` JSON |
| `branche_code` | Optionele branche voor distinctiveness-check (`NULL` = geen check) |
| `company_description` | Optionele bedrijfsomschrijving als grondwaarheid voor narrative |
| `contextprofiel` | Optioneel contextprofiel (`nieuw_merk`, `herpositionering`, …) |
| `cost_usd` | Apify-run |
| `narrative_cost_usd` | Claude Haiku (apart voor debugging) |

Auth-tabellen: `app_users`, `password_reset_tokens` — zie `sql/add_auth_tables.sql`.

Migratie bestaande DB:

```bash
mysql … < linkedin-analyse/sql/add_narrative_cost.sql
mysql … < linkedin-analyse/sql/add_auth_tables.sql
mysql … < linkedin-analyse/sql/add_branche_column.sql
mysql … < linkedin-analyse/sql/add_scan_context_fields.sql
```

---

## UI-beslissingen (app)

| Element | Beslissing |
|---|---|
| Pill main | Groen fill + blauwe tekst |
| Pill second | Groene outline + blauwe tekst (geen beige) |
| Fields | 40px, pink bg, placeholder beige, typ zwart, geen label erboven |
| Scan-lijst | Agenda LIST_ROW — status groen, View \| Delete |
| Opnieuw ophalen | CE CheckboxOption (zwart bolletje) |
| Flash feedback | Alleen typografie (SaveNotice), fade 5s na delete |
| Login | Forgot password link, reset via mail |

Details: `docs/igisnext-identity.md`.

---

## Rapportopbouw

**Outreach-mail** (`li_render_outreach_email_html`) — plakken in Mail:

1. Kop — Social Media Scan — {bedrijf}, datum, ContentFlow
2. **Intro** — case line + pitch
3. **Score-samenvatting** — positionering / consistentie (indien narrative)
4. **Activiteitstijdlijn** (indien data)
5. **De cijfers** — tabel met sentimentbolletjes
6. **Wat opvalt** — observaties
7. **Verder online** — teaser + pill-link; vanaf **Posts op reacties** niet in de mail

**Volledig rapport** (`report.php` / preview op scan) — alles hierboven plus:

8. **Posts op reacties** — mini-ranking
9. **Wat ze over zichzelf vertellen** — narrative
10. **Waar ContentFlow bij kan helpen**
11. **Tot slot**

**Onderwerpregel mailto:** *AI beloont geen gemiddelde merken — hoe zit dat bij {bedrijf}?*

Knop **Kopieer outreach-mail** pakt alleen `#scan-report-email` (geen witte pagina-rand).

---

## Configuratie (.env.production)

| Variabele | Doel |
|---|---|
| `APIFY_API_TOKEN` | Platform-token (zelfde als CE) |
| `LINKEDIN_ANALYSE_APP_PASSWORD` | Login Erik (seed + reset) |
| `LINKEDIN_ANALYSE_EVA_APP_PASSWORD` | Login Eva |
| `LINKEDIN_ANALYSE_DB_*` | Aparte MySQL-database |
| `LINKEDIN_ANALYSE_FTP_*` | Deploy naar subdomein |
| `ANTHROPIC_API_KEY` | Narratief-laag |
| `LINKEDIN_ANALYSE_AI_SUMMARY_ENABLED` | `true` / `false` |
| `LINKEDIN_ANALYSE_MAIL_FROM` | Reset-mail afzender (fallback `CE_MAIL_FROM`) |

Deploy genereert `includes/config.local.php` (niet committen).

---

## Operatie

### Deploy

```bash
# Sync identity-css indien gewijzigd in docs/
cp docs/igisnext-identity.css linkedin-analyse/assets/
./scripts/deploy-linkedin-analyse.sh production
```

### Backup (code-snapshot)

```bash
./scripts/backup-linkedin-analyse.sh
```

Output: `backups/linkedin-analyse-latest.zip` (overschrijft vorige; bevat ook `docs/igisnext-identity.*`).

### Tests lokaal

```bash
cd linkedin-analyse
php scripts/test-analyzer.php          # fixture → metrieken
php scripts/test-narrative.php         # fixture → Claude (API-key nodig)
php scripts/test-narrative-polish.php  # offline check li_narrative_polish_dutch()
```

### Testcase productie

URL: `https://www.linkedin.com/company/growthelements/`  
Verwacht: 17 unieke posts, stiltes 35/32/26 dagen, ritme ~0,6/week.

### Cache & narrative

- Zelfde slug binnen 7 dagen → hergebruik `raw_data`, geen Apify-kosten
- Oude scans zonder `metrics.narrative` krijgen bij openen automatisch narrative uit `raw_data` (~€0,002 Claude, geen Apify)
- Branche achteraf wijzigen op `scan.php` herberekent alleen narrative uit `raw_data` — geen Apify-kosten
- **Opnieuw ophalen** → verse Apify-run (1–2 min); branche van de vorige scan wordt hergebruikt

### Cron (optioneel)

```cron
0 3 * * * php /pad/naar/linkedin-analyse/cleanup.php
```

---

## Changelog

| Datum | Wijziging |
|---|---|
| 2026-08-18 | Branche-stap + distinctiveness-check (categorietaal + hygienefactoren); case-line prioriteit; `add_branche_column.sql` |
| 2026-08-19 | Bedrijfsomschrijving + contextprofiel (§10.2); description_alignment in case-line; `add_scan_context_fields.sql` |
| 2026-08-19 | Narrative polish: prompt + vangnet voor "(implicit in …)"-parentheses; `scripts/test-narrative-polish.php` |
| 2026-08-19 | Herhaalbaarheid beste post: top_posts + narrative repeatability-check; peak_unused-tekst aangepast |
| 2026-08-19 | Repeatability: core_message-keuze + reinforces_positioning (ja/nee) in mail-tekst |
| 2026-08-09 PM | Identity design doc + CSS (`docs/igisnext-identity.*`); app.css refactor; backup → `linkedin-analyse-latest.zip`; BUILD bijgewerkt |
| 2026-08-09 AM | Scan-historie + delete; CE fields/pills/checkbox; forgot password; SaveNotice flash; auth-tabellen |
| 2026-08-09 | Narratief-laag live; verkopende intro; sentimentbolletjes; Eva-login; budget €0,10; geen underlines; cache/refresh-fixes |
| 2026-08-09 | Eerste productie-deploy; Apify + analyzer + rapport + CE-app-UI |
| 2026-08-08 | Briefing + skelet; GrowthElements-rapportdesign |

---

## Open / backlog

- Geen publieke API — tool blijft achter login
- `raw_data` 90 dagen; metrics-fragmenten + `report_token` 180 dagen — scan-metrieken (cijfers) blijven staan
- GrowthElements-fixture geeft narrative `hoog` (uniforme testposts); echte Apify-data eerder `gemiddeld`
- Briefing `cursor-linkedin-scan-tool.md` §5.1 noemt nog underlines — bewust verwijderd in implementatie
- Identity CSS in CE React nog niet geïmporteerd — tokens blijven in `frontend/src/index.css` (zelfde waarden)
