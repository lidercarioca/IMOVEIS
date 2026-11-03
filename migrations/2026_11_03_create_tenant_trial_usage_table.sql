CREATE TABLE IF NOT EXISTS `tenant_trial_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `plan_code` varchar(50) NOT NULL DEFAULT 'trial',
  `started_at` datetime NOT NULL,
  `expires_at` datetime DEFAULT NULL,
  `used_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_trial_usage_tenant_id` (`tenant_id`),
  KEY `idx_tenant_trial_usage_expires_at` (`expires_at`),
  CONSTRAINT `fk_tenant_trial_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Reexecuções seguras: a tabela já existe é tratada como no-op e a restrição
-- de unicidade por tenant permanece como garantia estrutural da regra de uso único.
