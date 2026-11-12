-- CE-02 metadata + ad-hoc onderbouwing (briefing cursor-aandachtspunten-onderbouwing §2, §4)
-- Idempotent: duplicate column wordt door admin-run-migration.php genegeerd.
ALTER TABLE research_items ADD COLUMN stance ENUM('bevestigt','nuanceert','spreekt_tegen') NULL;
ALTER TABLE research_items ADD COLUMN reliability ENUM('low','medium','high') NULL;
