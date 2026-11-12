# BrandFlocks — applicatie (fork)

PHP/React voor **app.brandflocks.nl** en **staging.brandflocks.nl** — **standalone** t.o.v. CE (`ce.igisnext.com`).

**Structuur & infra:** [docs/BRANDFLOCKS-STANDALONE.md](docs/BRANDFLOCKS-STANDALONE.md)

| Map | Inhoud |
|-----|--------|
| `web/` | PHP API + static deploy |
| `frontend/` | Vite/React |
| `scripts/` | deploy, migraties, n8n |
| `json/n8n/` | Workflow-JSON → push naar n8n (Brandflocks-map) |
| `docs/` | deploy-checklist, n8n, BUILD-NOTES |
| `briefing/spec/` | actieve specs (subset) |

**Omgevingen:** alleen `.env.brandflocks` en `.env.brandflocks-target` in **deze map** (niet committen).

```bash
./scripts/check-env-isolation.sh brandflocks
./scripts/verify-standalone-infra.sh brandflocks
./scripts/deploy-ftp.sh brandflocks
```

Bovenliggende Dropbox-map `BrandFlocks/` = design (`HTML/`, `ill/`, `svg/`) en briefing-spiegel — geen tweede deploy-bron.

Optioneel: symlink `BrandFlocks/.env.brandflocks` → `brandflocks/.env.brandflocks`. Geen routine deploy vanuit `CE-Cursor`.
