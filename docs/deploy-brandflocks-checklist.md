# Deploy Brandflocks — checklist (weekplan stap 12, uitgewerkt)

**Wanneer:** na herontwerp-review op branch `herontwerp-2026-11`, niet vóór gecommitte campagne-basis.

**Script:** `./scripts/deploy-ftp.sh brandflocks` (zelfde build/upload-pad als staging).

## 1. Omgeving aanmaken

```bash
cp .env.brandflocks.example .env.brandflocks
# Vul FTP, DB, URL, tokens en n8n-suffix in — kopieer géén waarden uit .env of .env.staging.
./scripts/check-env-isolation.sh brandflocks
```

De isolatiecheck weigert wanneer DB, FTP-doel, publieke URL, reset-token, credential-key of webhooks **gelijk** zijn aan productie **of** aan staging.

## 2. Deploy

```bash
./scripts/deploy-ftp.sh brandflocks
```

Optioneel vooraf: `./scripts/deploy-ftp-probe.sh brandflocks` (zodra de probe het env ondersteunt).

## 3. Migraties

Op de Brandflocks-database, in volgorde (zelfde set als staging):

```bash
./scripts/run-migration.sh brandflocks
```

Controleer dat `web/migrations/` op de server overeenkomt met `scripts/migrations/` (deploy synct die map).

## 4. Branchebibliotheek

```bash
# vanaf weekplan — eenmalig per omgeving
php scripts/import-branchebibliotheek.php
```

(Draai tegen de Brandflocks-DB; gebruik de ZIP uit `briefing/hba-input/`.)

## 5. Rooktest (handmatig)

- Inloggen op `CE_PUBLIC_BASE_URL`
- Eén artikel-flow (thema → onderzoek → detail)
- Eén campagne-flow (concept → bevestigen → agenda)
- `./scripts/smoke-test.sh brandflocks` zodra het env-argument ondersteund wordt

## 6. Geen staging/productie-sleutels

- `.env.brandflocks` staat in `.gitignore`; nooit committen.
- Secrets die per ongeluk in code belanden: verwijderen en roteren (weekplan stap 12).
