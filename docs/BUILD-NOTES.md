# Brandflocks — BUILD-NOTES

**Repo:** [github.com/erik-jihaa/brandflocks](https://github.com/erik-jihaa/brandflocks)  
**Afgesplitst van CE:** tag `bf-forkpunt` op [CE-Cursor](https://github.com/erik-jihaa/CE-Cursor) (`53b3173`). CE (`ce.igisnext.com`) blijft de pilot- en naslagrepo.

## Status

| Fase | Wat |
|------|-----|
| Fork + vangnet | `./scripts/test-all.sh`, fixtures in `tests/fixtures/` |
| Inventaris | `docs/inventaris-dode-code.md` |
| Opschonen | ronde D gaat door (§ stap 5 integraties open); web/api pilot-testmerken weg — `briefing/spec/cursor-plan-fork-opschonen-brandflocks-2026-11-05.md` |
| Herontwerp | `briefing/spec/cursor-weekplan-herontwerp-2026-11-03.md` |

## Deploy

Checklist: `docs/deploy-brandflocks-checklist.md`  
Design tokens: `docs/igisnext-identity.md` (naam/huisstijl wordt Brandflocks-whitelabel).

## Changelog (Brandflocks-repo)

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
