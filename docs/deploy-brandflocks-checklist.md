# Deploy Brandflocks — checklist (weekplan stap 12, uitgewerkt)

**Wanneer:** na herontwerp-review op branch `herontwerp-2026-11`, niet vóór gecommitte campagne-basis.

**Script:** `./scripts/deploy-ftp.sh brandflocks` (eigen subdomein / FTP-account).

**Bf-staging op Antagonist:** `brandflocks-target` — subdomein `staging.brandflocks.nl`, eigen DB + FTP → `./scripts/bootstrap-env-brandflocks-target.sh`, daarna `./scripts/deploy-ftp.sh brandflocks-target`.

## 1. Omgeving aanmaken

```bash
cp .env.brandflocks.antagonist-secrets.example .env.brandflocks.antagonist-secrets
# Vul FTP_PASSWORD en DB_PASS uit DirectAdmin (Antagonist Plus).
./scripts/bootstrap-env-brandflocks.sh
# Of handmatig: cp .env.brandflocks.example .env.brandflocks en alles invullen.
./scripts/check-env-isolation.sh brandflocks
```

De isolatiecheck weigert wanneer DB, FTP-doel, publieke URL, reset-token, credential-key of webhooks **gelijk** zijn aan productie, CE-staging **of** `app.brandflocks.nl`.

### Bf-staging (`brandflocks-target`)

In DirectAdmin (zelfde Antagonist-account als app):

1. Subdomein **`staging.brandflocks.nl`** (document root `…/domains/staging.brandflocks.nl/public_html/`).
2. MariaDB-database + gebruiker (bijv. `deb2003839n1_brandflocks_staging`).
3. FTP-account **`staging@brandflocks.nl`** geworteld op die `public_html` → in `.env`: `FTP_REMOTE_PATH=/`.

```bash
cp .env.brandflocks-target.antagonist-secrets.example .env.brandflocks-target.antagonist-secrets
# vul FTP_PASSWORD, DB_PASS
./scripts/bootstrap-env-brandflocks-target.sh
./scripts/check-env-isolation.sh brandflocks-target
./scripts/deploy-ftp-probe.sh brandflocks-target
./scripts/deploy-ftp.sh brandflocks-target
./scripts/run-migration.sh brandflocks-target
./scripts/setup-brandflocks-n8n.sh staging
```

OAuth redirect-URI’s (Google/LinkedIn): voeg `https://staging.brandflocks.nl/...` toe naast app.

## 1b. n8n (eigen CE-kopie voor Bf)

```bash
./scripts/setup-brandflocks-n8n.sh        # app + Bf-staging workflows
# Zie docs/n8n-brandflocks.md
```

## 2. Deploy

```bash
./scripts/deploy-ftp.sh brandflocks
```

Optioneel vooraf: `./scripts/deploy-ftp-probe.sh brandflocks` (zodra de probe het env ondersteunt).

## 3. Database: baseline of bestaande DB

**Nieuwe (lege) database:** importeer eerst `web/schema/brandflocks-baseline.sql` (één consolidated schema, Ronde F).

**Bestaande CE-import:** baseline overslaan; eenmalig `POST /api/admin/schema-migrations-init` op die omgeving zet alle pre-baseline migratiebestandsnamen als reeds toegepast (zodat `--all` alleen nieuwe root-migraties pakt).

## 4. Migraties (ná deploy)

```bash
./scripts/run-migration.sh brandflocks
```

Deploy synct `web/migrations/` naar de server (actieve migraties, niet `archive/`).

## 5. Branchebibliotheek

```bash
# vanaf weekplan — eenmalig per omgeving, op de server (lokaal is DB_HOST=localhost niet bereikbaar)
php scripts/import-branchebibliotheek.php
php scripts/import-branchebibliotheek.php --dry-run   # controle zonder writes
```

Bronmap: `docs/Branchebibliotheek-voor-Erik-2nov/` of `--dir=...`. Catalogus-CSV staat ook in `web/data/branchelijst.csv` (102 labels, fallback als de tabel nog leeg is). JSON-hygiëne voor de 20 gecureerde branches komt pas ná deze import in `branche_library`.

**Op de server** (zonder SSH), na deploy van `web/data/Branches/`:

```bash
curl -sS -X POST -H "X-CE-Reset-Token: $CE_RESET_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{}' "$CE_PUBLIC_BASE_URL/api/admin/import-branchebibliotheek"
```

## 6. Rooktest (handmatig)

- Inloggen op `CE_PUBLIC_BASE_URL`
- Eén artikel-flow (thema → onderzoek → detail)
- Eén campagne-flow (concept → bevestigen → agenda)
- `./scripts/smoke-test.sh brandflocks` zodra het env-argument ondersteund wordt

## 7. Geen staging/productie-sleutels

- `.env.brandflocks` staat in `.gitignore`; nooit committen.
- Secrets die per ongeluk in code belanden: verwijderen en roteren (weekplan stap 12).
