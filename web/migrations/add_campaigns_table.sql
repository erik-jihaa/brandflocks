-- Campaign planner persistence + Agenda grouping
CREATE TABLE IF NOT EXISTS campaigns (
  id CHAR(36) NOT NULL PRIMARY KEY,
  client_id CHAR(36) NOT NULL,
  title VARCHAR(512) NOT NULL,
  theme TEXT NOT NULL,
  target_audience VARCHAR(512) NULL,
  post_count INT NOT NULL DEFAULT 4,
  start_date DATE NULL,
  generated_plan JSON NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_campaigns_client (client_id),
  KEY idx_campaigns_status (status)
);

ALTER TABLE content_slots
  ADD COLUMN IF NOT EXISTS campaign_id CHAR(36) NULL,
  ADD KEY IF NOT EXISTS idx_slots_campaign (campaign_id);
