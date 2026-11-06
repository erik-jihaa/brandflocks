ALTER TABLE scans ADD COLUMN report_token CHAR(32) NULL AFTER error_message;
CREATE UNIQUE INDEX idx_scans_report_token ON scans (report_token);
