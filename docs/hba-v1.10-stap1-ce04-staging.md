# HBA v1.10 — stap 1 (deel A): CE-04 bewijs staging

Datum: 2026-10-13

## CE-04 post 3 (via `/api/admin/hba-ce04-post3`)

Trigger + poll (~90s): alle drie testmerken leveren een **regel-feedbackregel** met citaat:

| Merk | Regel (samenvatting) | Citaat |
| --- | --- | --- |
| Brandpunt | geen oudere voorraad met korting | Black Friday: alle zakken van vorige maand… |
| Duinroos | geen wachtlijst via invalkrachten | door de inzet van invalkrachten… |
| Stelwerk | geen resellerpartnerschap | Stelwerk is nu officieel gecertificeerd resellerpartner… |

Geen `[CE-04] Review mislukt` / `tool-output niet gelezen` in deze run.

Script: `./scripts/run-hba-r0-ce04-post3-staging.sh 90` (trigger + client-side poll; vereist deploy met `pollOnly=1`).

Bewijs-JSON: `docs/hba-v1.10-stap1-ce04-post3-staging.json`.

## Controlecall (6×5)

Lokaal gedraaid (zelfde model/prompt als runtime): **6/6 cases pass**, verboden logica mee — geen stap-1.4 aanpassing nodig.

JSON: `docs/hba-v1.10-stap1-constraint-breach-verificatie-5x6.json`.

Endpoint na deploy: `/api/admin/hba-constraint-breach-verificatie-test?runs=5`.

## Opmerking

Eerdere JSON (`hba-v0-testresultaat.json`, 13 okt 10:26 UTC) kan nog oude CE-04-parsefouten tonen; live staging + huidige n8n-workflow (Build Feedback Rows → `$('Claude Review')`) levert wel regel + citaat in feedback (geen `tool-output niet gelezen`).
