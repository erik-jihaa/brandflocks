-- HBA architectuur-check + uitgebreide branche_library (Rogier addendum aug 2026)

ALTER TABLE branche_library ADD COLUMN branche_definitie TEXT NULL;
ALTER TABLE branche_library ADD COLUMN bronnen_kwaliteit VARCHAR(16) NULL;
ALTER TABLE branche_library ADD COLUMN vakcontext JSON NULL;
ALTER TABLE branche_library ADD COLUMN differentiatie_assen JSON NULL;
ALTER TABLE branche_library ADD COLUMN waar_onderscheid_zit TEXT NULL;

CREATE TABLE IF NOT EXISTS architectuur_check_result (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NOT NULL,
    kracht_triangle_oordeel VARCHAR(32) NULL,
    kracht_triangle_toelichting TEXT NULL,
    kracht_triangle_verbetering TEXT NULL,
    relevantie_tegenkracht_oordeel VARCHAR(32) NULL,
    relevantie_tegenkracht_toelichting TEXT NULL,
    relevantie_tegenkracht_verbetering TEXT NULL,
    relevantie_rol_oordeel VARCHAR(32) NULL,
    relevantie_rol_toelichting TEXT NULL,
    relevantie_rol_verbetering TEXT NULL,
    scherpte_uniciteit_oordeel VARCHAR(32) NULL,
    scherpte_uniciteit_toelichting TEXT NULL,
    scherpte_uniciteit_verbetering TEXT NULL,
    strawman_gedetecteerd TINYINT(1) NULL,
    zit_in_hygienefactoren TINYINT(1) NULL,
    regels_oordeel VARCHAR(32) NULL,
    regels_toelichting TEXT NULL,
    regels_verbetering TEXT NULL,
    totaaloordeel VARCHAR(64) NULL,
    premium_laag_aangeraden TINYINT(1) NOT NULL DEFAULT 0,
    toon_suggesties JSON NULL,
    voorbeeld_suggesties JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_architectuur_check_client (client_id),
    KEY idx_architectuur_check_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
