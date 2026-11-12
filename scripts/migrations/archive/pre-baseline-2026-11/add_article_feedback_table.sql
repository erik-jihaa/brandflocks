-- article_feedback — vereist door CE-04 (Review & Publish)
-- Uitvoeren op igisnext_ce via phpMyAdmin -> SQL-tab -> Ga.

CREATE TABLE IF NOT EXISTS article_feedback (
  id            CHAR(36)     NOT NULL PRIMARY KEY,
  slot_id       CHAR(36)     NOT NULL,
  article_id    CHAR(36)     NOT NULL,
  reviewer_name VARCHAR(100) NOT NULL DEFAULT 'Hoofdredacteur',
  feedback_type VARCHAR(20)  NOT NULL DEFAULT 'text',
  comment       TEXT         NOT NULL,
  action_label  VARCHAR(100) NULL,
  excerpt       TEXT         NULL,
  status        VARCHAR(20)  NOT NULL DEFAULT 'open',
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_slot    (slot_id),
  KEY idx_article (article_id),
  CONSTRAINT fk_article_feedback_article
    FOREIGN KEY (article_id) REFERENCES articles(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
