-- Ronde 8 preview P1: campagne-metadata + slot-koppeling (geen LLM)
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS template_key VARCHAR(32) NULL AFTER title,
  ADD COLUMN IF NOT EXISTS campaign_goal VARCHAR(20) NULL AFTER template_key,
  ADD COLUMN IF NOT EXISTS channels_json JSON NULL AFTER post_count,
  ADD COLUMN IF NOT EXISTS preview_status VARCHAR(20) NOT NULL DEFAULT 'concept' AFTER status;

ALTER TABLE content_slots
  ADD COLUMN IF NOT EXISTS campaign_slot_index INT NULL,
  ADD COLUMN IF NOT EXISTS campaign_platform VARCHAR(32) NULL;
