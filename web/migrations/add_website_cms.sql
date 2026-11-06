-- Expliciete CMS-keuze per tenant (wordpress | joomla | static)
ALTER TABLE clients ADD COLUMN IF NOT EXISTS website_cms VARCHAR(20) NULL;
