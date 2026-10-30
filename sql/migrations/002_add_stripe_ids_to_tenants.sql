-- Adiciona colunas para armazenar identificadores Stripe no tenant
ALTER TABLE `tenants`
    ADD COLUMN `stripe_customer_id` VARCHAR(255) NULL AFTER `data_expiracao`,
    ADD COLUMN `stripe_subscription_id` VARCHAR(255) NULL AFTER `stripe_customer_id`;
