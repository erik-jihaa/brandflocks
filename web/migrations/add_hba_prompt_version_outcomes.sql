-- HBA v1.10 stap 2: promptversie op coaching-uitkomsten (Rogier reactie 15 okt 2026)
-- Zelfde kolomnamen als hba_scores: prompt_version + model (scores gebruikt model_used).

ALTER TABLE hba_theme_verdicts ADD COLUMN IF NOT EXISTS prompt_version VARCHAR(16) NULL;
ALTER TABLE hba_research_verdicts ADD COLUMN IF NOT EXISTS prompt_version VARCHAR(16) NULL;
ALTER TABLE hba_article_hints ADD COLUMN IF NOT EXISTS prompt_version VARCHAR(16) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS prompt_version VARCHAR(16) NULL;
ALTER TABLE architectuur_check_result ADD COLUMN IF NOT EXISTS model VARCHAR(64) NULL;
