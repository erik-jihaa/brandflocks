# Platform-architectuur: tenants, dev→productie release, ontwikkelaarsdashboard

**Doel:** ContentFlow als **platform** draaien voor meerdere betalende tenants, met
(1) veilige tenant-isolatie, (2) een ontwikkelomgeving van waaruit upgrades gecontroleerd
naar productie gaan, en (3) een ontwikkelaarsdashboard met per-tenant inzicht in
gebruik, problemen, servicevragen en kosten.

Dit brief beschrijft de doelarchitectuur en de weg ernaartoe. Het is bewust
gefaseerd: **fase 1 is de minimale veilige basis**, fase 2-3 zijn uitbreidingen die
pas lonen bij meer tenants.

---

## 0. Waar we nu staan (geverifieerd in de code)

| Onderdeel | Huidige stand |
|---|---|
| **Omgevingen** | **Eén** omgeving: `ce.igisnext.com`. Geen staging/dev-omgeving. `.env` bevat één set credentials, één `DB_*`, één `FTP_*`. |
| **Deploy** | `./scripts/deploy-ftp.sh`: bouwt frontend → merged `dist/` in `web/` → schrijft `web/includes/db.local.php` uit `.env` → FTP-upload (`deploy_ftp_upload.py`, modes `full`/`quick`). Direct naar productie, geen tussenstap. |
| **Tenant-isolatie** | Op **rij-niveau**: alles hangt aan `clients.id` (`client_id`-kolom op vrijwel elke tabel). Eén gedeelde MySQL-database. Sessie bepaalt actieve `ce_client_id`. |
| **Tenant aanmaken** | `./scripts/create-client.sh` → `POST /api/admin/create-client`; `provision-tenant.sh` kopieert sources/wachtwoord van een bron-tenant. |
| **Schema** | ✅ `docs/schema.sql` is nu een echte productie-export (34 tabellen), `schema_migrations`-tabel bestaat, `GET /api/admin/schema-export` + `./scripts/export-production-schema.sh`. |
| **CORS** | ✅ Allowlist via `ce_allowed_origins()` / `CE_ALLOWED_ORIGINS`, niet meer wildcard. |
| **Kosten** | `web/api/tenant-cost-repository.php` + `/support/costs`: rekent per tenant per maand (Apify werkelijk uit `scrape_runs.cost_eur`; Claude/n8n geschat op basis van tarieven). |
| **Support-chat** | `support_chat_usage`-tabel: per tenant tokens, `cost_eur`, `message_count`, budget-guard (`CE_SUPPORT_CHAT_BUDGET_EUR`). |
| **Health** | `GET /api/health` — alleen DB-connectie + IMAP-beschikbaarheid. |
| **Foutlogging** | ⚠️ **Vrijwel afwezig.** Eén `error_log`-aanroep in de hele API (`password-reset-repository.php`). Fouten worden opgevangen met stille `catch (Throwable) {}` en verdwijnen. |

### Drie architecturele problemen die dit blokkeren

1. **Geen scheiding tussen ontwikkelen en klanten bedienen.** Elke `deploy-ftp.sh` raakt
   direct de omgeving waarin betalende tenants werken. Bij één klant is dat te
   overzien; bij vijf is één slechte deploy een incident voor iedereen tegelijk.
2. **WordPress deelt de database met de applicatie.** De export bevat naast de 22
   CE-tabellen ook 12 `wpce_*`-tabellen (WordPress core). Dat betekent dat een
   WordPress-plugin, -update of -compromittering in dezelfde database zit als alle
   klantdata. Dit is het grootste veiligheids- en herstelrisico in de huidige opzet.
3. **Problemen zijn onzichtbaar tot een klant belt.** Er is geen foutlog, geen
   activiteitenlog, geen alarmering. De stille `catch (Throwable) {}`-patronen (nuttig
   voor robuustheid) maken dat een falende n8n-webhook of mislukte publicatie nergens
   wordt geregistreerd.

---

