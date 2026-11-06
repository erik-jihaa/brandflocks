ALTER TABLE scans ADD COLUMN IF NOT EXISTS report_opened_at DATETIME NULL AFTER report_token;
