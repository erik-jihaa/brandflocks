-- CE-03 Stap D (briefing v11 §4.3.4bis) — Claude zelfcontrole op artikel
ALTER TABLE articles
  ADD COLUMN IF NOT EXISTS self_review JSON NULL AFTER hashtags_suggested;
