# HBA — stap 1: architectuurpoort (§1)

**Branch:** `hba-ronde-4-v1.9` · **Ijkset:** v0.3

## Gedaan

- **`web/api/hba-architecture-poort.php`** — veldbevindingen `tegenkrachtOntbreekt` / `regelOntbreekt`, herberekend `totaaloordeel`, `meetlatStatus` (`actief` / `geblokkeerd`).
- **`hba-architectuur-check-repository.php`** — harde poort en identity-gate early exit verwijderd; check draait altijd; guardrail alleen bij `meetlatStatus === actief`.
- **`hba-identity-gate.php`** — lege tegenkracht = bevinding, geen stop.
- **Wizard UI** — `HbaArchitectureCheckPanel` toont meetlatstatus en veldadviezen.
- **Ijkrun** — `bereikt_tot`, meetlat uit `meetlatStatus`, judge op verplichte architectuur-signalen.

## Klaar als (Rogier-lijst)

| Geval | verwacht meetlat | Opmerking |
| --- | --- | --- |
| A01, A02, A03, A17, A18 | actief | Na LLM-run valideren op staging |
| A09, A12, A15 | geblokkeerd + inhoudelijk oordeel | Geen kale “vul eerst in”-melding |

Draai op staging: `?execute=1&id=A15` (en overige) via `/api/admin/hba-ijkrun`.
