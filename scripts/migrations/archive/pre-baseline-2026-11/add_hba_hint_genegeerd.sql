-- HBA artikel-aanwijzing: negeer zonder toe te passen
ALTER TABLE hba_article_hints ADD COLUMN IF NOT EXISTS genegeerd TINYINT(1) NOT NULL DEFAULT 0;
