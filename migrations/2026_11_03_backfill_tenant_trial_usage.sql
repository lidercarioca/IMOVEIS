INSERT IGNORE INTO `tenant_trial_usage` (
  `tenant_id`,
  `plan_code`,
  `started_at`,
  `expires_at`,
  `used_at`,
  `created_at`,
  `updated_at`
)
SELECT
  `trial_source`.`tenant_id`,
  'trial' AS `plan_code`,
  `trial_source`.`started_at`,
  `trial_source`.`expires_at`,
  `trial_source`.`used_at`,
  NOW(),
  NOW()
FROM (
  SELECT
    `p`.`tenant_id`,
    COALESCE(`p`.`data_pagamento`, `t`.`created_at`, NOW()) AS `started_at`,
    COALESCE(`t`.`data_expiracao`, DATE_ADD(COALESCE(`p`.`data_pagamento`, `t`.`created_at`, NOW()), INTERVAL 7 DAY)) AS `expires_at`,
    COALESCE(`p`.`data_pagamento`, `t`.`created_at`, NOW()) AS `used_at`
  FROM `pagamentos` `p`
  INNER JOIN `tenants` `t` ON `t`.`id` = `p`.`tenant_id`
  WHERE `p`.`plano` = 'trial'

  UNION ALL

  SELECT
    `t`.`id` AS `tenant_id`,
    `t`.`created_at` AS `started_at`,
    `t`.`data_expiracao` AS `expires_at`,
    `t`.`created_at` AS `used_at`
  FROM `tenants` `t`
  WHERE `t`.`plano` = 'trial'
) AS `trial_source`
LEFT JOIN `tenant_trial_usage` `ttu` ON `ttu`.`tenant_id` = `trial_source`.`tenant_id`
WHERE `ttu`.`id` IS NULL
GROUP BY `trial_source`.`tenant_id`, `trial_source`.`started_at`, `trial_source`.`expires_at`, `trial_source`.`used_at`;

-- Reexecução segura: o backfill ignora registros duplicados e preserva a evidência
-- histórica já armazenada sem criar nova utilização artificial para um tenant existente.