## 1. Doelarchitectuur — drie omgevingen

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│  LOKAAL (dev)   │ --> │   STAGING       │ --> │   PRODUCTIE     │
│  vite + php -S  │     │ dev.ce.igis…    │     │ ce.igisnext.com │
│  eigen DB-kopie │     │ eigen DB        │     │ tenant-DB       │
│  n8n: dev-flows │     │ n8n: dev-flows  │     │ n8n: prod-flows │
└─────────────────┘     └─────────────────┘     └─────────────────┘
      commit                git tag              handmatige release
```

**Kernregel:** productie wordt **nooit** direct vanaf een werkkopie gedeployed. Alleen
vanaf een getagde commit die op staging is geverifieerd.

### 1.1 Wat er per omgeving verschilt

Alles wat per omgeving verschilt zit al in `.env` — er is dus geen codewijziging nodig,
alleen **meerdere env-bestanden**:

| Bestand | Doel |
|---|---|
| `.env` | Lokaal (bestaand gedrag, ongewijzigd) |
| `.env.staging` | Staging: eigen `DB_*`, eigen `FTP_*` (subdomein), eigen `CE_PUBLIC_BASE_URL`, eigen `CE_ALLOWED_ORIGINS`, **eigen n8n-webhooks** |
| `.env.production` | Productie: huidige waarden |

`deploy-ftp.sh` krijgt een omgevingsargument:

```bash
./scripts/deploy-ftp.sh staging      # leest .env.staging
./scripts/deploy-ftp.sh production   # leest .env.production, vraagt bevestiging
```

Implementatie: in `deploy-ftp.sh` de harde `$ROOT/.env` vervangen door
`$ROOT/.env.${ENV:-}` met fallback naar `.env`; `deploy_ftp_upload.py` en het
`db.local.php`-genererende blok krijgen datzelfde pad mee (beide accepteren het
env-pad al als argument, dus dit is een kleine wijziging).

### 1.2 Nieuwe env-variabele: `CE_ENV`

Voeg toe aan alle env-bestanden: `CE_ENV=local|staging|production`.

Gebruik:

- **Zichtbare omgevingsbanner** in de frontend bij `staging` (voorkomt "ik zat per ongeluk in de verkeerde omgeving").
- **Guard op destructieve endpoints**: `admin-reset-content`, `admin-delete-client`, `content-reset` weigeren te draaien als `CE_ENV=production` zonder een extra expliciete bevestigingsparameter. Dit adresseert direct het mass-delete-incident uit `BUILD-NOTES.md` § Open risico's.
- **Logregels** taggen met de omgeving (zie § 3).

### 1.3 Staging-database vullen

Nieuw script `./scripts/sync-staging-from-production.sh`:

1. Schema uit productie halen (hergebruikt de bestaande `export-production-schema.sh`).
2. Schema toepassen op staging-DB.
3. **Geanonimiseerde** seed: één demo-tenant met realistische structuur maar zonder klantdata — geen echte e-mailadressen, geen echte OAuth-tokens, geen echte WordPress-wachtwoorden.

**Expliciet niet:** productiedata één-op-één naar staging kopiëren. Dan staan
klant-tokens en artikelen op een tweede plek en is het datalek-oppervlak verdubbeld.

---

## 2. Release-pijplijn: hoe upgrades naar tenants gaan

### 2.1 Stappen

| # | Stap | Commando / actie |
|---|---|---|
| 1 | Ontwikkelen | Lokaal, tegen lokale DB-kopie |
| 2 | Committen | Git; migraties als los `.sql`-bestand in `scripts/migrations/` |
| 3 | Staging deployen | `./scripts/deploy-ftp.sh staging` |
| 4 | Migraties draaien op staging | `./scripts/run-migration.sh staging <file.sql>` (registreert in `schema_migrations`) |
| 5 | Verifiëren | Smoke-test (§ 2.3) op staging |
| 6 | Taggen | `git tag release-YYYY-MM-DD` |
| 7 | **Productie** | `./scripts/deploy-ftp.sh production` — vraagt expliciete bevestiging, weigert bij niet-gecommitte wijzigingen |
| 8 | Migraties productie | `./scripts/run-migration.sh production <file.sql>` |
| 9 | Schema-export bijwerken | `./scripts/export-production-schema.sh` + commit |

### 2.2 Migraties eerst, code daarna — of andersom?

Regel: **additieve migraties vóór de code-deploy** (nieuwe tabel/kolom toevoegen kan
geen kwaad als de oude code die kolom nog niet gebruikt). **Destructieve migraties**
(kolom verwijderen/hernoemen) pas ná de deploy, in een aparte stap — anders breekt de
oude code tussen migratie en upload.

Het bestaande auto-schema-patroon (`ce_table_has_column()` + `ALTER`) blijft als
vangnet: als een migratie vergeten wordt, herstelt de API zichzelf. Dat vangnet is
geen vervanging voor de migratie zelf — anders loopt het schema opnieuw weg van
`docs/schema.sql`.

### 2.3 Smoke-test na elke deploy

Nieuw script `./scripts/smoke-test.sh <staging|production>` dat na deploy automatisch draait:

| Check | Verwacht |
|---|---|
| `GET /api/health` | `ok: true`, `db: connected` |
| `GET /api/clients` (met reset-token) | ≥1 client, HTTP 200 |
| `GET /api/agenda` voor testtenant | HTTP 200, geen PHP-fout in body |
| `GET /api/admin/wp-diagnose?client_id=…` | `authenticatedAs` gevuld |
| Frontend `index.html` | Bevat de verwachte asset-hash uit de build (detecteert half-gelukte FTP-upload) |

Die laatste is belangrijk: de huidige FTP-deploy heeft geen enkele controle of alle
bestanden zijn aangekomen.

### 2.4 n8n-workflows meenemen in releases

n8n is nu de zwakste schakel in de release: workflows worden los gepusht
(`push-ce01-n8n.sh` etc.) en er is één n8n-instance voor alles.

**Fase 1 (minimaal):** `regenerate-all-ce-workflows.sh` + `push-all-ce-n8n.sh`
opnemen als expliciete stap in de release-checklist, met een versienotitie in
`BUILD-NOTES.md`. De workflow-JSON's worden al gegenereerd uit Python-scripts in git
— dat is de bron van waarheid, goed zo.

**Fase 2:** aparte n8n-folder (of tweede instance) voor dev-workflows, met de
webhook-URL's per omgeving in `.env.staging` vs `.env.production`. De code leest die
URL's al uit env/`ce.local.php`, dus dit vergt geen codewijziging — alleen andere
waarden en een tweede set workflows in n8n.

---

## 3. Observability: van "klant belt" naar "wij zien het eerst"

Dit is de grootste functionele lacune. Zonder dit is het dashboard uit § 4 leeg.

### 3.1 Nieuwe tabel: `event_log`

```sql
CREATE TABLE IF NOT EXISTS event_log (
  id CHAR(36) NOT NULL PRIMARY KEY,
  client_id CHAR(36) NULL,               -- NULL = platform-breed
  severity VARCHAR(10) NOT NULL,          -- info | warn | error
  category VARCHAR(50) NOT NULL,          -- publish | n8n | oauth | scraper | mailbox | auth | schema
  event VARCHAR(100) NOT NULL,            -- bv. 'wp_publish_failed'
  message TEXT NULL,
  context JSON NULL,                      -- slot_id, http-status, endpoint, …
  environment VARCHAR(20) NOT NULL,       -- uit CE_ENV
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client_created (client_id, created_at),
  KEY idx_severity_created (severity, created_at),
  KEY idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Helper in nieuw bestand `web/api/event-log-repository.php`:

```php
ce_log_event($pdo, 'error', 'publish', 'wp_publish_failed', $message, [
    'clientId' => $clientId, 'slotId' => $slotId, 'httpStatus' => $code,
]);
```

**Waar aanroepen (minimaal, fase 1):**

| Plek | Event |
|---|---|
| `publish-repository.php` — WP-publicatie faalt | `wp_publish_failed` |
| `publish-repository.php` — LinkedIn-post faalt | `linkedin_publish_failed` |
| `theme-repository.php` — `ce_trigger_ce01_theme_director()` niet-ok | `n8n_webhook_failed` |
| `articles-submit.php` — CE-04-webhook faalt | `n8n_webhook_failed` |
| `linkedin-oauth-helper.php` — token verlopen/refresh mislukt | `oauth_expired` |
| `scrape-repository.php` — Apify-run faalt of budget op | `scraper_failed` / `scraper_budget_exceeded` |
| `mailbox-helper.php` — IMAP-sync faalt | `mailbox_sync_failed` |

**Belangrijk:** de bestaande stille `catch (Throwable) {}`-blokken hoeven niet te
verdwijnen (ze houden de app draaiend), maar krijgen er één regel bij: log het event.
Robuust blijven én zichtbaar maken.

**Retentie:** dagelijkse of wekelijkse opruiming van `info`-events ouder dan 30 dagen;
`error` 12 maanden bewaren. Anders groeit deze tabel ongelimiteerd.

### 3.2 Activiteit per tenant: `tenant_activity_daily`

Voor "gebruikt deze klant het eigenlijk wel?" is een foutlog niet genoeg. Eén rij per
tenant per dag, bijgewerkt door de bestaande flows:

```sql
CREATE TABLE IF NOT EXISTS tenant_activity_daily (
  client_id CHAR(36) NOT NULL,
  activity_date DATE NOT NULL,
  logins INT NOT NULL DEFAULT 0,
  articles_created INT NOT NULL DEFAULT 0,
  articles_published INT NOT NULL DEFAULT 0,
  themes_reviewed INT NOT NULL DEFAULT 0,
  api_requests INT NOT NULL DEFAULT 0,
  errors INT NOT NULL DEFAULT 0,
  PRIMARY KEY (client_id, activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Bijwerken via `INSERT … ON DUPLICATE KEY UPDATE kolom = kolom + 1` op de relevante
plekken (login-handler, article-insert, publish-succes, theme-submit). Goedkoop,
geen extra query per request.

**Afgeleide signalen voor het dashboard:** dagen sinds laatste login, dagen sinds
laatste publicatie, artikelen deze maand versus `posts_per_week × weken`
(= "haalt de klant het afgesproken ritme?").

### 3.3 Servicevragen

Er zijn nu twee bronnen die je aan elkaar wilt kunnen zien:

- `support_chat_usage` — bestaat al, maar bevat **alleen totalen** (tokens, kosten, aantal). Je ziet niet *waar* klanten over struikelen.
- E-mail/telefoon — nergens vastgelegd.

**Voorstel:** breid support-chat uit met een log van de gestelde vragen
(`support_chat_messages`: client_id, vraag, tijdstip, en optioneel een
categorie-label). Dat maakt een top-10 "waar lopen klanten tegenaan" mogelijk — direct
input voor je backlog. Voeg daarnaast in het dashboard een simpel handmatig
`service_tickets`-veld toe (client_id, onderwerp, status, notitie) zodat mail- en
telefoonvragen op dezelfde plek landen. Bewust simpel: geen ticketsysteem bouwen,
alleen genoeg om patronen te zien.

**Privacy:** vragen van klanten opslaan is persoonsgegevensverwerking — leg vast
hoelang je ze bewaart en noem het in je verwerkersovereenkomst.

---

## 4. Ontwikkelaarsdashboard

**Route:** `/platform` (of `/admin/platform`) — alleen toegankelijk met
platform-beheerdersrechten, **niet** met een gewone tenant-login.

### 4.1 Toegangsbeveiliging

De huidige `CE_RESET_TOKEN`-header is prima voor scripts, maar ongeschikt als
enige beveiliging voor een dashboard in de browser.

**Voorstel:** kolom `clients.is_platform_admin` (of een aparte `platform_users`-tabel
als beheerders geen tenant zijn). Guard: sessie moet ingelogd zijn **én** de vlag
hebben. Reset-token blijft bestaan voor scripts/CLI.

Let op: `CE_AUTH_DISABLED=1` (testfase) moet dit dashboard **nooit** openzetten —
hardcoded uitzondering.

### 4.2 Schermindeling

**Overzicht (alle tenants, één tabel — de landingsview):**

| Kolom | Bron |
|---|---|
| Tenant | `clients.name` |
| Status | groen/oranje/rood op basis van errors (7d) + laatste activiteit |
| Laatste login | `tenant_activity_daily` |
| Artikelen deze maand | `articles` per client |
| Ritme | gepubliceerd vs. `posts_per_week × weken` |
| Fouten (7d) | `event_log` severity=error |
| Kosten deze maand | `ce_tenant_cost_report()` — **bestaat al** |
| Publish-mode | `clients.publish_mode` |

**Detail per tenant (klik door):**

| Blok | Inhoud |
|---|---|
| **Gebruik** | Grafiek activiteit (30d), laatste login/publicatie, ritme vs. afspraak |
| **Problemen** | `event_log` gefilterd op deze client, nieuwste eerst, met context-JSON uitklapbaar |
| **Servicevragen** | Support-chat-vragen + handmatige tickets |
| **Kosten** | Hergebruik `GET /api/tenant-costs` — werkelijke Apify + geschatte Claude/n8n, uitgesplitst |
| **Configuratie** | Snelle diagnose: WP gekoppeld?, LinkedIn OAuth geldig/verlopen?, aantal actieve bronnen, objectives ingesteld? |
| **Acties** | Bestaande admin-endpoints als knoppen: theme-refill, wp-diagnose, mailbox-sync, scraper-sync — met bevestiging |

**Platform-breed:**

- Foutenfeed over alle tenants (severity=error, 24h) — je startpunt 's ochtends.
- Deploy-status: huidige release-tag, laatst toegepaste migratie uit `schema_migrations`, `CE_ENV`.
- Totale kosten alle tenants deze maand vs. vorige maand.

### 4.3 API-endpoints (nieuw)

```
GET /api/platform/tenants                 → overzichtstabel (alle tenants + kerncijfers)
GET /api/platform/tenants/{id}            → detail (gebruik, config, laatste events)
GET /api/platform/events?severity=&days=  → foutenfeed, filterbaar
GET /api/platform/costs?month=YYYY-MM     → alle tenants (wrapper om bestaande tenant-costs)
GET /api/platform/status                  → release-tag, migratiestand, CE_ENV, health
```

Alle onder dezelfde platform-admin-guard.

### 4.4 Bouwen als artifact of als scherm?

Voor de eerste versie is een **React-scherm in de bestaande frontend** het meest
logisch: je hebt de auth-context, de componenten en de routing al. Overweeg pas een
losse tool als het dashboard functionaliteit krijgt die niets met de app te maken heeft.

---

## 5. Tenant-isolatie: wat nu goed is en wat aandacht vraagt

**Goed:** de rij-gebaseerde isolatie (`client_id` overal, `ce_client_id_for_slot()`,
tokens per client in `clients`) is consequent doorgevoerd — dat is in de
LinkedIn-multi-tenant-review al vastgesteld en ik zag geen tegenvoorbeelden.

**Aandachtspunten:**

| Punt | Risico | Aanpak |
|---|---|---|
| **WordPress in dezelfde DB** (`wpce_*`) | Grootste risico: WP-kwetsbaarheid raakt klantdata | WordPress verhuizen naar een **eigen database met een eigen DB-gebruiker**. Geen codewijziging in CE nodig — CE praat met WP via de REST API, niet via de database. |
| **Credentials in `clients`-kolommen** | WP-wachtwoord en FTP-wachtwoord staan als kolom in de tabel; `linkedin_*`/scraper-tokens zijn wél versleuteld (`ce_credential_encrypt`) | Trek dit gelijk: alles wat een wachtwoord/token is door dezelfde encryptie halen. |
| **Geen query-niveau garantie** | Eén vergeten `WHERE client_id = …` lekt data tussen tenants | Fase 2: een gedeelde helper die client-scope afdwingt, plus een testscript dat met twee tenants controleert of endpoints elkaars data teruggeven. |
| **Backups** | `backups/`-map bevat alleen env-backups | Geautomatiseerde dagelijkse DB-dump met retentie, buiten de webroot. Het mass-delete-incident liet zien dat editorial content niet terug te halen was. |

---

## 6. Voorgestelde bouwvolgorde

**Fase 1 — veilige basis (eerst dit, voordat er meer tenants bij komen)**

1. `CE_ENV` + env-bestanden per omgeving; `deploy-ftp.sh <env>`.
2. Staging-omgeving (subdomein + eigen DB) + seed-script met geanonimiseerde demo-tenant.
3. `event_log`-tabel + `ce_log_event()` + de zeven aanroeppunten uit § 3.1.
4. Guard op destructieve endpoints bij `CE_ENV=production`.
5. Dagelijkse DB-backup met retentie.

**Fase 2 — inzicht**

6. `tenant_activity_daily` + bijwerkpunten.
7. Platform-admin-guard (`is_platform_admin`).
8. Dashboard v1: overzichtstabel + foutenfeed + kosten (hergebruikt bestaande kostenmodule).
9. `smoke-test.sh` na deploy.

**Fase 3 — verdieping**

10. Detailweergave per tenant + admin-acties als knoppen.
11. Support-chat-vragen loggen + handmatige service-tickets.
12. WordPress naar eigen database.
13. Aparte n8n-webhooks per omgeving.
14. Cross-tenant isolatie-testscript.

---

## 7. Wat dit brief bewust níet voorstelt

- **Database per tenant.** Bij dit aantal tenants voegt dat vooral operationele last toe; de rij-isolatie is consequent doorgevoerd. Heroverwegen bij een klant met harde eisen (eigen datalocatie, aparte backup/retentie).
- **Kubernetes/containers/CI-runner.** De huidige FTP-deploy is simpel en werkt. Eerst staging + smoke-test; automatiseren pas als handmatig deployen echt knelt.
- **Externe monitoringdienst (Sentry, Datadog).** Kan later; `event_log` in de eigen database geeft nu al het benodigde inzicht zonder extra leverancier, kosten of verwerkersovereenkomst.
- **Een echt ticketsysteem.** Alleen genoeg registratie om patronen te zien.

---

## 8. Wijzigingsmatrix

| Bestand / component | Wijziging |
|---|---|
| `.env.staging`, `.env.production` | Nieuw; `.env.example` uitbreiden met `CE_ENV` |
| `scripts/deploy-ftp.sh` | Omgevingsargument, bevestiging bij productie, smoke-test aanroepen |
| `scripts/deploy_ftp_upload.py` | Env-pad al als argument — controleren, geen grote wijziging |
| `scripts/sync-staging-from-production.sh` | Nieuw (schema + geanonimiseerde seed) |
| `scripts/run-migration.sh` | Nieuw of uitbreiden met omgevingsargument |
| `scripts/smoke-test.sh` | Nieuw |
| `web/api/event-log-repository.php` | Nieuw (`ce_log_event`, auto-schema) |
| `web/api/publish-repository.php`, `theme-repository.php`, `articles-submit.php`, `linkedin-oauth-helper.php`, `scrape-repository.php`, `mailbox-helper.php` | Logregel toevoegen in bestaande foutpaden |
| `web/api/platform-repository.php` + `platform-*.php` | Nieuw (dashboard-endpoints) |
| `web/api/auth-guard.php` | Platform-admin-guard; uitzondering op `CE_AUTH_DISABLED` |
| `web/api/content-reset.php`, `admin-delete-client.php`, `admin-reset-content.php` | Guard op `CE_ENV=production` |
| `frontend/src/pages/PlatformPage.tsx` (+ subcomponenten) | Nieuw dashboard |
| `frontend/src/layout/` | Omgevingsbanner bij staging |
| `scripts/migrations/` | `add_event_log.sql`, `add_tenant_activity_daily.sql`, `add_platform_admin_flag.sql` |
| `docs/BUILD-NOTES.md` | Bijwerken na implementatie |
