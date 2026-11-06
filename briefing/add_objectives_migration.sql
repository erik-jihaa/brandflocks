-- CE: Objectives + Google Reviews migraties
-- Voer uit op igisnext_ce via phpMyAdmin → SQL-tab → Ga.
-- Volgorde is belangrijk: objectives tabel eerst.

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Nieuwe tabel: objectives
CREATE TABLE objectives (
  id            CHAR(36)      NOT NULL PRIMARY KEY,
  client_id     CHAR(36)      NOT NULL,
  label         VARCHAR(255)  NOT NULL,
  description   TEXT,
  percentage    TINYINT       NOT NULL DEFAULT 0,
  sort_order    TINYINT       NOT NULL DEFAULT 0,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_objectives_client
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Clients: Google Place ID + schrijfstijl
ALTER TABLE clients
  ADD COLUMN google_place_id VARCHAR(255) NULL,
  ADD COLUMN writing_style   VARCHAR(100) NULL DEFAULT 'zakelijk_direct';

-- 3. Themes: objective_id
ALTER TABLE themes
  ADD COLUMN objective_id CHAR(36) NULL,
  ADD CONSTRAINT fk_themes_objective
    FOREIGN KEY (objective_id) REFERENCES objectives(id);

-- 4. Content_slots: objective_id
ALTER TABLE content_slots
  ADD COLUMN objective_id CHAR(36) NULL,
  ADD CONSTRAINT fk_slots_objective
    FOREIGN KEY (objective_id) REFERENCES objectives(id);

-- 5. Articles: objective velden
ALTER TABLE articles
  ADD COLUMN objective_suggested CHAR(36) NULL,
  ADD COLUMN objective_final     CHAR(36) NULL,
  ADD CONSTRAINT fk_articles_obj_suggested
    FOREIGN KEY (objective_suggested) REFERENCES objectives(id),
  ADD CONSTRAINT fk_articles_obj_final
    FOREIGN KEY (objective_final) REFERENCES objectives(id);

-- 6. Performance_reports: Google Reviews + objective snapshot
ALTER TABLE performance_reports
  ADD COLUMN google_rating            DECIMAL(3,1) NULL,
  ADD COLUMN google_total_reviews     INT          NULL,
  ADD COLUMN google_reviews_snapshot  JSON         NULL,
  ADD COLUMN objective_breakdown      JSON         NULL;

SET FOREIGN_KEY_CHECKS = 1;

-- Testdata: objectives voor iGiSNext testklant
INSERT INTO objectives (id, client_id, label, description, percentage, sort_order)
VALUES
  (UUID(), '016384af-b26e-4415-aa9c-abc46b804798',
   'Brand Awareness',
   'Naamsbekendheid opbouwen en de merkidentiteit van iGiSNext versterken. Posts die laten zien wie we zijn, waar we voor staan en hoe we denken.',
   40, 1),
  (UUID(), '016384af-b26e-4415-aa9c-abc46b804798',
   'Thought Leadership',
   'Inhoudelijk gezag opbouwen op het snijvlak van AI, organisatie-identiteit en groei. Posts die een standpunt innemen en de lezer iets nieuws leren.',
   35, 2),
  (UUID(), '016384af-b26e-4415-aa9c-abc46b804798',
   'Lead Generation',
   'Potentiële klanten bereiken die worstelen met kennisborging, AI-implementatie of schaalbaarheid. Posts die herkenning oproepen en een gesprek uitlokken.',
   25, 3);
