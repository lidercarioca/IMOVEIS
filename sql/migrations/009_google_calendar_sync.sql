-- Migration: 009_google_calendar_sync.sql
-- Cria a tabela agendamentos_google_sync usada pelo Google Calendar sync
CREATE TABLE IF NOT EXISTS `agendamentos_google_sync` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `tenant_id` INT NOT NULL,
  `agendamento_id` INT NOT NULL,
  `google_event_id` VARCHAR(500) DEFAULT NULL,
  `synced_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `ux_tenant_agendamento` (`tenant_id`, `agendamento_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_agendamentos_google_sync_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
