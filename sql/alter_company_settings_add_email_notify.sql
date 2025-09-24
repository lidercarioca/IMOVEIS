-- Adiciona campos de e-mail e notificações na tabela company_settings
ALTER TABLE `company_settings`
  ADD COLUMN `email_notifications` varchar(255) DEFAULT NULL AFTER `company_color3`,
  ADD COLUMN `email_leads` varchar(255) DEFAULT NULL AFTER `email_notifications`,
  ADD COLUMN `notify_new_lead` tinyint(1) DEFAULT 1 AFTER `email_leads`,
  ADD COLUMN `notify_new_property` tinyint(1) DEFAULT 1 AFTER `notify_new_lead`,
  ADD COLUMN `notify_property_status` tinyint(1) DEFAULT 1 AFTER `notify_new_property`,
  ADD COLUMN `notify_contact_form` tinyint(1) DEFAULT 1 AFTER `notify_property_status`;
