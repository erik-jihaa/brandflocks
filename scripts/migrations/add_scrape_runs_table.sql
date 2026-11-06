-- scrape_runs — Apify/scraper budget logging (briefing v11)
CREATE TABLE IF NOT EXISTS scrape_runs (
  id CHAR(36) NOT NULL PRIMARY KEY,
  source_id CHAR(36) NOT NULL,
  client_id CHAR(36) NULL,
  theme_id CHAR(36) NULL,
  slot_id CHAR(36) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'ok',
  units_used DECIMAL(10,4) NULL,
  cost_eur DECIMAL(10,4) NULL,
  items_count INT NULL,
  apify_run_id VARCHAR(100) NULL,
  message TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_source_created (source_id, created_at),
  KEY idx_client_created (client_id, created_at)
);

ALTER TABLE sources ADD COLUMN IF NOT EXISTS scraper_linkedin_profile VARCHAR(500) NULL;
ALTER TABLE sources ADD COLUMN IF NOT EXISTS scraper_linkedin_password_ref VARCHAR(255) NULL;
