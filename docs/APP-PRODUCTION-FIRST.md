# app.brandflocks.nl eerst — werkvolgorde

**Besluit:** `staging.brandflocks.nl` komt later. Alle energie naar **één stabiele productie-app**.

## Definitie “goed werkend”

| # | Criterium | Check |
|---|-----------|--------|
| 1 | Login + home | `./scripts/acceptance-brandflocks-e2e.py` → 0 fail |
| 2 | Campagne → bevestigen → agenda + **Activatie**-lancering | idem |
| 3 | HBA op pilottenants | Wizard/progress + guardrail |
| 4 | **CE-02** research op app | Fastlane met research, geen staging-URL in n8n |
| 5 | **CE-03/04** article + review | n8n + Anthropic/Gemini credentials groen |
| 6 | Mail uit | `erik@brandflocks.nl` / tenant mail |

```bash
cd brandflocks
export BF_ACCEPT_EMAIL=… BF_ACCEPT_PASSWORD=…
./scripts/acceptance-brandflocks-e2e.py
./scripts/verify-standalone-infra.sh brandflocks
```

## n8n (alleen app)

**Geen n8n Variables-abo nodig.** Waarden uit `.env.brandflocks` worden bij **push** in workflows gezet (`scripts/n8n_env.py`).

1. Wijzig token/URL in `.env.brandflocks`
2. **Push workflows** opnieuw:
   ```bash
   ./scripts/push-ce02-n8n.sh
   ./scripts/push-ce03-n8n.sh
   ./scripts/push-ce04-n8n.sh
   # of: ./scripts/push-all-ce-n8n.sh  (CE_N8N_TARGET=brandflocks)
   ```
3. **MySQL-credential** in n8n → Antagonist `deb2003839n1_brandflocks_app`
4. **Anthropic credential** → actuele API key (niet `ant auth login`)

Zie [n8n-brandflocks.md](./n8n-brandflocks.md).

## Deploy-app

```bash
./scripts/check-env-isolation.sh brandflocks
./scripts/deploy-ftp.sh brandflocks
./scripts/run-migration.sh brandflocks   # indien nieuwe migraties
```

## Bewust niet nu

- `brandflocks-target` / staging subdomain
- Sync vanaf `staging.igisnext.com`
- CE-Cursor deploy

*12 nov 2026*
