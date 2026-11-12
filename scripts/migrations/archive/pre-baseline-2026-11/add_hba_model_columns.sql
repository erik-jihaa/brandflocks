-- HBA model-logging per verdict/hint (v1 config-split — briefing cursor-hba-model-allocatie.md)
-- Bestaande rijen: NULL (geen backfill).

ALTER TABLE hba_theme_verdicts ADD COLUMN model VARCHAR(64) NULL;
ALTER TABLE hba_research_verdicts ADD COLUMN model VARCHAR(64) NULL;
ALTER TABLE hba_article_hints ADD COLUMN model VARCHAR(64) NULL;
