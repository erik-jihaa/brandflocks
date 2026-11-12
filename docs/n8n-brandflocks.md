# n8n — BrandFlocks (app + Bf-staging)

## Twee omgevingen

| Deploy-profiel | `.env` | Publieke URL | `N8N_WEBHOOK_SUFFIX` | Workflow-label in n8n |
|----------------|--------|--------------|----------------------|------------------------|
| **app (productie Bf)** | `.env.brandflocks` | `https://app.brandflocks.nl` | `-brandflocks` | `CE-… [Bf app]` |
| **Bf-staging** | `.env.brandflocks-target` | `https://staging.brandflocks.nl` | `-brandflocks-staging` | `CE-… [Bf staging]` |

CE-productie (`ce.igisnext.com`) en CE-staging blijven hun eigen workflows (`-staging` / geen suffix).

## Variables in n8n

**Niet vereist.** Op een plan zonder n8n Variables worden `CE_PUBLIC_BASE_URL` en `CE_RESET_TOKEN` bij `./scripts/push-*-n8n.sh` / `push-n8n-workflow.py` vanuit `.env.brandflocks` in de workflow geïnjecteerd. Na wijziging van token: opnieuw pushen, geen Variables-tab.

## Map in n8n (los van Content Engine)

BrandFlocks-workflows horen in de **map `BrandFlocks`** in hetzelfde n8n-project als CE (niet in `ContentEngine`).

| Variabele | Gebruik |
|-----------|---------|
| `N8N_BF_FOLDER_ID` | Map voor **alle** Bf-pushes (`brandflocks` + `brandflocks-target`) |
| `N8N_CE_FOLDER_ID` | Alleen CE-profielen (`staging` / `production`) — **niet** in `.env.brandflocks*` |

Eenmalig (maakt map + zet id in beide `.env`-bestanden):

```bash
./scripts/ensure-n8n-bf-folder.py
```

Herkenning in de UI: naam `CE-… [Bf app]` / `[Bf staging]`, webhook `…-brandflocks` / `…-brandflocks-staging`.

## Eenmalige setup

```bash
./scripts/setup-brandflocks-n8n.sh   # roept ensure-n8n-bf-folder.py eerst aan
```

Of alleen app of alleen Bf-staging: `./scripts/setup-brandflocks-n8n.sh app` | `staging`.

Daarna deploy zodat `ce.local.php` de webhook-URL’s krijgt:

```bash
./scripts/deploy-ftp.sh brandflocks
./scripts/deploy-ftp.sh brandflocks-target   # optioneel
```

## MySQL-credentials in n8n

Maak in n8n **twee** MySQL-credentials:

1. **Brandflocks [app]** → Antagonist DB (`deb2003839n1_brandflocks_app`)
2. **Brandflocks [Bf staging]** → Antagonist DB (`deb2003839n1_brandflocks_staging` o.i.d.)

Zet `N8N_MYSQL_CREDENTIAL_ID` + `N8N_MYSQL_CREDENTIAL_NAME` in het bijbehorende `.env.*`.

Gebruik **niet** het ID van `Content Engine [staging]` — dat is een andere database (CE-02 faalt dan met *Theme or slot not found*).

**Host voor n8n Cloud:** `brandflocks.nl` (niet `localhost`). In DirectAdmin → MariaDB/MySQL → **Remote access** het IP van n8n toestaan (zie fout in n8n-executie: `Access denied for user … @'x.x.x.x'`). Zonder remote access werkt alleen de app-PHP; workflows CE-01/02/03/04 niet.

## Anthropic (Claude HTTP-nodes)

CE-02/03/04 gebruiken **httpHeaderAuth** `Header Auth account` (`x-api-key`), niet alleen `ANTHROPIC_API_KEY` in PHP. Als n8n-executies eindigen met *Authorization failed - please check your credentials*, staat de key in n8n verlopen of leeg — de app kan nog wél HBA via PHP draaien.

Sync vanuit `.env.brandflocks`:

```bash
CE_ENV=brandflocks python3 ./scripts/sync-n8n-anthropic-header.py
```

Optioneel: `N8N_ANTHROPIC_HEADER_CREDENTIAL_ID` / `N8N_ANTHROPIC_HEADER_CREDENTIAL_NAME` als de credential anders heet.

## Isolatie

```bash
./scripts/check-env-isolation.sh brandflocks
./scripts/check-env-isolation.sh brandflocks-target
```

Webhook-URL’s moeten per omgeving uniek zijn (`update-env-n8n-webhooks.py` na elke push).
