-- §7 Agenda als bedieningspaneel — dag/tijd + publicaties per bestemming
ALTER TABLE content_slots
  ADD COLUMN IF NOT EXISTS publish_date DATE NULL,
  ADD COLUMN IF NOT EXISTS publish_time TIME NULL;

ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS editorial_status VARCHAR(24) NULL;

CREATE TABLE IF NOT EXISTS publications (
  id            CHAR(36)     NOT NULL PRIMARY KEY,
  article_id    CHAR(36)     NOT NULL,
  platform      VARCHAR(32)  NOT NULL,
  status        VARCHAR(24)  NOT NULL DEFAULT 'gepland',
  scheduled_at  DATETIME     NULL,
  published_at  DATETIME     NULL,
  external_id   VARCHAR(128) NULL,
  external_url  TEXT         NULL,
  error_message TEXT         NULL,
  attempts      TINYINT      NOT NULL DEFAULT 0,
  UNIQUE KEY uq_publication_article_platform (article_id, platform),
  KEY idx_publication_article (article_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
