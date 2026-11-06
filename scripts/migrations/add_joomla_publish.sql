-- Joomla Web Services publish (per tenant in clients)
ALTER TABLE clients ADD COLUMN IF NOT EXISTS joomla_site_url VARCHAR(500) NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS joomla_api_token VARCHAR(500) NULL;
ALTER TABLE clients ADD COLUMN IF NOT EXISTS joomla_category_id INT NULL;
