-- Migration 007: add stripe_event_id UNIQUE index to pagamentos
-- Objetivo: reforçar a idempotência de pagamentos por stripe_event_id,
--           prevenindo race conditions na injeção de webhooks Stripe.
-- IMPORTANTE: revisar antes de aplicar em produção.

DELIMITER $$
DROP PROCEDURE IF EXISTS add_stripe_event_id_unique_index$$
CREATE PROCEDURE add_stripe_event_id_unique_index()
BEGIN
  -- Verificar se já existe o índice UNIQUE em (tenant_id, stripe_event_id)
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'pagamentos'
      AND index_name = 'uq_pagamentos_tenant_stripe_event_id'
  ) THEN
    ALTER TABLE `pagamentos`
      ADD UNIQUE KEY `uq_pagamentos_tenant_stripe_event_id` (`tenant_id`, `stripe_event_id`);
  END IF;
END$$
CALL add_stripe_event_id_unique_index$$
DROP PROCEDURE IF EXISTS add_stripe_event_id_unique_index$$
DELIMITER ;

-- Rollback manual:
-- ALTER TABLE `pagamentos` DROP INDEX `uq_pagamentos_tenant_stripe_event_id`;

-- Notas:
-- - Chave UNIQUE em (tenant_id, stripe_event_id) garante que cada tenant não pode ter dois pagamentos para o mesmo stripe_event_id.
-- - Valores NULL são tratados pelo MySQL como distintos para índices UNIQUE (cada NULL não entra em conflito com outro NULL).
-- - Isso complementa o índice existente em (tenant_id, stripe_reference).
-- - A lógica de deduplicação em BillingHistoryModel::recordPayment() fica mais robusta contra race conditions.
