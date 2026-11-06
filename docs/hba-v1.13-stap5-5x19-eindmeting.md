# HBA v1.13 — stap 5 eindmeting (5×19, voltooid)

**Status:** **voltooid** — pilotcriteria **gehaald**.

| | |
|---|---|
| **Datum** | 22 oktober 2026 |
| **Omgeving** | staging (`https://staging.igisnext.com`) |
| **Prompt** | **v1.13** |
| **Model** | **claude-sonnet-4-6** (default staging `.env`, geen `HBA_MODEL_*` override) |
| **Run** | `./scripts/run-hba-testmerken-matrix.sh --runs 5` via staging-API |
| **Duur** | ~53 min (poging 3) |
| **Log** | `docs/hba-v1.13-stap5-5x19-run-attempt3.log` |
| **Import (optioneel)** | `docs/hba-v1.13-import-staging-2026-10-21.log` |

Eerdere pogingen: poging 1 (tegoed, okt 20), poging 2 (500 midden run 1, okt 21) — **tellen niet** als pilotmeting.

## Pilotcriteria (Rogier)

| Criterium | Doel | Uitkomst |
| --- | --- | --- |
| Stable 5/5 | ≥ 16 / 19 | **19 / 19** |
| Vier categorietaal-posts | nooit `tegenkracht` in een run | **0× tegenkracht** (Brandpunt categorietaal, Duinroos categorietaal, Duinroos archetype zonder bewijs, Stelwerk branchetaal) |
| Merkbijdrage-spreiding | ≤ 1 overal | **0 posts > 1** |

**Conclusie:** v1.13 + `foe_vervaging`-reparatie haalt de afgesproken drempel; pilot kan op basis van deze meting starten (besluit Erik/Rogier).

## Spoor 1b — offline replay

- `docs/hba-v1.13-tegenkracht-diagnose.md` (live vs replay nu vs `&& !kern_categorietaal` terug op dezelfde 95 scores).
- `merkbewering` / `kern_categorietaal` per run in JSON (geen backfill).

## Artefacten

| Bestand | Inhoud |
| --- | --- |
| `docs/hba-testmerken-stability-v1.13-5runs.json` | Ruwe 5×19 + `merkbewering` / `kern_categorietaal` per run |
| `docs/hba-testmerken-stabiliteit.md` | Tabel per post (v1.13) |
| `docs/hba-v1.13-tegenkracht-diagnose.md` | Replay 1b |
| `docs/hba-v1.13-categorietaal-runs-1-2-rapport.md` | Tussentijds (log beperkt; vervangen door deze eindmeting) |

## Opmerkingen

- `hoofdoordeel_gecorrigeerd`: 0 / 95; `hoofdoordeel_volgt_uit_flags`: 100%.
- `constraint_breach`: 15 behouden, 1 verworpen door verificatie.
