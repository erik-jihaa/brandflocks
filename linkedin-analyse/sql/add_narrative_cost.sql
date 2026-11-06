-- Narratief-analyse (Claude) — aparte kostenkolom naast Apify.
ALTER TABLE scans
  ADD COLUMN narrative_cost_usd DECIMAL(10,4) NULL AFTER cost_usd;
