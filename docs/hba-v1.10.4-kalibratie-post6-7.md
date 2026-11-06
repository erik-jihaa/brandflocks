# Gerichte kalibratie — Stelwerk 6 & Duinroos 7 (v1.10.4)

Gegenereerd: 2026-10-16 (staging)  
Prompt: **v1.10.4** · **5 runs × 2 posts** (~6,8 min)  
Ruwe JSON: `hba-v1.10.4-kalibratie-post6-7-5runs.json`

## Scorer-criteria (Rogier / testset)

| Post | Verwacht | 5/5 scorer? |
| --- | --- | --- |
| **Duinroos 7** | `proof_gap` **of** `foe_vervaging` op kop; BC ≤ 5 | **Ja** — 5× flag op kop; BC steeds **5** |
| **Stelwerk 6** | `proof_gap` op samenwerking/ervaring; BC ≤ 6 | **Ja** — 5× `proof_gap`; BC **5–6** |

Beide posts staan op `ambigu: true` → matrix-judge geeft per run altijd **`review`** (geen auto-`goed`), ook als flags en BC kloppen.

## Duinroos 7 — detail

| Run | Flag | BC | Hoofdoordeel | PHP-correctie |
| ---: | --- | ---: | --- | --- |
| 1 | `foe_vervaging` (kop) | 5 | tegenkracht | op_merk → tegenkracht |
| 2 | `foe_vervaging` (kop) | 5 | tegenkracht | op_merk → tegenkracht |
| 3 | `proof_gap` (kop) | 5 | op_merk | — |
| 4 | `foe_vervaging` (kop) | 5 | tegenkracht | op_merk → tegenkracht |
| 5 | `proof_gap` (kop) | 5 | op_merk | — |

**Instabiliteit hoofdoordeel (v1.10.4):** 3× `tegenkracht`, 2× `op_merk` (PHP filter `foe` + `op_merk` → `tegenkracht`). **Fix v1.10.5:** die flip verwijderd; `op_merk` + flag op kop blijft staan.

## Stelwerk 6 — detail

| Run | Flag (citaat) | BC | Hoofdoordeel |
| ---: | --- | ---: | --- |
| 1 | `proof_gap` openingszin samenwerking | 5 | op_merk |
| 2 | `proof_gap` ervaringszin | 6 | op_merk |
| 3–5 | `proof_gap` openingszin samenwerking | 5 | op_merk |

Geen PHP-hoofdoordeel-correcties op deze post.

## Telling

- **PHP-correcties:** 3 / 10 scores (30% op deze subset; alleen Duinroos 7 bij `foe_vervaging`).
- **Conclusie kalibratie:** scorer voldoet aan **flag + BC-cap**; resterende ruis = **hoofdoordeel** (Duinroos) en matrix-label **review** (ambigu).
