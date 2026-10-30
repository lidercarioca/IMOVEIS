-- Migration: Create system_alerts table for monitoring
-- Description: Table to store system alerts and notifications
-- Version: 1.0
-- Date: 2024

CREATE TABLE IF NOT EXISTS `system_alerts` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium',
    `metadata` JSON NULL,
    `status` ENUM('active', 'resolved', 'acknowledged') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL DEFAULT NULL,
    `resolved_by` INT(11) NULL DEFAULT NULL,
    `acknowledged_at` TIMESTAMP NULL DEFAULT NULL,
    `acknowledged_by` INT(11) NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_status_severity` (`status`, `severity`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_severity_created` (`severity`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key constraint if users table exists
-- ALTER TABLE `system_alerts` ADD CONSTRAINT `fk_alerts_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
-- ALTER TABLE `system_alerts` ADD CONSTRAINT `fk_alerts_acknowledged_by` FOREIGN KEY (`acknowledged_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;