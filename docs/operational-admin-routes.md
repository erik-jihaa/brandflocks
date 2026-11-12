# Operationele `/admin/*`-routes (ronde C)

Bindende lijst voor Brandflocks na fork-opschoning. Alles wat **niet** hier staat (of onder uitzonderingen) hoort niet in `web/api/index.php`.

Bron: `briefing/spec/cursor-plan-fork-opschonen-brandflocks-2026-11-05.md` § Ronde C, aangevuld met repo-scripts.

## Kern (fork-plan)

| Route | Doel |
|-------|------|
| `db-dump` | DB-backup via HTTPS (`scripts/backup-db.sh`) |
| `wp-diagnose` | WP-koppeling diagnosticeren |
| `theme-refill` | Thema-voorraad aanvullen |
| `run-migration` | Migraties draaien (`scripts/run-migration.sh`) |
| `schema-migrations-init` | Migratie-register initialiseren |
| `schema-export` | Schema dump (`scripts/export-production-schema.sh`) |
| `mailbox-sync` | Mailbox → bronnen (cron/n8n op server) |
| `analytics-sync` | Analytics sync (cron/n8n) |
| `scraper-sync` | Scraper sync (cron/n8n) |
| `llm-visibility-sync` | LLM visibility sync (cron/n8n) |
| `poll-n8n-failures` | n8n failures → event_log (`scripts/poll-n8n-failures.sh`, `cron-daily.sh`) |
| `ce02-trigger` | CE-02 handmatig/webhook |
| `agenda-repair` | Agenda-herstel |
| `list-slots` | Slot/tenant-overzicht (`smoke-test.sh`, tenant-tests) |
| `debug-research` | Research-debug per slot |
| `reset-content` | Volledige content-reset |
| `reset-client-content` | Per-tenant reset |
| `create-client` | Nieuwe tenant |
| `update-client` | Tenant bijwerken |
| `delete-client` | Tenant verwijderen |
| `delete-asana-sources` | Alle Asana-bronnen (`category = asana`) wissen |
| `export-tenant` | Tenant export |
| `import-tenant` | Tenant import |
| `set-password` | Klant-login wachtwoord |
| `set-platform-password` | Platform wachtwoord |
| `create-platform-user` | Platform-gebruiker |
| `events` | Event_log + retentie (`scripts/prune-event-log.sh`) |
| `hygiene` | Hygiene job (cron/n8n) |
| `import-support-emails` | Support-mail import (`scripts/import-support-emails.sh`) |

## Repo-extensies (blijven tot expliciet anders)

| Route | Reden |
|-------|------|
| `prune-support-service` | Support-chat retentie — `scripts/prune-support-service.sh`, `cron-daily.sh` |
| `wordpress-db` | WP DB scheiden — `scripts/separate-wordpress-database.sh` |
| `hba-article-hint-slot` | CE-03 n8n — `scripts/generate-ce03-workflow.py` (verwijderen pas na workflow-wijziging) |

## Verwijderd in ronde C (geen script/n8n-ref)

- `republish-wp` — handmatige republish; productie gebruikt normale publish-flow
- `hba-status` — HBA-pilot diagnostiek
- `hba-ensure-guardrail` — eenmalige guardrail-bootstrap

## Server-check (Erik)

Crontab/cPanel en n8n op de **hosting** staan niet in git. Vul `docs/inventaris-dode-code.md` §5 aan als een sync-route daar niet meer wordt aangeroepen — dan mag die alsnog weg.
