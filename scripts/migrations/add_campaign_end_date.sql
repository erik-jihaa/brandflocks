-- Campaign end date (when the campaign period ends)
ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS end_date DATE NULL AFTER start_date;
