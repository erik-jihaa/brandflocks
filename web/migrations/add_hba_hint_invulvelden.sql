-- HBA-hint: invulvelden i.p.v. verzonnen feiten (v1.10 stap 3)
ALTER TABLE hba_article_hints ADD COLUMN IF NOT EXISTS invulvelden JSON NULL;
ALTER TABLE hba_article_hints ADD COLUMN IF NOT EXISTS bewijsveld_bron TEXT NULL;
