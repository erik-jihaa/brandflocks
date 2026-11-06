-- Dagelijkse activiteit per tenant (login, publish, errors, …) voor platform-dashboard.
CREATE TABLE IF NOT EXISTS tenant_activity_daily (
    client_id CHAR(36) NOT NULL,
    activity_date DATE NOT NULL,
    logins INT NOT NULL DEFAULT 0,
    articles_created INT NOT NULL DEFAULT 0,
    articles_published INT NOT NULL DEFAULT 0,
    themes_reviewed INT NOT NULL DEFAULT 0,
    api_requests INT NOT NULL DEFAULT 0,
    errors INT NOT NULL DEFAULT 0,
    PRIMARY KEY (client_id, activity_date),
    KEY idx_activity_date (activity_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
