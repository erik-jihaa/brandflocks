-- Report §5.8bis — artikeltellingen per strategie-groep
ALTER TABLE performance_reports
  ADD COLUMN IF NOT EXISTS article_count_strategy INT NULL AFTER engagement_diff_pct,
  ADD COLUMN IF NOT EXISTS article_count_general INT NULL AFTER article_count_strategy,
  ADD COLUMN IF NOT EXISTS article_count_total INT NULL AFTER article_count_general;
