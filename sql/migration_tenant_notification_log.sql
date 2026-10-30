-- Tabela para rastrear notificações de expiração enviadas
-- Evita enviar múltiplos emails para o mesmo tenant
CREATE TABLE IF NOT EXISTS tenant_notification_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tenant_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    data_envio DATETIME DEFAULT CURRENT_TIMESTAMP,
    status VARCHAR(20) DEFAULT 'enviado',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_tenant_tipo (tenant_id, tipo),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tipo_data (tipo, data_envio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
