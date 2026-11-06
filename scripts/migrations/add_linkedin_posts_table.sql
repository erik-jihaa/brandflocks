CREATE TABLE IF NOT EXISTS linkedin_posts (
  id CHAR(36) NOT NULL PRIMARY KEY,
  client_id CHAR(36) NOT NULL,
  source_id CHAR(36) NOT NULL,
  activity_urn VARCHAR(32) NOT NULL,
  post_url VARCHAR(512) NULL,
  posted_at DATETIME NULL,
  text_preview TEXT NULL,
  reactions INT NOT NULL DEFAULT 0,
  likes INT NOT NULL DEFAULT 0,
  comments INT NOT NULL DEFAULT 0,
  reposts INT NOT NULL DEFAULT 0,
  article_id CHAR(36) NULL,
  scraped_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_client_activity (client_id, activity_urn),
  KEY idx_client_posted (client_id, posted_at),
  KEY idx_source (source_id)
);
