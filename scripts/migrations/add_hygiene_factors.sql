-- §6 Hygiënefactoren per contextprofiel (Fase 4)
CREATE TABLE IF NOT EXISTS hygiene_lists (
  id             CHAR(36)     NOT NULL PRIMARY KEY,
  contextprofiel VARCHAR(64)  NOT NULL,
  version        INT          NOT NULL DEFAULT 1,
  status         VARCHAR(16)  NOT NULL DEFAULT 'draft',
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_hygiene_profile_version (contextprofiel, version)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hygiene_factors (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  list_id     CHAR(36)     NOT NULL,
  phrase      TEXT         NOT NULL,
  kind        VARCHAR(24)  NOT NULL,
  sources     JSON         NULL,
  added_by    VARCHAR(128) NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hygiene_factor_list FOREIGN KEY (list_id) REFERENCES hygiene_lists(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE hba_scores
  ADD COLUMN IF NOT EXISTS hygiene_list_version INT NULL;
