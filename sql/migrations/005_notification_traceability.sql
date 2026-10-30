-- Migration 005: notification traceability (proposta)
-- Objetivo: adicionar colunas para correlação e diagnóstico nas notificações
-- IMPORTANTE: revisar antes de aplicar em produção. NÃO executar automaticamente.

DELIMITER $$
DROP PROCEDURE IF EXISTS add_notification_traceability_columns$$
CREATE PROCEDURE add_notification_traceability_columns()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'tenant_notification_log'
      AND column_name = 'stripe_event_id'
  ) THEN
    ALTER TABLE `tenant_notification_log`
      ADD COLUMN `stripe_event_id` VARCHAR(255) NULL AFTER `tipo`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'tenant_notification_log'
      AND column_name = 'invoice_id'
  ) THEN
    ALTER TABLE `tenant_notification_log`
      ADD COLUMN `invoice_id` VARCHAR(255) NULL AFTER `stripe_event_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'tenant_notification_log'
      AND column_name = 'subscription_id'
  ) THEN
    ALTER TABLE `tenant_notification_log`
      ADD COLUMN `subscription_id` VARCHAR(255) NULL AFTER `invoice_id`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'tenant_notification_log'
      AND column_name = 'error_message'
  ) THEN
    ALTER TABLE `tenant_notification_log`
      ADD COLUMN `error_message` TEXT NULL AFTER `status`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'tenant_notification_log'
      AND index_name = 'idx_notification_stripe_event'
  ) THEN
    ALTER TABLE `tenant_notification_log`
      ADD INDEX `idx_notification_stripe_event` (`stripe_event_id`);
  END IF;
END$$
CALL add_notification_traceability_columns$$
DROP PROCEDURE IF EXISTS add_notification_traceability_columns$$
DELIMITER ;

-- Rollback manual:
-- ALTER TABLE `tenant_notification_log` DROP COLUMN `stripe_event_id`;
-- ALTER TABLE `tenant_notification_log` DROP COLUMN `invoice_id`;
-- ALTER TABLE `tenant_notification_log` DROP COLUMN `subscription_id`;
-- ALTER TABLE `tenant_notification_log` DROP COLUMN `error_message`;

-- Notas:
-- - Não remover UNIQUE existente. Evitar mudanças que possam afetar o cron `notificar_expiracao_tenants.php`.
-- - Adicionar colunas como NULL para não quebrar consultas existentes.
