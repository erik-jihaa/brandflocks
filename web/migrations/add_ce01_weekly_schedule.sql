-- Testtenants / sandbox: ce01_weekly_schedule = 0 uitsluiten van maandag CE-01-scheduler (n8n).
-- Handmatige CE-01 (webhook met client_id) blijft werken.

ALTER TABLE clients
  ADD COLUMN ce01_weekly_schedule TINYINT(1) NOT NULL DEFAULT 1;
