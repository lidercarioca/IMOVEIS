CREATE TABLE IF NOT EXISTS `google_calendar_connections` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `provider` VARCHAR(32) NOT NULL DEFAULT 'google',
  `provider_account_id` VARCHAR(255) DEFAULT NULL,
  `google_user_email` VARCHAR(255) DEFAULT NULL,
  `access_token` TEXT DEFAULT NULL,
  `refresh_token` TEXT DEFAULT NULL,
  `expires_in` INT DEFAULT NULL,
  `scope` VARCHAR(512) DEFAULT NULL,
  `scopes` TEXT DEFAULT NULL,
  `token_type` VARCHAR(50) DEFAULT NULL,
  `token_payload` LONGTEXT DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `calendar_id` VARCHAR(255) NOT NULL DEFAULT 'primary',
  `status` ENUM('active', 'revoked', 'disconnected') NOT NULL DEFAULT 'active',
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `tenant_user_google_connection` (`tenant_id`, `user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider_account_id` (`provider_account_id`),
  CONSTRAINT `fk_google_calendar_connections_tenant`
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_google_calendar_connections_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
