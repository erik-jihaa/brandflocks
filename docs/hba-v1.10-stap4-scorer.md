# HBA v1.10 — stap 4: scorer ziet niet-onderbouwde kernbeweringen

Datum: 2026-10-13. Lokaal gebouwd; import + 5×19 ná **“ja staging”**.

## Wat erin zit

1. **`proof_gap`:** ook als de kernbewering (kop/openingszin) een resultaat claimt dat de rest niet verklaart of logisch niet kan.
2. **`foe_vervaging`:** het vieren van een resultaat dat de tegenkracht meet (snelheid, volume, capaciteit, weggewerkte wachtlijst), ook als de rest de regel verdedigt.
3. **Scoreprompt:** `proof_gap` op de kernbewering → merkbijdrage hoogstens 5.
4. **Promptversie:** `v1.10`.
5. **Testposts** (V0-teksten uit v1.9 §4), `ambigu: true`:
   - Duinroos post 7 — `proof_gap` op “Onze wachtlijst is in één maand opgelost”, merkbijdrage ≤ 5
   - Stelwerk post 6 — `proof_gap` op de samenwerkings- of ervaringszin, merkbijdrage ≤ 6

Ambigue posts met verwachte flags worden niet meer automatisch “review”: ontbreekt `proof_gap` of zit de merkbijdrage boven de cap, dan telt de run als fout.

## Staging-run (13 okt 2026)

Deploy + import (19 posts, prompt **v1.10**) + 5×19 matrix.

**Samenvatting:** 16 stable goed · 0 instabiel · 3 review · 0 fout · 0 functionele flag-hits.

Ruwe data: `docs/hba-testmerken-stability-v1.10-5runs.json`, rapport in `docs/hba-testmerken-resultaat.md` (§ v1.10).

## Klaar-criteria stap 4

| Criterium | Uitkomst |
| --- | --- |
| Geen achteruitgang op 16 stabiele posts (post 1, Brandpunt post 5) | Gehaald — 16× 5/5, Brandpunt post 1 en 5 stable |
| Duinroos post 7: `proof_gap` op kernzin, merkbijdrage ≤ 5 | **Niet gehaald** — 0/5 runs: geen `proof_gap`, merkbijdrage 8, hoofdoordeel `op_merk` |
| Stelwerk post 6: `proof_gap`, merkbijdrage ≤ 6 | **Deels** — 5/5 `proof_gap` (ervaring/samenwerking), merkbijdrage 7 (boven cap) |

### Nieuwe testposts (5 runs)

| Post | Goed runs | Hoofdoordeel | Opmerking |
| --- | ---: | --- | --- |
| Duinroos — Kernbewering zonder bewijs (V0) | 0/5 | `op_merk` | Fout: geen `proof_gap`, BC 8 |
| Stelwerk — Samenwerking/ervaring (V0) | 0/5 | `op_merk` | Fout: wel `proof_gap`, BC 7 > 6 |

Volgende stap (prompt of post-processing): kernzin “Onze wachtlijst is in één maand opgelost” moet `proof_gap` triggeren; bij `proof_gap` op kern moet merkbijdrage ≤ 5 ook in de score landen (nu 7–8).
