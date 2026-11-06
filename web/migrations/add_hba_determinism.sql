-- §10 Determinismemeting — client status + weergavemodus
ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_determinism_status VARCHAR(16) NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_determinism_measured_at TIMESTAMP NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_determinism_report JSON NULL;

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_score_display_mode VARCHAR(16) NOT NULL DEFAULT 'band';

ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS hba_score_averaging TINYINT(1) NOT NULL DEFAULT 0;
