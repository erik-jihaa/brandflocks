-- Fase 1 observability: centrale foutlog per tenant en platform-breed.
-- Retentie: info 30 dagen, warn/error 12 maanden (ce_prune_event_log).
CREATE TABLE IF NOT EXISTS event_log (
    id CHAR(36) NOT NULL PRIMARY KEY,
    client_id CHAR(36) NULL,
    severity VARCHAR(10) NOT NULL,
    category VARCHAR(50) NOT NULL,
    event VARCHAR(100) NOT NULL,
    message TEXT NULL,
    context JSON NULL,
    environment VARCHAR(20) NOT NULL,
    notified_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_client_created (client_id, created_at),
    KEY idx_severity_created (severity, created_at),
    KEY idx_category (category),
    KEY idx_event_client (event, client_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
