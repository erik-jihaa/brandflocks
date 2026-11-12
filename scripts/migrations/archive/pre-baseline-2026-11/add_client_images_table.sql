CREATE TABLE IF NOT EXISTS client_images (
  id           CHAR(36)      NOT NULL PRIMARY KEY,
  client_id    CHAR(36)      NOT NULL,
  filename     VARCHAR(500)  NOT NULL,
  url          VARCHAR(500)  NOT NULL,
  alt_text     VARCHAR(500)  NULL,
  tags         VARCHAR(500)  NULL,
  uploaded_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_client_images_client (client_id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
