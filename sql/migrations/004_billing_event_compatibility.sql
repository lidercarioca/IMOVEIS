-- Migration 004: billing event compatibility
-- Nota: não executar automaticamente. Revisar antes de aplicar em produção.

ALTER TABLE `stripe_webhook_events`
  ADD COLUMN IF NOT EXISTS `event_id` VARCHAR(255) NULL AFTER `stripe_event_id`;

ALTER TABLE `stripe_webhook_events`
  ADD INDEX IF NOT EXISTS `idx_event_id` (`event_id`);

ALTER TABLE `stripe_webhook_events`
  ADD UNIQUE INDEX IF NOT EXISTS `ux_stripe_event_id` (`stripe_event_id`);

-- Esta migration prepara o lookup dual entre stripe_event_id (campo oficial) e event_id (compatibilidade legada).
-- O campo event_id é usado apenas para fallback durante a transição.
-- Após validar o backfill e o código dual-read/write, a próxima fase pode remover a dependência de event_id.

-- Esta migration prepara o lookup dual entre stripe_event_id (campo oficial) e event_id (compatibilidade legada).
-- O campo event_id é usado apenas para fallback durante a transição.
-- Após validar o backfill e o código dual-read/write, a próxima fase pode remover a dependência de event_id.
