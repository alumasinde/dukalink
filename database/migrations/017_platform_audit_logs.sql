CREATE TABLE IF NOT EXISTS platform_audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id BIGINT UNSIGNED NULL,
    summary VARCHAR(255) NOT NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_platform_audit_created (created_at),
    KEY idx_platform_audit_user (platform_user_id,created_at),
    KEY idx_platform_audit_entity (entity_type,entity_id,created_at),
    CONSTRAINT fk_platform_audit_user FOREIGN KEY (platform_user_id) REFERENCES platform_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
