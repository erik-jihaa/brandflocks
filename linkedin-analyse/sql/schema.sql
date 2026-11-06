CREATE TABLE IF NOT EXISTS scans (
  id CHAR(36) NOT NULL PRIMARY KEY,
  company_url VARCHAR(500) NOT NULL,
  company_name VARCHAR(255) NULL,
  company_slug VARCHAR(255) NULL,
  posts_count INT NULL,
  apify_run_id VARCHAR(100) NULL,
  compute_units DECIMAL(10,4) NULL,
  cost_usd DECIMAL(10,4) NULL,
  narrative_cost_usd DECIMAL(10,4) NULL,
  raw_data LONGTEXT NULL,
  metrics JSON NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  error_message TEXT NULL,
  report_token CHAR(32) NULL,
  report_opened_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_slug_created (company_slug, created_at),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_users (
  email VARCHAR(255) NOT NULL PRIMARY KEY,
  password_hash VARCHAR(255) NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
  id CHAR(36) NOT NULL PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_token_hash (token_hash),
  KEY idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
