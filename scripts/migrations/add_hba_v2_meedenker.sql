-- HBA v2 meedenker — theme verdicts, article hints, branche library, client flags.
-- Bron: briefing/cursor-hba-v2-meedenker.md

CREATE TABLE IF NOT EXISTS branche_library (
    id CHAR(36) NOT NULL PRIMARY KEY,
    branche_code VARCHAR(64) NOT NULL,
    branche_label VARCHAR(255) NOT NULL,
    markt_archetype VARCHAR(32) NULL,
    hygienefactoren JSON NOT NULL,
    categorietaal JSON NOT NULL,
    bron ENUM('afgeleid', 'gecureerd') NOT NULL DEFAULT 'afgeleid',
    gecureerd_door CHAR(36) NULL,
    laatst_bijgewerkt TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branche_code (branche_code),
    KEY idx_branche_label (branche_label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hba_theme_verdicts (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    theme_id CHAR(36) NOT NULL,
    label ENUM('versterkt', 'neutraal', 'verwatert') NOT NULL,
    toelichting TEXT NOT NULL,
    wat_ontbreekt TEXT NULL,
    judged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_theme_verdicts_client (client_id),
    KEY idx_hba_theme_verdicts_theme (theme_id),
    KEY idx_hba_theme_verdicts_judged (judged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hba_article_hints (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    article_id CHAR(36) NOT NULL,
    aanwijzing TEXT NOT NULL,
    passage_voor TEXT NULL,
    passage_na TEXT NULL,
    headline_voor TEXT NULL,
    headline_na TEXT NULL,
    cta_voor TEXT NULL,
    cta_na TEXT NULL,
    regel_overtreden TEXT NULL,
    toegepast TINYINT(1) NOT NULL DEFAULT 0,
    judged_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_hba_article_hints_client (client_id),
    KEY idx_hba_article_hints_article (article_id),
    KEY idx_hba_article_hints_judged (judged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
