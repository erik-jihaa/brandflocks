-- Ronde 6: vragen-/feitenbank + merkonderdeel per slot (addendum §3/§6)
CREATE TABLE IF NOT EXISTS merk_vragen (
  id CHAR(36) NOT NULL PRIMARY KEY,
  client_id CHAR(36) NOT NULL,
  vraag TEXT NOT NULL,
  bron VARCHAR(20) NOT NULL DEFAULT 'bureau',
  doelpagina VARCHAR(2048) NULL,
  pagina_antwoord VARCHAR(16) NOT NULL DEFAULT 'onbekend',
  geo_uitkomst VARCHAR(32) NULL,
  laatst_gebruikt DATE NULL,
  aantal_gebruikt INT NOT NULL DEFAULT 0,
  actief TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_merk_vragen_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS merk_feiten (
  id CHAR(36) NOT NULL PRIMARY KEY,
  client_id CHAR(36) NOT NULL,
  formulering TEXT NOT NULL,
  bron_url VARCHAR(2048) NULL,
  merk_onderdeel VARCHAR(64) NULL,
  bevestigd_door_bureau TINYINT(1) NOT NULL DEFAULT 1,
  laatst_gebruikt DATE NULL,
  aantal_gebruikt INT NOT NULL DEFAULT 0,
  actief TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_merk_feiten_client (client_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE campaigns
  ADD COLUMN IF NOT EXISTS merk_onderdelen_json JSON NULL,
  ADD COLUMN IF NOT EXISTS feit_anchor_ids JSON NULL,
  ADD COLUMN IF NOT EXISTS vraag_anchor_ids JSON NULL;

ALTER TABLE content_slots
  ADD COLUMN IF NOT EXISTS campaign_merk_onderdeel VARCHAR(64) NULL;
