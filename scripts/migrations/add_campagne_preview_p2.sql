-- Ronde 8 preview P2: fase + opdracht per campagneslot
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS phase_plan_json JSON NULL AFTER channels_json;

ALTER TABLE content_slots
  ADD COLUMN IF NOT EXISTS campaign_fase VARCHAR(128) NULL,
  ADD COLUMN IF NOT EXISTS campaign_opdracht TEXT NULL,
  ADD COLUMN IF NOT EXISTS campaign_rol VARCHAR(20) NULL;
