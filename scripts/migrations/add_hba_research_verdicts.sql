-- HBA-laag op Research — oordeel per research-item (like/forget), additief op CE-02.
-- Zelfde labels als hba_theme_verdicts: versterkt / neutraal / verwatert.

CREATE TABLE IF NOT EXISTS hba_research_verdicts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    slot_id CHAR(36) NOT NULL,
    research_item_id CHAR(36) NOT NULL,
    label ENUM('versterkt', 'neutraal', 'verwatert') NOT NULL,
    toelichting TEXT NOT NULL,
    wat_ontbreekt TEXT NULL,
    judged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_research_verdicts_client (client_id),
    KEY idx_hba_research_verdicts_slot (slot_id),
    KEY idx_hba_research_verdicts_item (research_item_id),
    KEY idx_hba_research_verdicts_judged (judged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
