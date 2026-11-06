# HBA v1.10 — stap 2 versieregistratie

**Doel (Rogier reactie §2):** elke opgeslagen HBA-coaching-uitkomst heeft `prompt_version` en `model` (zelfde kolomnamen als op `hba_scores`, daar `model_used`).

## Migratie

`scripts/migrations/add_hba_prompt_version_outcomes.sql` — kolommen op:

- `hba_theme_verdicts`
- `hba_research_verdicts`
- `hba_article_hints`
- `architectuur_check_result` (+ `model` indien ontbrak)

Promptversie-waarde: `CE_HBA_PROMPT_VERSION` (`web/api/hba-prompt.php`, nu **v1.10**).

## Code

- `ce_hba_persist_outcome_meta()` in `hba-model-config.php` — na INSERT op theme/research/hint/architectuurcheck.
- Runtime fallback: `ce_hba_ensure_verdict_model_columns()` voegt kolommen toe als migratie nog niet gedraaid is.

## Artikelscore

Al geïmplementeerd in `hba-repository.php` (`model_used`, `prompt_version` op `hba_scores`).

## Staging

```bash
./scripts/run-migration.sh staging add_hba_prompt_version_outcomes.sql
```

Controle: nieuwe theme-/hint-run → rij met `prompt_version = v1.10` en gevuld `model`.
