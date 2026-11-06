# Gerichte kalibratie — Stelwerk 6 & Duinroos 7 (v1.10.5)

Gegenereerd: 2026-10-16 (staging)  
Prompt: **v1.10.5** · **5 runs × 2 posts** (~5,8 min)  
Ruwe JSON: `hba-v1.10.5-kalibratie-post6-7-5runs.json`

## Scorer-criteria (Rogier / testset)

| Post | Verwacht | 5/5 scorer? |
| --- | --- | --- |
| **Duinroos 7** | `proof_gap` **of** `foe_vervaging` op kop; BC ≤ 5 | **Ja** — 5× flag op kop; BC steeds **5** |
| **Stelwerk 6** | `proof_gap` op samenwerking/ervaring; BC ≤ 6 | **Ja** — 5× `proof_gap`; BC **5–6** |

Beide posts staan op `ambigu: true` → matrix-judge geeft per run altijd **`review`** (geen auto-`goed`).

## Duinroos 7 — detail

| Run | Flag | BC | Hoofdoordeel | PHP-correctie |
| ---: | --- | ---: | --- | --- |
| 1 | `foe_vervaging` (kop) | 5 | op_merk | — |
| 2 | `foe_vervaging` (kop) | 5 | op_merk | — |
| 3 | `proof_gap` (kop) | 5 | op_merk | — |
| 4 | `foe_vervaging` (kop) | 5 | op_merk | — |
| 5 | `proof_gap` (kop) | 5 | op_merk | — |

**Hoofdoordeel:** 5/5 **`op_merk`** (geen PHP-correcties; v1.10.5 filter).

## Stelwerk 6 — detail

| Run | Flag (citaat) | BC | Hoofdoordeel |
| ---: | --- | ---: | --- |
| 1–5 | `proof_gap` (openings-/ervaringszin) | 5–6 | op_merk |

Geen PHP-hoofdoordeel-correcties.

## Telling

- **PHP-correcties:** 0 / 10 scores.
- **Verschil t.o.v. v1.10.4:** Duinroos 7 hoofdoordeel stabiel; flags/BC ongewijzigd groen.
