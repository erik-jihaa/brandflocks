-- Ronde 8 preview P5: GEO-vraagankers op campagne + slot
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS geo_anchor_ids JSON NULL;

ALTER TABLE content_slots
  ADD COLUMN IF NOT EXISTS campaign_anker JSON NULL;
