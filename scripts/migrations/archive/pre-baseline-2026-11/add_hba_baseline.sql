-- §9 Nulmeting — origin onderscheid app vs baseline LinkedIn-historie
ALTER TABLE hba_scores
  ADD COLUMN IF NOT EXISTS origin VARCHAR(16) NOT NULL DEFAULT 'app';

ALTER TABLE hba_scores
  ADD COLUMN IF NOT EXISTS linkedin_post_id CHAR(36) NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_baseline_status VARCHAR(24) NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_baseline_completed_at TIMESTAMP NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_baseline_post_count INT NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_baseline_narrative_id CHAR(36) NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_baseline_prompt_version VARCHAR(16) NULL;

CREATE INDEX IF NOT EXISTS idx_hba_scores_origin ON hba_scores (origin);
CREATE INDEX IF NOT EXISTS idx_hba_scores_linkedin_post ON hba_scores (linkedin_post_id);
