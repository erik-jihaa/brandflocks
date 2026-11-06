-- Branche voor distinctiveness-check (optioneel per scan).
ALTER TABLE scans
  ADD COLUMN branche_code VARCHAR(64) NULL AFTER company_slug;
