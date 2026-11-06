# HBA v1.10.1 — stap 4 scorer (afronding na stap 3 PHP-cap)

**Promptversie:** `v1.10.1` (`CE_HBA_PROMPT_VERSION`).

## Wijzigingen t.o.v. v1.10 (commit `6fc0208`)

1. **Flags / werkvolgorde:** vroege samenwerkings- of ervarings-`proof_gap`; merkbijdrage-regels (≤5 kop/opening, ≤6 vroege kernzin, bijzin zonder harde cap).
2. **Hoofdoordeel:** `foe_vervaging` → altijd `tegenkracht` (PHP-filter + prompttekst).
3. **PHP na stap 3:** `brand_contribution_proof_gap_early_cap` — bij `proof_gap` in eerste ~55% van langere posts (≥350 tekens), merkbijdrage max **6** (Stelwerk 6; korte posts zoals Stelwerk post 1 uitgezonderd).

Stap 3 blijft: `proof_gap` / `foe_vervaging` op kop of openingszin → max **5**.

## Klaar-criteria (samengevoegd stap 3+4)

| Post | Verwacht |
|------|----------|
| Duinroos 7 | `proof_gap` óf `foe_vervaging` op kop, BC ≤5 |
| Stelwerk 6 | `proof_gap`, BC ≤6 |
| 16 stabiele v1.9-posts | geen regressie (5×19) |

## Staging

Deploy, daarna import of 5× matrix. Kalibratieposts gericht testen vóór volledige 5×19.

## Staging-check (15 okt 2026)

| Post | Resultaat |
|------|-----------|
| Stelwerk 1 | BC 8, `proof_gap`, judgment **goed** (early cap niet op korte post) |
| Stelwerk 6 | 5/5: `proof_gap`, BC **6**, 0× fout (samengevoegd met stap 3 Duinroos 7 eerder 5/5) |

Volledige 5×19-regressie nog niet gedraaid na v1.10.1.
