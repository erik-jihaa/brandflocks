-- Ronde 8 preview P4: plan-check resultaat op campagne
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS plan_check_json JSON NULL,
  ADD COLUMN IF NOT EXISTS plan_check_prompt_version VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS plan_check_model VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS plan_check_at TIMESTAMP NULL;
