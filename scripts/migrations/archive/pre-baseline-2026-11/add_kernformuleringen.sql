-- §3 Kernformuleringen — terugkerende formuleringen + scoring-hit
ALTER TABLE brand_narratives
  ADD COLUMN IF NOT EXISTS kernformuleringen JSON NULL;

ALTER TABLE hba_scores
  ADD COLUMN IF NOT EXISTS kernformulering_hit TINYINT NULL;
