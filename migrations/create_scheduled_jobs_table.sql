-- Migration: Create scheduled_jobs table for job scheduling
-- Description: Table to store scheduled jobs with cron-like expressions
-- Version: 1.0
-- Date: 2024

CREATE TABLE IF NOT EXISTS `scheduled_jobs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `command` VARCHAR(500) NOT NULL,
    `schedule` VARCHAR(100) NOT NULL COMMENT 'Cron expression: * * * * *',
    `enabled` TINYINT(1) NOT NULL DEFAULT 1,
    `last_run` TIMESTAMP NULL DEFAULT NULL,
    `next_run` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `created_by` INT(11) NULL DEFAULT NULL,
    `description` TEXT NULL,
    `max_runtime` INT(11) NULL DEFAULT NULL COMMENT 'Maximum runtime in seconds',
    `retry_count` INT(11) NOT NULL DEFAULT 0,
    `max_retries` INT(11) NOT NULL DEFAULT 3,
    `last_error` TEXT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_name` (`name`),
    INDEX `idx_next_run` (`next_run`),
    INDEX `idx_enabled_next_run` (`enabled`, `next_run`),
    INDEX `idx_schedule` (`schedule`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert some default scheduled jobs
INSERT IGNORE INTO `scheduled_jobs` (`name`, `command`, `schedule`, `description`) VALUES
('cleanup_old_logs', 'cleanup_logs', '0 2 * * *', 'Clean up old log files daily at 2 AM'),
('backup_database', 'backup_db', '0 3 * * 0', 'Weekly database backup on Sundays at 3 AM'),
('process_pending_webhooks', 'process_webhooks', '*/15 * * * *', 'Process pending webhooks every 15 minutes'),
('generate_reports', 'generate_reports', '0 6 * * 1', 'Generate weekly reports on Mondays at 6 AM'),
('health_check', 'system_health_check', '*/5 * * * *', 'System health check every 5 minutes');