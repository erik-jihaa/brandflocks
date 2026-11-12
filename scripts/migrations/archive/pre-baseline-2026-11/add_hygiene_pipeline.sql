-- §6 Hygiënefactoren — scrape-pipeline + strateeg-review
CREATE TABLE IF NOT EXISTS hygiene_competitors (
  id          CHAR(36)     NOT NULL PRIMARY KEY,
  list_id     CHAR(36)     NOT NULL,
  name        VARCHAR(128) NOT NULL,
  base_url    VARCHAR(512) NOT NULL,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hygiene_competitor_list FOREIGN KEY (list_id) REFERENCES hygiene_lists(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hygiene_scrape_pages (
  id           CHAR(36)     NOT NULL PRIMARY KEY,
  competitor_id CHAR(36)     NOT NULL,
  page_type    VARCHAR(32)  NOT NULL,
  url          VARCHAR(512) NOT NULL,
  text_content MEDIUMTEXT   NULL,
  http_status  SMALLINT     NULL,
  scraped_at   TIMESTAMP    NULL,
  CONSTRAINT fk_hygiene_page_competitor FOREIGN KEY (competitor_id) REFERENCES hygiene_competitors(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE hygiene_factors
  ADD COLUMN IF NOT EXISTS review_status VARCHAR(16) NOT NULL DEFAULT 'approved';

ALTER TABLE hygiene_factors
  ADD COLUMN IF NOT EXISTS competitor_count TINYINT NULL;
