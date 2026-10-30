-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: sys_imoveis
-- ------------------------------------------------------
-- Server version	12.3.2-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `agendamentos`
--

DROP TABLE IF EXISTS `agendamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agendamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) NOT NULL,
  `leads_id` int(11) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `data_agendamento` datetime NOT NULL,
  `status` varchar(50) DEFAULT 'confirmado' COMMENT 'confirmado, cancelado, realizado',
  `descricao` text DEFAULT NULL,
  `google_event_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `google_event_id` (`google_event_id`),
  KEY `property_id` (`property_id`),
  KEY `leads_id` (`leads_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_agendamentos_deleted_at` (`deleted_at`),
  KEY `idx_user_data_deleted` (`user_id`,`data_agendamento`,`deleted_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agendamentos_ibfk_2` FOREIGN KEY (`leads_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `agendamentos_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_agendamentos_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `agendamentos_google_sync`
--

DROP TABLE IF EXISTS `agendamentos_google_sync`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `agendamentos_google_sync` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `agendamento_id` int(11) NOT NULL,
  `google_event_id` varchar(500) DEFAULT NULL,
  `synced_at` datetime DEFAULT current_timestamp(),
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_tenant_agendamento` (`tenant_id`,`agendamento_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `fk_agendamentos_google_sync_agendamento` (`agendamento_id`),
  CONSTRAINT `fk_agendamentos_google_sync_agendamento` FOREIGN KEY (`agendamento_id`) REFERENCES `agendamentos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts`
--

DROP TABLE IF EXISTS `alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `alert_type` varchar(50) NOT NULL COMMENT 'lead_inativo, property_parado, commission_pending, etc',
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `title` varchar(255) NOT NULL,
  `message` longtext DEFAULT NULL,
  `related_entity_type` varchar(50) DEFAULT NULL COMMENT 'lead, property, commission, sale',
  `related_entity_id` int(11) DEFAULT NULL COMMENT 'ID do lead, property, etc',
  `status` enum('novo','reconhecido','resolvido','descartado') DEFAULT 'novo',
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `auto_triggered` tinyint(1) DEFAULT 1,
  `rule_id` int(11) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL COMMENT 'JSON com dados adicionais',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_status` (`status`),
  KEY `idx_related_entity` (`related_entity_type`,`related_entity_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `fk_alerts_rule` (`rule_id`),
  KEY `fk_alerts_acknowledged_by` (`acknowledged_by`),
  KEY `fk_alerts_resolved_by` (`resolved_by`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_status_resolved_at` (`status`,`resolved_at`),
  CONSTRAINT `fk_alerts_acknowledged_by` FOREIGN KEY (`acknowledged_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_alerts_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_alerts_rule` FOREIGN KEY (`rule_id`) REFERENCES `alerts_rules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_alerts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabela de alertas inteligentes do sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts_config`
--

DROP TABLE IF EXISTS `alerts_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `alert_type` varchar(50) NOT NULL,
  `alert_name` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `priority` varchar(20) DEFAULT 'MEDIUM',
  `threshold` int(11) DEFAULT 0,
  `enabled` tinyint(1) DEFAULT 1,
  `last_sent_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_alert` (`user_id`,`alert_type`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_user` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `alerts_config_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts_history`
--

DROP TABLE IF EXISTS `alerts_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL DEFAULT 1,
  `alert_type` varchar(50) NOT NULL COMMENT 'lead_inativo, property_parado, commission_pending, etc',
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `title` varchar(255) NOT NULL,
  `message` longtext DEFAULT NULL,
  `related_entity_type` varchar(50) DEFAULT NULL COMMENT 'lead, property, commission, sale',
  `related_entity_id` int(11) DEFAULT NULL COMMENT 'ID do lead, property, etc',
  `status` enum('novo','reconhecido','resolvido','descartado') NOT NULL DEFAULT 'novo',
  `acknowledged_at` datetime DEFAULT NULL,
  `acknowledged_by` int(11) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `auto_triggered` tinyint(1) DEFAULT 1,
  `rule_id` int(11) DEFAULT NULL,
  `metadata` longtext DEFAULT NULL COMMENT 'JSON com dados adicionais',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `archived_at` datetime DEFAULT NULL COMMENT 'Timestamp de arquivamento',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_severity` (`severity`),
  KEY `idx_status` (`status`),
  KEY `idx_related_entity` (`related_entity_type`,`related_entity_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_archived_at` (`archived_at`),
  CONSTRAINT `chk_user_id_positive` CHECK (`user_id` > 0)
) ENGINE=InnoDB AUTO_INCREMENT=296 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Tabela de alertas inteligentes do sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerts_rules`
--

DROP TABLE IF EXISTS `alerts_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerts_rules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `rule_name` varchar(100) DEFAULT NULL,
  `condition` text DEFAULT NULL,
  `alert_type` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `days_threshold` int(11) NOT NULL DEFAULT 30,
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `enabled` tinyint(1) DEFAULT 1,
  `auto_trigger` tinyint(1) DEFAULT 1,
  `notification_channels` longtext DEFAULT NULL CHECK (json_valid(`notification_channels`)),
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `alert_type` (`alert_type`),
  KEY `idx_alert_type` (`alert_type`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_key_logs`
--

DROP TABLE IF EXISTS `api_key_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_key_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `api_key_id` int(11) NOT NULL,
  `method` varchar(10) DEFAULT NULL,
  `endpoint` varchar(500) DEFAULT NULL,
  `response_status` int(11) DEFAULT NULL,
  `response_time` int(11) DEFAULT NULL,
  `last_used` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_api_key_id` (`api_key_id`),
  KEY `idx_last_used` (`last_used`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `api_key_logs_ibfk_1` FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `api_keys`
--

DROP TABLE IF EXISTS `api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `key` varchar(255) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  KEY `idx_key` (`key`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`active`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `async_jobs`
--

DROP TABLE IF EXISTS `async_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `async_jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `job_type` varchar(50) NOT NULL,
  `job_data` longtext NOT NULL,
  `status` enum('pending','processing','completed','failed') DEFAULT 'pending',
  `priority` enum('low','normal','high') DEFAULT 'normal',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_priority` (`priority`),
  KEY `idx_created` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `audit_logs`
--

DROP TABLE IF EXISTS `audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL COMMENT 'user_create, user_update, user_delete, tenant_select, etc',
  `resource_type` varchar(50) NOT NULL COMMENT 'user, tenant, settings, etc',
  `resource_id` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `old_values` longtext DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_action_type` (`action_type`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_audit_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `banners`
--

DROP TABLE IF EXISTS `banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) DEFAULT NULL,
  `description` mediumtext DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `order_position` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `button_text` varchar(50) DEFAULT NULL,
  `mobile_image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `blocked_ips`
--

DROP TABLE IF EXISTS `blocked_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `blocked_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `ip_address` varchar(45) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL,
  `blocked_by_user_id` int(11) DEFAULT NULL,
  `permanent` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_blocked_until` (`blocked_until`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commission_settings`
--

DROP TABLE IF EXISTS `commission_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commission_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID ??nico da configura????o',
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL COMMENT 'Vendedor',
  `commission_percentage` decimal(5,2) DEFAULT 6.00,
  `default_percentage` decimal(5,2) NOT NULL DEFAULT 6.00 COMMENT 'Percentual padr??o de comiss??o',
  `active` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Se a comiss??o est?? ativa',
  `notes` text DEFAULT NULL COMMENT 'Observa????es sobre a comiss??o',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `idx_commission_settings_active` (`active`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_commission_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Configura????es de comiss??o por vendedor';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `commissions`
--

DROP TABLE IF EXISTS `commissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `commissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID ??nico da comiss??o',
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `sale_id` int(11) NOT NULL COMMENT 'Refer??ncia ?? venda (property_sales)',
  `user_id` int(11) NOT NULL COMMENT 'Vendedor que realizou a venda',
  `property_id` int(11) NOT NULL COMMENT 'Im??vel vendido',
  `sale_value` decimal(15,2) NOT NULL COMMENT 'Valor total da venda',
  `commission_percentage` decimal(5,2) NOT NULL DEFAULT 6.00 COMMENT 'Percentual de comiss??o aplicado',
  `commission_value` decimal(12,2) NOT NULL COMMENT 'Valor da comiss??o calculado',
  `status` enum('pendente','aprovada','paga','cancelada') NOT NULL DEFAULT 'pendente' COMMENT 'Estado da comiss??o',
  `approval_notes` text DEFAULT NULL COMMENT 'Notas na aprova????o',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT 'Data de aprova????o',
  `approved_by` int(11) DEFAULT NULL COMMENT 'ID do gerente que aprovou',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT 'Data do pagamento',
  `paid_method` varchar(50) DEFAULT NULL COMMENT 'M??todo de pagamento (transfer??ncia, cheque, etc)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data de cria????o',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data de atualiza????o',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sale_id` (`sale_id`),
  KEY `fk_commissions_property` (`property_id`),
  KEY `fk_commissions_approved_by` (`approved_by`),
  KEY `idx_commissions_user_status` (`user_id`,`status`),
  KEY `idx_commissions_user_created` (`user_id`,`created_at`),
  KEY `idx_commissions_status` (`status`),
  KEY `idx_commissions_created_at` (`created_at`),
  KEY `idx_commissions_paid_at` (`paid_at`),
  KEY `idx_commissions_deleted_at` (`deleted_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_commissions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_commissions_property` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_commissions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_commissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de comiss??es de vendedores';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `company_settings`
--

DROP TABLE IF EXISTS `company_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `company_name` varchar(255) NOT NULL,
  `company_email` varchar(255) DEFAULT NULL,
  `company_email2` varchar(255) DEFAULT NULL,
  `company_phone` varchar(20) DEFAULT NULL,
  `company_whatsapp` varchar(20) DEFAULT NULL,
  `company_address` text DEFAULT NULL,
  `company_weekday_hours` varchar(50) DEFAULT '9h ??s 18h',
  `company_saturday_hours` varchar(50) DEFAULT '9h ??s 13h',
  `company_description` text DEFAULT NULL,
  `company_facebook` varchar(255) DEFAULT NULL,
  `company_instagram` varchar(255) DEFAULT NULL,
  `company_linkedin` varchar(255) DEFAULT NULL,
  `company_youtube` varchar(255) DEFAULT NULL,
  `company_logo` varchar(255) DEFAULT NULL,
  `company_color1` varchar(7) DEFAULT NULL,
  `company_color2` varchar(7) DEFAULT NULL,
  `company_font` varchar(100) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `map_coordinates` point DEFAULT NULL,
  `business_hours` text DEFAULT NULL,
  `creci` varchar(20) DEFAULT NULL,
  `company_color3` varchar(50) DEFAULT NULL,
  `email_notifications` varchar(255) DEFAULT NULL,
  `email_leads` varchar(255) DEFAULT NULL,
  `notify_new_lead` tinyint(1) DEFAULT 1,
  `notify_new_property` tinyint(1) DEFAULT 1,
  `notify_property_status` tinyint(1) DEFAULT 1,
  `notify_contact_form` tinyint(1) DEFAULT 1,
  `notify_agendamento` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `financeiro`
--

DROP TABLE IF EXISTS `financeiro`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `financeiro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) DEFAULT NULL,
  `reference_sale_id` int(11) DEFAULT NULL,
  `reference_commission_id` int(11) DEFAULT NULL,
  `reversal_of_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL COMMENT 'receita, despesa, comissao',
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_transacao` date NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pendente' COMMENT 'pendente, concluído, cancelado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `financeiro_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financeiro_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_financeiro_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `followup_templates`
--

DROP TABLE IF EXISTS `followup_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `followup_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` enum('email','sms') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text NOT NULL,
  `variables` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `goals_config`
--

DROP TABLE IF EXISTS `goals_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `goals_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `goal_id` varchar(50) NOT NULL,
  `goal_name` varchar(100) NOT NULL,
  `target_value` decimal(15,2) NOT NULL,
  `period_days` int(11) DEFAULT 30,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_goal` (`tenant_id`,`goal_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `google_calendar_connections`
--

DROP TABLE IF EXISTS `google_calendar_connections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `google_calendar_connections` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `provider` varchar(32) NOT NULL DEFAULT 'google',
  `provider_account_id` varchar(255) DEFAULT NULL,
  `google_user_email` varchar(255) DEFAULT NULL,
  `access_token` text DEFAULT NULL,
  `refresh_token` text DEFAULT NULL,
  `expires_in` int(11) DEFAULT NULL,
  `scope` varchar(512) DEFAULT NULL,
  `scopes` text DEFAULT NULL,
  `token_type` varchar(50) DEFAULT NULL,
  `token_payload` text DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `calendar_id` varchar(255) NOT NULL DEFAULT 'primary',
  `status` enum('active','revoked','disconnected') NOT NULL DEFAULT 'active',
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_user_google_connection` (`tenant_id`,`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_provider_account_id` (`provider_account_id`),
  CONSTRAINT `fk_google_calendar_connections_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_google_calendar_connections_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_followup_scheduled`
--

DROP TABLE IF EXISTS `lead_followup_scheduled`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_followup_scheduled` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `sequence_id` int(11) NOT NULL,
  `step_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `scheduled_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `executed_at` timestamp NULL DEFAULT NULL,
  `status` enum('pending','executed','failed','cancelled') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sequence_id` (`sequence_id`),
  KEY `step_id` (`step_id`),
  KEY `idx_lead_status` (`lead_id`,`status`),
  KEY `idx_scheduled` (`scheduled_at`,`status`),
  KEY `idx_tenant` (`tenant_id`),
  CONSTRAINT `lead_followup_scheduled_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_followup_scheduled_ibfk_2` FOREIGN KEY (`sequence_id`) REFERENCES `lead_followup_sequences` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_followup_scheduled_ibfk_3` FOREIGN KEY (`step_id`) REFERENCES `lead_followup_steps` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_followup_sequences`
--

DROP TABLE IF EXISTS `lead_followup_sequences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_followup_sequences` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `trigger_condition` enum('lead_created','status_changed','score_changed','inactivity_days') NOT NULL,
  `trigger_value` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_trigger` (`trigger_condition`,`trigger_value`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_followup_steps`
--

DROP TABLE IF EXISTS `lead_followup_steps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_followup_steps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sequence_id` int(11) NOT NULL,
  `step_order` int(11) NOT NULL,
  `delay_days` int(11) NOT NULL,
  `delay_hours` int(11) DEFAULT 0,
  `action_type` enum('email','sms','call_reminder','task') NOT NULL,
  `template_subject` varchar(255) DEFAULT NULL,
  `template_body` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sequence_order` (`sequence_id`,`step_order`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `lead_followup_steps_ibfk_1` FOREIGN KEY (`sequence_id`) REFERENCES `lead_followup_sequences` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_interactions`
--

DROP TABLE IF EXISTS `lead_interactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_interactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `interaction_type` varchar(50) NOT NULL COMMENT 'viewed, called, emailed, sms, status_change, assigned, notes_added',
  `title` varchar(255) NOT NULL COMMENT 'Título resumido da interação',
  `description` text DEFAULT NULL COMMENT 'Descrição detalhada',
  `old_value` varchar(255) DEFAULT NULL COMMENT 'Valor anterior (para status changes)',
  `new_value` varchar(255) DEFAULT NULL COMMENT 'Valor novo (para status changes)',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Dados adicionais (duração de chamada, assunto email, etc)' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_type` (`interaction_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_lead_tenant` (`lead_id`,`tenant_id`),
  CONSTRAINT `lead_interactions_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_interactions_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `lead_interactions_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=120 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de interações com leads';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_score_history`
--

DROP TABLE IF EXISTS `lead_score_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_score_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `lead_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `old_score` decimal(5,2) DEFAULT NULL,
  `new_score` decimal(5,2) DEFAULT NULL,
  `old_category` enum('hot','warm','cold') DEFAULT NULL,
  `new_category` enum('hot','warm','cold') DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lead_tenant` (`lead_id`,`tenant_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=60 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lead_scoring_config`
--

DROP TABLE IF EXISTS `lead_scoring_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lead_scoring_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `interaction_type` varchar(50) NOT NULL,
  `weight` decimal(3,2) NOT NULL DEFAULT 1.00,
  `max_points` int(11) DEFAULT 100,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_interaction` (`tenant_id`,`interaction_type`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `leads`
--

DROP TABLE IF EXISTS `leads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','negotiating','closed','cancelled') DEFAULT 'new',
  `linked_sale_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `proposal_sent` tinyint(1) NOT NULL DEFAULT 0,
  `proposal_sent_at` datetime DEFAULT NULL,
  `pipeline_stage_id` int(11) DEFAULT NULL,
  `utm_source` varchar(50) DEFAULT NULL,
  `utm_medium` varchar(50) DEFAULT NULL,
  `utm_campaign` varchar(100) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `utm_tracking_id` int(11) DEFAULT NULL,
  `score` decimal(5,2) DEFAULT 0.00,
  `score_category` enum('hot','warm','cold') DEFAULT 'cold',
  `score_updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `idx_leads_status` (`status`),
  KEY `fk_leads_assigned_user` (`assigned_user_id`),
  KEY `idx_leads_proposal_sent` (`proposal_sent`),
  KEY `idx_leads_linked_sale` (`linked_sale_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_leads_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_leads_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_ibfk_2` FOREIGN KEY (`linked_sale_id`) REFERENCES `property_sales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=286 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts`
--

DROP TABLE IF EXISTS `login_attempts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_username_ip_time` (`username`,`ip_address`,`attempt_time`),
  KEY `idx_attempt_time` (`attempt_time`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_attempts_log`
--

DROP TABLE IF EXISTS `login_attempts_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_attempts_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts` (`username`,`attempted_at`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `messages`
--

DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `from_name` varchar(255) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `property_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `notifications`
--

DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `dismissed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_read_at` (`read_at`),
  KEY `idx_dismissed_at` (`dismissed_at`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=240 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pagamentos`
--

DROP TABLE IF EXISTS `pagamentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pagamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `valor` decimal(10,2) NOT NULL,
  `status` enum('pendente','pago') DEFAULT 'pendente',
  `data_pagamento` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `plano` varchar(50) DEFAULT NULL,
  `stripe_reference` varchar(255) DEFAULT NULL,
  `stripe_reference_type` varchar(50) DEFAULT NULL,
  `stripe_event_id` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pagamentos_tenant_stripe_reference` (`tenant_id`,`stripe_reference`),
  UNIQUE KEY `uq_pagamentos_tenant_stripe_event_id` (`tenant_id`,`stripe_event_id`),
  KEY `tenant_id` (`tenant_id`),
  CONSTRAINT `pagamentos_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1285 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_history`
--

DROP TABLE IF EXISTS `password_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `old_password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_password_history` (`user_id`,`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `password_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_resets`
--

DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`(64)),
  KEY `user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pipeline_history`
--

DROP TABLE IF EXISTS `pipeline_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pipeline_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `pipeline_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `from_stage_id` int(11) DEFAULT NULL,
  `to_stage_id` int(11) NOT NULL,
  `old_value` decimal(12,2) DEFAULT NULL,
  `new_value` decimal(12,2) DEFAULT NULL,
  `old_expected_close_date` date DEFAULT NULL,
  `new_expected_close_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_pipeline_id` (`pipeline_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pipeline_stages`
--

DROP TABLE IF EXISTS `pipeline_stages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pipeline_stages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `position` int(11) NOT NULL,
  `color` varchar(7) DEFAULT '#3B82F6',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_name` (`tenant_id`,`name`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=296 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `plans`
--

DROP TABLE IF EXISTS `plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'Identificador único (trial, basico, profissional, premium)',
  `name` varchar(100) NOT NULL COMMENT 'Nome amigável (Plano Básico, etc)',
  `description` text DEFAULT NULL COMMENT 'Descrição do plano',
  `max_users` int(11) NOT NULL DEFAULT 2 COMMENT 'Limite de usuários',
  `max_properties` int(11) NOT NULL DEFAULT 0,
  `monthly_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Valor mensal em reais',
  `trial_days` int(11) DEFAULT NULL COMMENT 'Dias de trial (NULL = sem trial)',
  `is_trial` tinyint(1) DEFAULT 0,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Features incluídas no plano (JSON array)' CHECK (json_valid(`features`)),
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Se o plano está disponível para venda',
  `display_order` int(11) DEFAULT 0 COMMENT 'Ordem de exibição nas páginas',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `stripe_price_id` varchar(255) DEFAULT NULL COMMENT 'Stripe Price ID para billing',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `idx_code` (`code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_order` (`display_order`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabela de planos de assinatura do sistema SaaS - Totalmente configurável e dinâmico';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `portal_integrations`
--

DROP TABLE IF EXISTS `portal_integrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `portal_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `portal_slug` varchar(100) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT 0,
  `endpoint` varchar(512) DEFAULT NULL,
  `credentials` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_portal` (`tenant_id`,`portal_slug`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `properties`
--

DROP TABLE IF EXISTS `properties`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `title` varchar(255) NOT NULL,
  `type` enum('apartment','house','commercial','land') NOT NULL,
  `transactionType` varchar(255) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `area` decimal(10,2) NOT NULL,
  `bedrooms` int(11) DEFAULT NULL,
  `bathrooms` int(11) DEFAULT NULL,
  `garage` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `neighborhood` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` char(2) DEFAULT NULL,
  `features` text DEFAULT NULL,
  `status` enum('active','pending','vendido','alugado','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `coordinates` point DEFAULT NULL,
  `video_url` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `yearBuilt` int(11) DEFAULT NULL,
  `zip` varchar(20) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `images` text DEFAULT NULL,
  `condominium` decimal(10,2) DEFAULT NULL,
  `iptu` decimal(10,2) DEFAULT NULL,
  `suites` int(11) DEFAULT NULL,
  `sale_commission_percentage` decimal(5,2) DEFAULT NULL COMMENT 'Percentual de comiss??o em caso de venda do im??vel',
  `pipeline_stage_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_properties_status` (`status`),
  KEY `idx_properties_type` (`type`),
  KEY `idx_properties_transaction_type` (`transactionType`),
  KEY `idx_sale_commission` (`sale_commission_percentage`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_properties_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=34 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_images`
--

DROP TABLE IF EXISTS `property_images`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_hash` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_images_property_id` (`property_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=78 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_last_movement`
--

DROP TABLE IF EXISTS `property_last_movement`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_last_movement` (
  `property_id` int(11) NOT NULL COMMENT 'ID do im??vel',
  `event_type` varchar(50) DEFAULT NULL COMMENT 'Tipo do ??ltimo evento',
  `last_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'Data do ??ltimo movimento',
  `days_without_movement` int(11) DEFAULT NULL COMMENT 'Dias sem movimento',
  PRIMARY KEY (`property_id`),
  KEY `idx_days_without_movement` (`days_without_movement`),
  KEY `idx_event_type` (`event_type`),
  CONSTRAINT `property_last_movement_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Snapshot do ??ltimo movimento de cada im??vel para queries r??pidas';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_movements`
--

DROP TABLE IF EXISTS `property_movements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_movements` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID ??nico do movimento',
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) NOT NULL COMMENT 'ID do im??vel',
  `user_id` int(11) DEFAULT NULL COMMENT 'ID do usu??rio que realizou a a????o',
  `event_type` enum('listing_created','listing_updated','listing_paused','listing_resumed','lead_contact','lead_visit_scheduled','visit_completed','offer_received','negotiation_started','negotiation_completed','sale_closed','status_changed') NOT NULL DEFAULT 'listing_updated' COMMENT 'Tipo de evento',
  `description` text DEFAULT NULL COMMENT 'Descri????o do evento (para compatibilidade)',
  `metadata` longtext DEFAULT NULL COMMENT 'Dados estruturados do evento em JSON' CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Data/hora do evento',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `idx_property_created` (`property_id`,`created_at`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_property_user` (`property_id`,`user_id`),
  KEY `idx_property_event` (`property_id`,`event_type`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `property_movements_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `property_movements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Hist??rico de movimentos e intera????es com im??veis';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_movements_backup`
--

DROP TABLE IF EXISTS `property_movements_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_movements_backup` (
  `id` int(11) NOT NULL DEFAULT 0,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_portal_sync`
--

DROP TABLE IF EXISTS `property_portal_sync`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_portal_sync` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) NOT NULL,
  `portal` varchar(100) NOT NULL,
  `last_exported_at` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `response` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `portal` (`portal`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `property_sales`
--

DROP TABLE IF EXISTS `property_sales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `property_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `property_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `broker_user_id` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `property_title` varchar(255) NOT NULL,
  `property_price` decimal(12,2) NOT NULL,
  `commission_6percent` decimal(12,2) NOT NULL,
  `commission_percentage_applied` decimal(10,2) DEFAULT NULL,
  `commission_value_applied` decimal(18,2) DEFAULT NULL,
  `broker_commission_percentage` decimal(10,2) DEFAULT NULL,
  `broker_commission_value` decimal(18,2) DEFAULT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_id` (`property_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `fk_property_sales_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `property_sales_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_notification_logs`
--

DROP TABLE IF EXISTS `push_notification_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_notification_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `subscription_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `icon` varchar(500) DEFAULT NULL,
  `badge` varchar(500) DEFAULT NULL,
  `status` enum('pending','sent','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL,
  `clicked_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_subscription_id` (`subscription_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `push_notification_logs_ibfk_1` FOREIGN KEY (`subscription_id`) REFERENCES `push_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `push_subscriptions`
--

DROP TABLE IF EXISTS `push_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `push_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `endpoint` varchar(500) NOT NULL,
  `auth_key` varchar(255) NOT NULL,
  `p256dh_key` varchar(255) NOT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_interaction` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `endpoint` (`endpoint`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_endpoint` (`endpoint`),
  KEY `idx_is_active` (`is_active`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `push_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `role_permissions`
--

DROP TABLE IF EXISTS `role_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `role` varchar(50) NOT NULL,
  `permission` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role`,`permission`),
  KEY `idx_role` (`role`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sales_pipeline`
--

DROP TABLE IF EXISTS `sales_pipeline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sales_pipeline` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `lead_id` int(11) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `pipeline_stage_id` int(11) NOT NULL,
  `value` decimal(12,2) DEFAULT 0.00,
  `expected_close_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `position` int(11) DEFAULT 0,
  `is_archived` tinyint(1) DEFAULT 0,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_stage` (`user_id`,`pipeline_stage_id`),
  KEY `idx_lead_id` (`lead_id`),
  KEY `idx_property_id` (`property_id`),
  KEY `idx_stage_id` (`pipeline_stage_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `sales_pipeline_ibfk_1` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_pipeline_ibfk_2` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `sales_pipeline_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `sales_pipeline_ibfk_4` FOREIGN KEY (`pipeline_stage_id`) REFERENCES `pipeline_stages` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `scheduled_jobs`
--

DROP TABLE IF EXISTS `scheduled_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `scheduled_jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `command` varchar(500) NOT NULL,
  `schedule` varchar(100) NOT NULL COMMENT 'Cron expression: * * * * *',
  `enabled` tinyint(1) NOT NULL DEFAULT 1,
  `last_run` timestamp NULL DEFAULT NULL,
  `next_run` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `max_runtime` int(11) DEFAULT NULL COMMENT 'Maximum runtime in seconds',
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `max_retries` int(11) NOT NULL DEFAULT 3,
  `last_error` text DEFAULT NULL,
  `job_type` varchar(255) DEFAULT NULL,
  `cron_expression` varchar(100) DEFAULT NULL,
  `job_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`job_data`)),
  `user_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `run_count` int(11) DEFAULT 0,
  `failure_count` int(11) DEFAULT 0,
  `last_failure` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_next_run` (`next_run`),
  KEY `idx_enabled_next_run` (`enabled`,`next_run`),
  KEY `idx_schedule` (`schedule`),
  KEY `idx_active_next_run` (`active`,`next_run`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `security_alerts`
--

DROP TABLE IF EXISTS `security_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `alert_type` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `alert_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by_user_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  PRIMARY KEY (`id`),
  KEY `idx_alert_time` (`alert_time`),
  KEY `idx_severity` (`severity`),
  KEY `idx_resolved_at` (`resolved_at`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `security_anomalies`
--

DROP TABLE IF EXISTS `security_anomalies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_anomalies` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `anomaly_type` varchar(100) NOT NULL,
  `severity` enum('LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'MEDIUM',
  `username` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `detected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `details` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_detected_at` (`detected_at`),
  KEY `idx_resolved_at` (`resolved_at`),
  KEY `idx_severity` (`severity`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `security_events`
--

DROP TABLE IF EXISTS `security_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `security_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `user_id` int(11) DEFAULT NULL,
  `event_type` varchar(100) NOT NULL,
  `severity` enum('INFO','LOW','MEDIUM','HIGH','CRITICAL') DEFAULT 'INFO',
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=65 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `site_visits`
--

DROP TABLE IF EXISTS `site_visits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `site_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `visitor_ua` text DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `visited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_visited_at` (`visited_at`),
  KEY `idx_visitor_ip` (`visitor_ip`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=302 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `stripe_webhook_events`
--

DROP TABLE IF EXISTS `stripe_webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `stripe_webhook_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stripe_event_id` varchar(255) NOT NULL,
  `event_id` varchar(255) DEFAULT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload`)),
  `status` enum('pending','processed','failed') NOT NULL DEFAULT 'pending',
  `tenant_id` int(11) DEFAULT NULL,
  `retry_count` int(11) NOT NULL DEFAULT 0,
  `error_message` text DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_stripe_event_id` (`stripe_event_id`),
  KEY `idx_entity_tenant` (`tenant_id`),
  KEY `idx_status` (`status`),
  KEY `idx_event_id` (`event_id`)
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `superadmin_tenant_sessions`
--

DROP TABLE IF EXISTS `superadmin_tenant_sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `superadmin_tenant_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `session_id` (`session_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_superadmin_sessions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_superadmin_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `suspicious_ips`
--

DROP TABLE IF EXISTS `suspicious_ips`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `suspicious_ips` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT 1,
  `ip_address` varchar(45) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `attempt_count` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `first_attempt` timestamp NOT NULL DEFAULT current_timestamp(),
  `reason` varchar(255) DEFAULT NULL,
  `blocked` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_address` (`ip_address`),
  KEY `idx_last_attempt` (`last_attempt`),
  KEY `idx_ip_address` (`ip_address`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `system_alerts`
--

DROP TABLE IF EXISTS `system_alerts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `system_alerts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `severity` enum('low','medium','high','critical') NOT NULL DEFAULT 'medium',
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `status` enum('active','resolved','acknowledged') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by` int(11) DEFAULT NULL,
  `acknowledged_at` timestamp NULL DEFAULT NULL,
  `acknowledged_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_severity` (`status`,`severity`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_severity_created` (`severity`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenant_notification_log`
--

DROP TABLE IF EXISTS `tenant_notification_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_notification_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `data_envio` datetime DEFAULT current_timestamp(),
  `status` varchar(20) DEFAULT 'enviado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tenant_tipo` (`tenant_id`,`tipo`),
  KEY `idx_tipo_data` (`tipo`,`data_envio`),
  CONSTRAINT `tenant_notification_log_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `tenants`
--

DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `company_subdomain` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` enum('active','inactive','trial') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `max_users` int(11) DEFAULT NULL COMMENT 'Limite de usuários. NULL = ilimitado',
  `plano` varchar(50) DEFAULT 'basico',
  `data_expiracao` datetime DEFAULT NULL,
  `stripe_customer_id` varchar(255) DEFAULT NULL,
  `stripe_subscription_id` varchar(255) DEFAULT NULL,
  `limite_usuarios` int(11) DEFAULT 2,
  `company_subdomain_created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  UNIQUE KEY `company_subdomain` (`company_subdomain`),
  UNIQUE KEY `idx_company_subdomain` (`company_subdomain`)
) ENGINE=InnoDB AUTO_INCREMENT=323 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `is_superadmin` tinyint(1) DEFAULT 0,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('superadmin','admin','user') NOT NULL DEFAULT 'user',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `force_password_change` tinyint(1) DEFAULT 1,
  `password_changed_at` datetime DEFAULT NULL,
  `last_login_attempt` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `broker_default_commission_percentage` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username_unique` (`username`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_superadmin` (`is_superadmin`),
  KEY `idx_tenant_role` (`tenant_id`,`role`),
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=163 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `utm_campaigns`
--

DROP TABLE IF EXISTS `utm_campaigns`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utm_campaigns` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `campaign_name` varchar(100) NOT NULL,
  `source` varchar(50) NOT NULL,
  `medium` varchar(50) NOT NULL,
  `content` varchar(255) DEFAULT NULL,
  `utm_link` text NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `campaign_name` (`campaign_name`),
  UNIQUE KEY `utm_link` (`utm_link`) USING HASH,
  KEY `idx_source` (`source`),
  KEY `idx_medium` (`medium`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `utm_tracking`
--

DROP TABLE IF EXISTS `utm_tracking`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `utm_tracking` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `campaign_id` int(11) DEFAULT NULL,
  `utm_source` varchar(50) DEFAULT NULL,
  `utm_medium` varchar(50) DEFAULT NULL,
  `utm_campaign` varchar(100) DEFAULT NULL,
  `utm_content` varchar(255) DEFAULT NULL,
  `utm_term` varchar(255) DEFAULT NULL,
  `clicked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_ip` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `referrer` text DEFAULT NULL,
  `destination_url` text DEFAULT NULL,
  `lead_id` int(11) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `conversion` tinyint(1) DEFAULT 0,
  `converted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lead_id` (`lead_id`),
  KEY `property_id` (`property_id`),
  KEY `idx_campaign_id` (`campaign_id`),
  KEY `idx_clicked_at` (`clicked_at`),
  KEY `idx_conversion` (`conversion`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `utm_tracking_ibfk_1` FOREIGN KEY (`campaign_id`) REFERENCES `utm_campaigns` (`id`),
  CONSTRAINT `utm_tracking_ibfk_2` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`),
  CONSTRAINT `utm_tracking_ibfk_3` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_audit_logs`
--

DROP TABLE IF EXISTS `webhook_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) DEFAULT NULL,
  `provider` varchar(64) NOT NULL,
  `event_type` varchar(128) NOT NULL,
  `status` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_audit_tenant_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_events`
--

DROP TABLE IF EXISTS `webhook_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `event_type` varchar(50) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `event_hash` varchar(64) DEFAULT NULL,
  `triggered_by_user_id` int(11) DEFAULT NULL,
  `data` longtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_entity_type` (`entity_type`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  KEY `idx_webhook_events_tenant_event_hash` (`tenant_id`,`event_hash`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_logs`
--

DROP TABLE IF EXISTS `webhook_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `webhook_id` int(11) NOT NULL,
  `status_code` int(11) DEFAULT NULL,
  `response` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_webhook_id` (`webhook_id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `webhook_logs_ibfk_1` FOREIGN KEY (`webhook_id`) REFERENCES `webhooks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhook_subscriptions`
--

DROP TABLE IF EXISTS `webhook_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhook_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `url` varchar(500) NOT NULL,
  `active` tinyint(1) DEFAULT 1,
  `secret` varchar(255) DEFAULT NULL,
  `last_retry_at` datetime DEFAULT NULL,
  `retry_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_event_type` (`event_type`),
  KEY `idx_active` (`active`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `webhook_subscriptions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `webhooks`
--

DROP TABLE IF EXISTS `webhooks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `webhooks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `url` varchar(500) NOT NULL,
  `eventos` varchar(500) NOT NULL COMMENT 'Lista separada por v??rgula: property.created,property.updated,etc',
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_triggered` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_active` (`active`),
  KEY `idx_tenant_id` (`tenant_id`),
  CONSTRAINT `webhooks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping events for database 'sys_imoveis'
--

--
-- Dumping routines for database 'sys_imoveis'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-10-27 16:04:39
