-- Migration 006: add Stripe reference support to pagamentos
-- Objetivo: adicionar campos de correlação Stripe para permitir idempotência financeira.
-- IMPORTANTE: revisar antes de aplicar em produção. NÃO executar automaticamente.

DELIMITER $$
DROP PROCEDURE IF EXISTS add_stripe_reference_columns_to_pagamentos$$
CREATE PROCEDURE add_stripe_reference_columns_to_pagamentos()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pagamentos'
      AND column_name = 'stripe_reference'
  ) THEN
    ALTER TABLE `pagamentos`
      ADD COLUMN `stripe_reference` VARCHAR(255) NULL AFTER `plano`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pagamentos'
      AND column_name = 'stripe_reference_type'
  ) THEN
    ALTER TABLE `pagamentos`
      ADD COLUMN `stripe_reference_type` VARCHAR(50) NULL AFTER `stripe_reference`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'pagamentos'
      AND column_name = 'stripe_event_id'
  ) THEN
    ALTER TABLE `pagamentos`
      ADD COLUMN `stripe_event_id` VARCHAR(255) NULL AFTER `stripe_reference_type`;
  END IF;

  IF NOT EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'pagamentos'
      AND index_name = 'uq_pagamentos_tenant_stripe_reference'
  ) THEN
    ALTER TABLE `pagamentos`
      ADD UNIQUE KEY `uq_pagamentos_tenant_stripe_reference` (`tenant_id`, `stripe_reference`);
  END IF;
END$$
CALL add_stripe_reference_columns_to_pagamentos$$
DROP PROCEDURE IF EXISTS add_stripe_reference_columns_to_pagamentos$$
DELIMITER ;

-- Rollback manual:
-- ALTER TABLE `pagamentos` DROP INDEX `uq_pagamentos_tenant_stripe_reference`;
-- ALTER TABLE `pagamentos` DROP COLUMN `stripe_reference`;
-- ALTER TABLE `pagamentos` DROP COLUMN `stripe_reference_type`;
-- ALTER TABLE `pagamentos` DROP COLUMN `stripe_event_id`;

-- Notas:
-- - Colunas são NULL para não quebrar pagamentos existentes e manuais.
-- - O índice único usa tenant_id + stripe_reference.
-- - Valores NULL em stripe_reference não causam conflito em MySQL/MariaDB.
