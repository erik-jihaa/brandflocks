# HBA v1.10 — stap 5 testset

**Bron:** `briefing/hba-input/hba-ronde-5-v1.10.md` stap 5.

## Wijzigingen

1. **Brandpunt post 3:** posttekst gebruikt nu *twee maanden geleden* i.p.v. *vorige maand*, zodat regelbreuk niet afhangt van interpretatie “jonger dan zes weken”.
2. **Regelbreukdefinitie:** `constraint_breach_uitleg` moet de geloofwaardigheidstest letterlijk volgen (zelfde eis als verbeteradvies).
3. **Functionele flags:** bestaande PHP in `ce_hba_functionele_flag_hits()` / CTA-strip — controleren in stap 6 (5×19) op 0 hits.

## Klaar-criteria

- JSON bijgewerkt + op staging geïmporteerd (Brandpunt).
- Brandpunt post 3: 5/5 `regelbreuk`, uitleg noemt de zes-wekenregel correct.

## Staging (15 okt 2026)

Promptversie **v1.10.2**. Brandpunt opnieuw geïmporteerd (nieuwe post 3) + 5 scores.

| Criterium | Uitkomst |
|-----------|----------|
| Brandpunt post 3 5/5 `regelbreuk` | **5/5 goed** |
| Uitleg noemt zes-wekenregel (niet “grens nadert”) | **5/5** |
| Functionele flag-hits op deze post | **0** |

Ruwe data: `docs/hba-v1.10-stap5-brandpunt-post3-5runs.json`.

Punt 3 (0 functionele flags op de hele 19-set) volgt in **stap 6** (5×19).
