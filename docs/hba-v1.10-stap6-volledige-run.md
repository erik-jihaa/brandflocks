# HBA v1.10.2 — stap 6 volledige stabiliteitsrun

**Datum:** 15 okt 2026 (staging). Prompt **v1.10.2**.

## Uitgevoerd

1. Volledige import 19 testposts (Brandpunt + Stelwerk + Duinroos).
2. **5×19** matrix via `run-hba-testmerken-matrix.sh --runs 5` (~38 min).
3. **V0 + CE-04** via `scripts/run-hba-v0-staging.py` (~8 min).

## Artefacten

| Bestand | Inhoud |
|---------|--------|
| `docs/hba-testmerken-stability-v1.10.2-5runs.json` | Ruwe stabiliteit (incl. `runMatrices` weggelaten in export) |
| `docs/stabiliteit-v1.10.2.md` | Zelfde als `docs/hba-testmerken-stabiliteit.md` na run |
| `docs/stabiliteit-v1.10.2-5runs.json` | Kopie voor Rogier-pakket |
| `docs/hba-v0-testresultaat.json` | V0 + CE-04 (15:15 UTC) |

Rogier-pakket: `docs/rogier-hba-testmerken-pakket/stabiliteit-v1.10.2.*`

## Samenvatting matrix

| Metric | Waarde |
|--------|--------|
| Stable goed (5/5) | **13** |
| Review (ambigu) | **3** |
| Instabiel | **2** |
| Fout (niet-ambigu) | **1** |
| Functionele flag-hits | **0** |
| `constraint_breach` behouden / verworpen | 15 / 0 |
| `hoofdoordeel_gecorrigeerd` | **7.4%** (7×) |

### Kalibratieposts (stap 3+4)

| Post | 5 runs | Flags / BC |
|------|--------|------------|
| Duinroos 7 | 5× **review** (0 fout) | `foe_vervaging` op kop, BC **5** |
| Stelwerk 6 | 5× **review** (0 fout) | `proof_gap`, BC **5–6** |

### Regressie t.o.v. v1.9 (16 stabiele posts)

| Post | Was | Nu |
|------|-----|-----|
| **Stelwerk post 5** (Branchetaal) | 5/5 goed | **0/5 fout** — model meldt extra `foe_vervaging`; PHP-filter zet hoofdoordeel op `tegenkracht` i.p.v. verwacht `categorietaal` |
| Duinroos post 2 | 5/5 | **4/5** (1 run `tegenkracht` i.p.v. `categorietaal`, zelfde mechanisme) |
| Duinroos post 5 | 5/5 | **4/5** (incidenteel extra flags) |

Oorzaak Stelwerk 5: `ce_hba_apply_hoofdoordeel_filter` (v1.10.1) — elke `foe_vervaging` → `tegenkracht`, ook op posts waar alleen `category_fallback` verwacht is.

## Ronde-5 klaar-criteria (selectie)

| Criterium | Status |
|-----------|--------|
| 16× v1.9 stabiel 5/5 | **Niet** — 13 stable + regressie Stelwerk 5 (+ lichte instabiliteit Duinroos 2/5) |
| Kalibratie 6/7 onder grens | **Ja** (review, geen fout-runs) |
| Brandpunt post 3 regelbreuk 5/5 | **Ja** (twee maanden geleden) |
| 0 functionele flags | **Ja** |
| BC-spreiding ≤ 1 (niet-ambigu) | **Ja** |
| `hoofdoordeel_gecorrigeerd` ≤ 5% | **Nee** (7.4%) |
| CE-04 post 3 (script) | **Ja** — `hba-v0-testresultaat.json`, regelFeedback gevuld |
| V0 premisse/hints | **Ja** — self_review + hints; Duinroos invulveld i.p.v. verzonnen feit |
| Controlecall 6×5 | Niet opnieuw in deze run (stap 1-doc blijft leidend) |

## Filterfix (v1.10.3)

Oorzaak van de 7 PHP-correcties: `model=categorietaal → tegenkracht`. Het model koos dus al **categorietaal**; de filter van v1.10.1 overschreef dat.

**v1.10.3:** `foe_vervaging` forceert `tegenkracht` alleen nog als het model `op_merk`, `neutraal` of leeg gaf (Duinroos 7). `categorietaal` blijft staan.

Hoofdoordeel-prompt: kern categorietaal (populariteit/kwaliteit/zorg) blijft `categorietaal`, ook bij extra `foe_vervaging` op een bijzin.

### Gerichte her-run (15 okt, 5×4 posts)

| Post | Uitkomst |
|------|----------|
| Stelwerk 5 | **5/5 goed**, `categorietaal` (flags mogen `foe_vervaging` bevatten) |
| Duinroos 2 | **5/5 goed**, `categorietaal` |
| Duinroos 5 | **5/5 goed**, `categorietaal` |
| Duinroos 7 | **5/5 review**, `tegenkracht`, BC **5** (geen regressie) |

0 PHP-correcties op deze 20 scores. Volledige 5×19 niet opnieuw gedraaid na v1.10.3.
