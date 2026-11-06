-- Ronde 8 preview P3: productpagina-extract op campagne
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS product_page_url VARCHAR(2048) NULL,
  ADD COLUMN IF NOT EXISTS product_extract_json JSON NULL,
  ADD COLUMN IF NOT EXISTS product_extract_prompt_version VARCHAR(32) NULL,
  ADD COLUMN IF NOT EXISTS product_extract_model VARCHAR(64) NULL,
  ADD COLUMN IF NOT EXISTS product_extract_at TIMESTAMP NULL;
