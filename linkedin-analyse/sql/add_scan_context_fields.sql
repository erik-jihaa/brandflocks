-- Bedrijfsomschrijving + contextprofiel (BrandArchitecture-wizard, §10 briefing).
ALTER TABLE scans
  ADD COLUMN company_description TEXT NULL AFTER branche_code,
  ADD COLUMN contextprofiel VARCHAR(32) NULL AFTER company_description;
