-- Campaign start date (when week 1 of the plan begins)
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS start_date DATE NULL AFTER post_count;
