-- Reset-tokens voor platform-beheerders (aparte login van tenants).
CREATE TABLE IF NOT EXISTS platform_password_reset_tokens (
    id CHAR(36) NOT NULL PRIMARY KEY,
    platform_user_id CHAR(36) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    email VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_platform_reset_token_hash (token_hash),
    KEY idx_platform_reset_user (platform_user_id),
    KEY idx_platform_reset_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
