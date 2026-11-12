-- Bronnen check-frequentie per tenant (Objective → Planning)
ALTER TABLE clients
  ADD COLUMN IF NOT EXISTS source_checks_per_week INT NOT NULL DEFAULT 1;
