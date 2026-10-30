-- Migration: criar tabela stripe_webhook_events
-- NÃO EXECUTAR automaticamente. Revisar antes de aplicar em produção.

CREATE TABLE IF NOT EXISTS `stripe_webhook_events` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `stripe_event_id` VARCHAR(255) NOT NULL,
  `event_id` VARCHAR(255) DEFAULT NULL,
  `payload` LONGTEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` ENUM('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `tenant_id` INT DEFAULT NULL,
  `retry_count` INT NOT NULL DEFAULT 0,
  `error_message` TEXT DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `ux_stripe_event_id` (`stripe_event_id`),
  INDEX `idx_event_id` (`event_id`),
  INDEX `idx_entity_tenant` (`tenant_id`),
  INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opcional: foreign key para tenants (recomendado revisar constraints do ambiente)
-- ALTER TABLE `stripe_webhook_events`
--   ADD CONSTRAINT `fk_swe_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Comentários técnicos:
-- - `stripe_event_id`: identificador único do evento enviado pelo Stripe (evt_...)
-- - `event_id`: coluna auxiliar para compatibilidade com implementações que usam outro nome
-- - `payload`: JSON bruto do webhook para auditoria e replay
-- - `status`: fluxo de processamento ('pending' -> 'processed' | 'failed')
-- - `retry_count` + `error_message`: controle de tentativas e diagnósticos
-- - `tenant_id`: vínculo opcional para segurança multi-tenant
