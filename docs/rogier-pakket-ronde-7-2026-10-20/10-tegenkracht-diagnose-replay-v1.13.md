# HBA v1.13 — tegenkracht-diagnose (offline replay, spoor 1b)

Gegenereerd: 2026-10-22 10:37:55 UTC
Bron: `docs/hba-testmerken-stability-v1.13-5runs.json` (95 scores, geen modelcalls).

Twee varianten van `ce_hba_resolve_hoofdoordeel()` op dezelfde flags/citaten:
- **Live v1.13:** trede 2 = `foe_vervaging` + `foe_on_kern` (nieuwe foe-definitie, zonder `&& !kernCat`).
- **Met voorwaarde terug:** trede 2 = `foe_vervaging` + `foe_on_kern` + **`!kern_categorietaal`**.

`merkbewering` / `kern_categorietaal` komen uit de matrix-export per run (geen backfill).

## Samenvatting stable 5/5 (scorebare posts)

| Variant | Stable goed |
| --- | ---: |
| Opgeslagen in run (v1.13 live) | **19** / 19 |
| Replay **nu** (zelfde regels) | **19** / 19 |
| Replay **met `&& !kernCat` terug** | **19** / 19 |

Bij v1.13 live **19/19** stable: replay nu zou gelijk moeten lopen met live; legacy toont wat de oude trede-2-voorwaarde zou doen op dezelfde scores.

## Per post (5 runs)

| Merk | Post | Verwacht | Goed (live) | Goed (replay nu) | Goed (replay +voorwaarde) |
| --- | --- | --- | ---: | ---: | ---: |
| Brandpunt Koffie | Breekt de regel | regelbreuk | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Categorietaal | categorietaal | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Mechanisme dat markthygiëne is | categorietaal | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Neutraal / grensgeval | neutraal | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Op merk | op_merk | 5/5 | 5/5 | 5/5 |
| Brandpunt Koffie | Verdedigt de regel | op_merk | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Archetype zonder bewijs | categorietaal | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Breekt de regel | regelbreuk | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Categorietaal | categorietaal | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Kernbewering zonder bewijs (V0 v1.9) | op_merk | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Logica van de tegenkracht | tegenkracht | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Neutraal / grensgeval | neutraal | 5/5 | 5/5 | 5/5 |
| Duinroos Thuiszorg | Op merk | op_merk | 5/5 | 5/5 | 5/5 |
| Stelwerk | Branchetaal uit de verboden logica | categorietaal | 5/5 | 5/5 | 5/5 |
| Stelwerk | Breekt de regel | regelbreuk | 5/5 | 5/5 | 5/5 |
| Stelwerk | Categorietaal | categorietaal | 5/5 | 5/5 | 5/5 |
| Stelwerk | Neutraal / grensgeval | neutraal | 5/5 | 5/5 | 5/5 |
| Stelwerk | Op merk | op_merk | 5/5 | 5/5 | 5/5 |
| Stelwerk | Samenwerking/ervaring zonder bewijs (V0 v1.9) | op_merk | 5/5 | 5/5 | 5/5 |

## Geen code-wijziging

Alleen diagnose op geëxporteerde scores. Pilotbesluit v1.13 baseert op live run + bovenstaande vergelijking.

