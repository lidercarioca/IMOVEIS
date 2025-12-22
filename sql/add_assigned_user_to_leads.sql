-- Adiciona coluna assigned_user_id à tabela leads
ALTER TABLE `leads` ADD COLUMN `assigned_user_id` int(11) DEFAULT NULL AFTER `property_id`;

-- Adiciona chave estrangeira para integridade referencial
ALTER TABLE `leads` ADD CONSTRAINT `fk_leads_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
