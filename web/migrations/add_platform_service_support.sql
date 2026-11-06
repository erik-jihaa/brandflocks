-- Support-chat vragen (alleen user-vraag) + interne service-tickets voor platform-dashboard.
CREATE TABLE IF NOT EXISTS support_chat_messages (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    client_id CHAR(36) NOT NULL,
    question TEXT NOT NULL,
    redacted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_support_chat_client_created (client_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS service_tickets (
    id CHAR(36) NOT NULL,
    client_id CHAR(36) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('open', 'in_progress', 'closed') NOT NULL DEFAULT 'open',
    note TEXT NULL,
    created_by_platform_user_id CHAR(36) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_service_tickets_client (client_id, status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
