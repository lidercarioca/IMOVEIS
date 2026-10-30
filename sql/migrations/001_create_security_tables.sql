-- Tabelas de Monitoramento de Segurança
-- Criadas para suportar o painel de monitoramento de segurança

-- Tabela de Anomalias de Segurança
CREATE TABLE IF NOT EXISTS `security_anomalies` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `anomaly_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    `username` VARCHAR(255),
    `user_id` INT,
    `ip_address` VARCHAR(45),
    `detected_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL,
    `details` TEXT,
    INDEX `idx_detected_at` (`detected_at`),
    INDEX `idx_resolved_at` (`resolved_at`),
    INDEX `idx_severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de IPs Suspeitas
CREATE TABLE IF NOT EXISTS `suspicious_ips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `user_id` INT,
    `username` VARCHAR(255),
    `attempt_count` INT DEFAULT 1,
    `last_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `first_attempt` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reason` VARCHAR(255),
    `blocked` BOOLEAN DEFAULT FALSE,
    INDEX `idx_last_attempt` (`last_attempt`),
    INDEX `idx_ip_address` (`ip_address`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de IPs Bloqueadas
CREATE TABLE IF NOT EXISTS `blocked_ips` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL UNIQUE,
    `reason` VARCHAR(255),
    `blocked_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `blocked_until` TIMESTAMP NULL,
    `blocked_by_user_id` INT,
    `permanent` BOOLEAN DEFAULT FALSE,
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_blocked_until` (`blocked_until`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Alertas de Segurança
CREATE TABLE IF NOT EXISTS `security_alerts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `alert_type` VARCHAR(100) NOT NULL,
    `message` TEXT NOT NULL,
    `severity` ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    `alert_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `resolved_at` TIMESTAMP NULL,
    `resolved_by_user_id` INT,
    `details` JSON,
    INDEX `idx_alert_time` (`alert_time`),
    INDEX `idx_severity` (`severity`),
    INDEX `idx_resolved_at` (`resolved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela de Eventos de Segurança
CREATE TABLE IF NOT EXISTS `security_events` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT,
    `event_type` VARCHAR(100) NOT NULL,
    `severity` ENUM('INFO', 'LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'INFO',
    `details` TEXT,
    `ip_address` VARCHAR(45),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_event_type` (`event_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
