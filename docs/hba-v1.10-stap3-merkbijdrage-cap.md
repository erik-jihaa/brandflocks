# HBA v1.10 — stap 3 merkbijdrage-cap (PHP)

**Doel (Rogier reactie §3–4):** als `proof_gap` of `foe_vervaging` de **kop** of **openingszin** citeert, is `brand_contribution.score` hoogstens **5** — afgedwongen in PHP na flag-filters, niet alleen in de prompt.

## Kop en openingszin

- **Kop:** headlineveld (`ce_article_headline`). Testmerken met placeholder `HBA-test: …` → kop = eerste zin van de body (LinkedIn-hook).
- **Openingszin:** eerste zin van de body tot de eerste punt; koptekst uit body gehaald als die herhaald wordt.

Functies: `ce_hba_resolve_kop_en_openingszin()`, `ce_hba_apply_brand_contribution_kernbewering_cap()` in `web/api/hba-repository.php`, aangeroepen vanuit `ce_hba_finalize_scored_tool()`.

Audit in `raw_response`: `brand_contribution_kernbewering_cap` (flag, citaat, from/to, applied).

## Testset

Duinroos post 7: `verwacht_flags_een_van`: `proof_gap` / `foe_vervaging` op de kop, `max_brand_contribution`: 5. Stelwerk post 6 blijft max 6.

Judge: `ce_hba_judge_testpost_row()` ondersteunt `verwacht_flags_een_van`.

## Staging

Deploy API, daarna bijvoorbeeld 5×19 stabiliteit of gericht Duinroos 7 + Stelwerk 6:

```bash
./scripts/deploy-staging.sh   # of bestaande deploy-flow
python3 scripts/run-hba-testmerken-stability-runs.py staging 5
```

## Staging-check (15 okt 2026)

Na deploy + testpost-import:

| Post | 5 runs | Opmerking |
|------|--------|-----------|
| Duinroos 7 | 5× review, 0× fout | `foe_vervaging` op kop, BC steeds **5** (cap) |
| Stelwerk 6 | 3× review, 2× fout | Cap grijpt als `proof_gap` op kop/opening (BC 5); bij flag op ervaringssin blijft BC 7–8 → nog ambigu (model, geen cap) |

Volledige 5×19-regressie: `python3 scripts/run-hba-testmerken-stability-runs.py … 5` (duurt ~30+ min).
