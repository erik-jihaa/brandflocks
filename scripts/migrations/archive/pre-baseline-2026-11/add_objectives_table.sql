-- Objectives + gerelateerde kolommen (zie briefing/add_objectives_migration.sql)
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS objectives (
  id            CHAR(36)      NOT NULL PRIMARY KEY,
  client_id     CHAR(36)      NOT NULL,
  label         VARCHAR(255)  NOT NULL,
  description   TEXT,
  percentage    TINYINT       NOT NULL DEFAULT 0,
  sort_order    TINYINT       NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_objectives_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS google_place_id VARCHAR(255) NULL,
  ADD COLUMN IF NOT EXISTS writing_style   VARCHAR(100) NULL DEFAULT 'zakelijk_direct';

ALTER TABLE themes ADD COLUMN IF NOT EXISTS objective_id CHAR(36) NULL;
ALTER TABLE content_slots ADD COLUMN IF NOT EXISTS objective_id CHAR(36) NULL;
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS objective_suggested CHAR(36) NULL,
  ADD COLUMN IF NOT EXISTS objective_final CHAR(36) NULL;

SET FOREIGN_KEY_CHECKS = 1;
