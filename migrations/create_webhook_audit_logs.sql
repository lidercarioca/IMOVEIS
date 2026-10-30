-- Migration: create_webhook_audit_logs.sql
-- Cria tabela webhook_audit_logs para auditoria de webhooks recebidos

CREATE TABLE IF NOT EXISTS webhook_audit_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NULL,
  provider VARCHAR(64) NOT NULL,
  event_type VARCHAR(128) NOT NULL,
  status VARCHAR(64) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_webhook_audit_tenant_created (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
