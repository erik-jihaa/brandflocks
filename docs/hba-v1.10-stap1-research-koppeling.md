# HBA v1.10 — stap 1 researchkoppeling (reactie Rogier 15 okt)

## Conclusie voor de pilot

| Vraag | Antwoord |
| --- | --- |
| Tenantlek? | **Nee.** Stelwerk kreeg in V0 **verkeerde URL’s** in `scripts/run-hba-v0-staging.py` (Black Friday + zorg-wachttijd), niet research van Duinroos uit de database. |
| `fetchSlotResearch` fout? | **Ja, bug in code.** `ce_fetch_slot_research()` gebruikte `ce_default_client_id()` (sessie/env). Onder **reset-token** zonder login faalde `ce_assert_slot_client_access` → `"Slot niet gevonden"`, terwijl `admin-debug-research` items wél via `slot_id` las. |
| Fix | Client afleiden uit **slot** (`ce_client_id_for_slot`); week-scope idem. |
| Isolatie | `scripts/test-hba-slot-research-isolation.py` — Stelwerk-slot via service-token: `fetchSlotResearch` groen, geen URL-overlap met Duinroos/Brandpunt. |

## Stelwerk V0-URL’s (correctie)

De drie Stelwerk-URL’s in `run-hba-v0-staging.py` zijn vervangen door ERP/MKB/inkoopmacht-gerelateerde bronnen (geen zorg, geen Black Friday).

**Volgende stap (niet in stap 1):** research uit merkbronnen in testmerk-config i.p.v. hardcoded lijsten (`hba-reactie-v1.10-en-ijkset-v0.2.md` § antwoord 2).

## Verificatie op staging

```bash
# Isolatie + fetchSlotResearch (reset-token)
./scripts/test-hba-slot-research-isolation.py staging

# Optioneel: volledige V0 opnieuw (CE-03 + JSON)
python3 scripts/run-hba-v0-staging.py
```

**Staging 15 okt 2026 (na deploy):** `./scripts/test-hba-slot-research-isolation.py staging` → 3× OK; `fetchSlotResearch.ok === true` op Stelwerk-slot `a49d2c72-fe04-4c0a-baa5-99854390b34d`.

Bewijs inhoud Stelwerk (geen zorg-URL’s in research): opnieuw `python3 scripts/run-hba-v0-staging.py` na URL-fix — nieuwe fastlane-slots krijgen ERP-bronnen.
