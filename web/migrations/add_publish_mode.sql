-- Publicatiemodus per klant (cursor-publish-modes.md)
ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS publish_mode
    ENUM('manual_with_review', 'manual_without_review', 'automatic')
    NOT NULL DEFAULT 'manual_with_review';

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS image_database_url VARCHAR(500) NULL,
  ADD COLUMN IF NOT EXISTS image_database_password VARCHAR(500) NULL;
