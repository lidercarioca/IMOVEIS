-- =============================================================================
-- SQL: Tabelas para Detecção de Anomalias e Logging de Segurança
-- =============================================================================

-- Histórico de logins com IPs e timestamps
CREATE TABLE IF NOT EXISTS login_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    login_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success TINYINT(1) DEFAULT 1,
    user_agent VARCHAR(500),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anomalias detectadas
CREATE TABLE IF NOT EXISTS login_anomalies (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    anomaly_type ENUM(
        'MULTIPLE_SESSIONS',
        'MULTIPLE_IPS',
        'IMPOSSIBLE_TRAVEL',
        'UNUSUAL_LOCATION',
        'NEW_DEVICE',
        'RAPID_LOGIN_ATTEMPTS',
        'SUSPICIOUS_BEHAVIOR'
    ) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    detected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved TINYINT(1) DEFAULT 0,
    resolved_at DATETIME NULL,
    admin_notes TEXT,
    INDEX idx_user_id (user_id),
    INDEX idx_anomaly_type (anomaly_type),
    INDEX idx_severity (severity),
    INDEX idx_detected_at (detected_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Log detalhado de requisições por usuário
CREATE TABLE IF NOT EXISTS request_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(255),
    ip_address VARCHAR(45) NOT NULL,
    request_method ENUM('GET', 'POST', 'PUT', 'DELETE', 'PATCH') NOT NULL,
    request_uri VARCHAR(500) NOT NULL,
    request_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    response_code INT,
    user_agent VARCHAR(500),
    INDEX idx_user_id (user_id),
    INDEX idx_ip_address (ip_address),
    INDEX idx_request_time (request_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Monitoramento de IPs suspeitas/bloqueadas
CREATE TABLE IF NOT EXISTS blocked_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reason VARCHAR(255) NOT NULL,
    blocked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    admin_notes TEXT,
    INDEX idx_ip_address (ip_address),
    INDEX idx_expires_at (expires_at),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Whitelist de IPs confiáveis (para usuários que usam VPN/proxy fixo)
CREATE TABLE IF NOT EXISTS trusted_ips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    description VARCHAR(255),
    added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    verified TINYINT(1) DEFAULT 1,
    UNIQUE KEY unique_user_ip (user_id, ip_address),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alertas de segurança para administradores
CREATE TABLE IF NOT EXISTS security_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    alert_type ENUM(
        'BRUTE_FORCE_ATTEMPT',
        'ANOMALY_DETECTED',
        'SUSPICIOUS_ACTIVITY',
        'MALWARE_DETECTED',
        'POLICY_VIOLATION',
        'IP_BLOCKED'
    ) NOT NULL,
    severity ENUM('LOW', 'MEDIUM', 'HIGH', 'CRITICAL') DEFAULT 'MEDIUM',
    message TEXT NOT NULL,
    related_user_id INT,
    related_ip_address VARCHAR(45),
    alert_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved TINYINT(1) DEFAULT 0,
    resolved_by INT,
    resolved_at DATETIME NULL,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_related_user_id (related_user_id),
    INDEX idx_alert_time (alert_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Índices de performance
-- =============================================================================

CREATE INDEX idx_login_history_user_time ON login_history(user_id, login_time);
CREATE INDEX idx_anomalies_user_time ON login_anomalies(user_id, detected_at);
CREATE INDEX idx_blocked_ips_active ON blocked_ips(is_active, expires_at);

-- =============================================================================
-- Views úteis para monitoramento
-- =============================================================================

CREATE OR REPLACE VIEW vw_active_blocked_ips AS
SELECT 
    ip_address,
    reason,
    blocked_at,
    expires_at,
    TIMESTAMPDIFF(MINUTE, NOW(), expires_at) as minutes_until_unblock
FROM blocked_ips
WHERE is_active = 1 AND expires_at > NOW();

CREATE OR REPLACE VIEW vw_recent_anomalies AS
SELECT 
    a.id,
    a.username,
    a.anomaly_type,
    a.severity,
    a.ip_address,
    a.detected_at,
    TIMESTAMPDIFF(MINUTE, a.detected_at, NOW()) as minutes_ago
FROM login_anomalies a
WHERE a.resolved = 0 AND a.detected_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY a.detected_at DESC;

CREATE OR REPLACE VIEW vw_suspicious_ips AS
SELECT 
    ip_address,
    COUNT(*) as attempt_count,
    COUNT(DISTINCT username) as unique_users,
    MAX(login_time) as last_attempt,
    GROUP_CONCAT(DISTINCT username SEPARATOR ', ') as attempted_users
FROM login_history
WHERE login_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)
GROUP BY ip_address
HAVING attempt_count > 5
ORDER BY attempt_count DESC;
