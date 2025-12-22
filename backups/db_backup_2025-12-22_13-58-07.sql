-- Backup do banco de dados rr_imoveis
-- Data: 2025-12-22 13:58:07
SET FOREIGN_KEY_CHECKS=0;

DROP TABLE IF EXISTS `agendamentos`;
CREATE TABLE `agendamentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `leads_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `data_agendamento` datetime NOT NULL,
  `status` varchar(50) DEFAULT 'confirmado' COMMENT 'confirmado, cancelado, realizado',
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `leads_id` (`leads_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `agendamentos_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE,
  CONSTRAINT `agendamentos_ibfk_2` FOREIGN KEY (`leads_id`) REFERENCES `leads` (`id`) ON DELETE SET NULL,
  CONSTRAINT `agendamentos_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `agendamentos` (`id`, `property_id`, `leads_id`, `user_id`, `data_agendamento`, `status`, `descricao`, `created_at`, `updated_at`) VALUES
('2', '79', NULL, '1', '2025-11-17 01:00:00', 'realizado', 'VAMOS TODOS DE CARRO? BLZA MARAVILHA\n', '2025-12-11 14:21:15', '2025-12-11 14:53:37');

DROP TABLE IF EXISTS `banners`;
CREATE TABLE `banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `image_path` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `order_position` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `button_text` varchar(50) DEFAULT NULL,
  `mobile_image_path` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `banners` (`id`, `title`, `description`, `image_path`, `link`, `active`, `order_position`, `created_at`, `updated_at`, `button_text`, `mobile_image_path`) VALUES
('1', 'Lançamento Residencial Jardins', 'Apartamentos de alto padrão a partir de R$ 650.000', '/assets/imagens/banners/banner1.jpg', '/lancamento-jardins', '1', '1', '2025-08-08 15:24:51', '2025-08-22 15:28:10', NULL, NULL),
('2', 'Casas em Alphaville', 'Conheça nosso portfólio de casas em condomínios', '/assets/imagens/banners/banner2.jpg', '/casas-alphaville', '1', '2', '2025-08-08 15:24:51', '2025-08-22 15:28:04', NULL, NULL),
('3', 'Salas Comerciais', 'Aluguel de salas comerciais em localização privilegiada', '/assets/imagens/banners/banner3.jpg', '/comercial', '1', '3', '2025-08-08 15:24:51', '2025-08-22 15:28:25', NULL, NULL),
('6', 'Casas de Altissimo padrão', 'Agende sua visita conosco', 'assets/imagens/banners/banner_693abfb3a68ca.jpg', NULL, '1', '0', '2025-12-11 09:57:23', '2025-12-11 09:58:34', NULL, NULL);

DROP TABLE IF EXISTS `company_settings`;
CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `company_settings` (`id`, `company_name`, `company_email`, `company_email2`, `company_phone`, `company_whatsapp`, `company_address`, `company_weekday_hours`, `company_saturday_hours`, `company_description`, `company_facebook`, `company_instagram`, `company_linkedin`, `company_youtube`, `company_logo`, `company_color1`, `company_color2`, `company_font`, `updated_at`, `map_coordinates`, `business_hours`, `creci`, `company_color3`, `email_notifications`, `email_leads`, `notify_new_lead`, `notify_new_property`, `notify_property_status`, `notify_contact_form`, `notify_agendamento`) VALUES
('1', 'DJ Imóveis', 'contato@rrimoveis.com.br', 'vendas@rrimoveis.com.br', '(11)3456-7890', '5521965025035', 'Av. Paulista, 1000 - Bela Vista, São Paulo - SP', '8h às 17h', '9 às 13h', 'Há mais de 15 anos no mercado imobiliário, a RR Imóveis se destaca pela excelência e compromisso com seus clientes. Especializada em imóveis de alto padrão e investimentos seguros.', 'https://facebook.com/rrimoveis', 'https://instagram.com/rrimoveis', 'https://linkedin.com/company/rrimoveis', 'https://youtube.com/rrimoveis', 'assets/imagens/logo/logo.png', '#0f2f76', '#0b410c', 'Roboto', '2025-12-22 12:05:04', NULL, NULL, NULL, '#d97706', 'ozonvitanatural@gmail.com', 'ozonvitanatural@gmail.com', '0', '0', '1', '1', '1');

DROP TABLE IF EXISTS `financeiro`;
CREATE TABLE `financeiro` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL COMMENT 'receita, despesa, comissao',
  `descricao` varchar(255) DEFAULT NULL,
  `valor` decimal(10,2) NOT NULL,
  `data_transacao` date NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `status` varchar(50) DEFAULT 'pendente' COMMENT 'pendente, concluído, cancelado',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `financeiro_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `financeiro_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `financeiro` (`id`, `property_id`, `user_id`, `tipo`, `descricao`, `valor`, `data_transacao`, `categoria`, `status`, `created_at`, `updated_at`) VALUES
('4', NULL, '1', 'receita', 'CASA VERANEIO DE PRAIA , NO PIX', '10000.00', '2025-12-11', 'Serviço', 'concluído', '2025-12-11 14:37:30', '2025-12-11 14:43:11'),
('5', NULL, '1', 'comissao', 'daniel jardim, vendeu a casa marimar ', '7000.00', '2025-12-11', 'Comissão', 'pendente', '2025-12-11 14:38:32', '2025-12-11 14:38:32'),
('7', NULL, '1', 'comissao', 'Venda da casa por Vitoria Jardim', '5200.00', '2025-12-12', 'Venda', 'concluído', '2025-12-12 15:56:39', '2025-12-12 15:57:53'),
('8', NULL, '1', 'receita', 'Venda do imovel pela corretora Vitoria Jardim . 50% COMISSAO PARA CORRETORA', '5200.00', '2025-12-12', 'Venda', 'pendente', '2025-12-12 15:58:27', '2025-12-12 15:59:36');

DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `assigned_user_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','negotiating','closed','cancelled') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `idx_leads_status` (`status`),
  KEY `fk_leads_assigned_user` (`assigned_user_id`),
  CONSTRAINT `fk_leads_assigned_user` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=220 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leads` (`id`, `name`, `email`, `phone`, `property_id`, `assigned_user_id`, `message`, `status`, `created_at`, `updated_at`, `source`, `notes`) VALUES
('217', 'DANIEL Lider', 'lidercarioca@gmail.com', '21982064891', '73', '10', 'Olá, tenho interesse no imóvel:\n\nTítulo: Terreno Comercial\nTipo: Terreno\nSituação: À Venda\nStatus: Ativo\nLocalização: Rua do Comércio\nPreço: R$ 250.000,00\n\n\nÁrea: 800.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', 'new', '2025-12-18 10:49:06', '2025-12-18 10:49:06', 'site', NULL),
('218', 'DANIEL Lider', 'ozonvitanatural@gmail.com', '21982064891', '81', '10', 'Olá, tenho interesse no imóvel:\n\nTítulo: Casa de Conjunto\nTipo: Casa\nSituação: Para Alugar\nStatus: Vendido\nLocalização: R IBIA 517\nPreço: R$ 1.950.000,00\nQuartos: 2\nBanheiros: 1\nÁrea: 110.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', 'new', '2025-12-19 11:53:05', '2025-12-19 11:53:05', 'site', NULL),
('219', 'joao marcio', 'jaomarcio@xg.com', '84996457182', '80', '10', 'Olá, tenho interesse no imóvel:\n\nTítulo: Casa de Praia\nTipo: Casa\nSituação: À Venda\nStatus: Ativo\nLocalização: Av. Beira Mar\nPreço: R$ 600.000,00\nQuartos: 4\nBanheiros: 3\nÁrea: 250.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', 'new', '2025-12-22 11:17:22', '2025-12-22 11:17:22', 'site', NULL);

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `success` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_username_ip_time` (`username`,`ip_address`,`attempt_time`),
  KEY `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `login_attempts` (`id`, `username`, `ip_address`, `attempt_time`, `success`) VALUES
('1', 'admin', '::1', '2025-12-22 11:38:57', '1'),
('2', 'demo', '::1', '2025-12-22 13:00:09', '1'),
('3', 'Vitoria', '::1', '2025-12-22 13:00:19', '1'),
('4', 'admin', '::1', '2025-12-22 13:00:27', '1'),
('5', 'Vitoria', '::1', '2025-12-22 13:00:45', '1'),
('6', 'admin', '::1', '2025-12-22 13:05:48', '1');

DROP TABLE IF EXISTS `login_attempts_log`;
CREATE TABLE `login_attempts_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL,
  `success` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts` (`username`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=146 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=267 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`, `user_id`) VALUES
('264', 'lead', 'Novo Interesse: Casa de Praia', 'Novo contato de joao marcio (jaomarcio@xg.com) no imóvel: Casa de Praia\n\nMensagem: Olá, tenho interesse no imóvel:\n\nTítulo: Casa de Praia\nTipo: Casa\nSituação: À Venda\nStatus: Ativo\nLo...', 'painel.php?tab=leads', '1', '2025-12-22 11:17:22', '10');

DROP TABLE IF EXISTS `password_history`;
CREATE TABLE `password_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `old_password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_password_history` (`user_id`,`created_at`),
  CONSTRAINT `password_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(128) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`(64)),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `password_resets` (`id`, `user_id`, `token`, `expires_at`, `used`, `created_at`) VALUES
('4', '9', '587c8bb43793f1638f078a5c5dc0da3c1e2b957cf01523a69f5b24fddb556ec4', '2025-12-18 13:11:23', '1', '2025-12-18 12:11:23'),
('5', '1', 'bd2c75eb54d29265cccc3f8197775f6cd84279beed3f91b37be9f1a155249484', '2025-12-18 13:15:36', '1', '2025-12-18 12:15:36'),
('6', '10', '00e71e3f7e48613a2de85ba4530034dcc95022311ca0b0d1d2c0ed2501643689', '2025-12-18 13:34:38', '0', '2025-12-18 12:34:38'),
('7', '9', 'e898ceefce21c2c6548020645e21b1d0c686d53186dc9324a275c996bd17f33f', '2025-12-18 14:02:02', '1', '2025-12-18 13:02:02'),
('8', '1', '43732a2724b86740e63a02cde8b2e37ea5c06ea7926247aea94fce10c416d875', '2025-12-22 11:17:17', '0', '2025-12-22 10:17:17'),
('9', '1', '4e75df8a72733f944f78630a12007921f69da794853c93eb935202ec0ad12f3c', '2025-12-22 11:37:34', '0', '2025-12-22 10:37:34'),
('10', '1', 'c98fb418169719c56a49490675d5c69d3bad5811a6bdcb403e14e52015cbff0d', '2025-12-22 11:56:36', '0', '2025-12-22 10:56:36');

DROP TABLE IF EXISTS `properties`;
CREATE TABLE `properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
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
  PRIMARY KEY (`id`),
  KEY `idx_properties_status` (`status`),
  KEY `idx_properties_type` (`type`),
  KEY `idx_properties_transaction_type` (`transactionType`)
) ENGINE=InnoDB AUTO_INCREMENT=10006 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `properties` (`id`, `title`, `type`, `transactionType`, `price`, `area`, `bedrooms`, `bathrooms`, `garage`, `description`, `location`, `neighborhood`, `city`, `state`, `features`, `status`, `created_at`, `updated_at`, `coordinates`, `video_url`, `featured`, `yearBuilt`, `zip`, `assigned_user_id`, `images`, `condominium`, `iptu`, `suites`) VALUES
('65', 'Apartamento', 'apartment', 'venda', '1000000.00', '55.00', '1', '1', '1', 'Cobertura Duplex de Alto Padrão — Luxo, Conforto e Privacidade\r\nApresentamos uma cobertura duplex de 420 m², projetada por escritório de arquitetura renomado e concluída com materiais premium. O living envidraçado conecta-se a uma ampla varanda gourmet com churrasqueira e piscina de borda infinita, oferecendo vista panorâmica da cidade e do mar. São 4 suítes plenas com closet e acabamentos em marcenaria personalizada; suíte master com banheiro Sr. e Sra., banheira de imersão e varanda privativa.\r\n\r\nCozinha gourmet com ilha central, despensa técnica e área de serviço completa. Espaços de convivência com pé-direito duplo, lareira e home cinema integrado a um sistema de som embutido. Projeto de iluminação DALI, ar-condicionado VRF e automação que controla iluminação, cortinas, áudio e segurança via app. 4 vagas na garagem com depósito privativo.\r\n\r\nLocalização privilegiada, ao lado de serviços exclusivos, restaurantes e acesso rápido às principais vias. Ideal para quem busca um estilo de vida sofisticado sem abrir mão de conforto e privacidade.', 'Av pastor mastin luther kink junior n 9007', 'colégio', 'Rio de Janeiro', 'RJ', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Elevador\",\"Churrasqueira\",\"Aceita Pet\"]', 'active', '2025-08-28 16:02:55', '2025-12-08 13:41:58', NULL, NULL, '0', '2010', '', NULL, NULL, NULL, NULL, NULL),
('66', 'Sala Comercial', 'commercial', 'aluguel', '7800.00', '456.00', '0', '1', '2', 'SHOW', 'Praça Sumaré', 'Potengi', 'Natal', 'RN', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Elevador\",\"Churrasqueira\"]', 'active', '2025-08-28 16:10:58', '2025-12-11 15:01:46', NULL, NULL, '0', '2019', '59124-500', NULL, NULL, NULL, NULL, NULL),
('70', 'Amplo e Bem Localizado', 'land', 'aluguel', '1500.00', '256.00', '0', '0', '0', 'Perto de tudo', 'Rua Maria Betânia de Vasconcelos, 108', 'Jardins', 'São Gonçalo do Amarante', 'RN', '[\"Piscina\",\"Academia\",\"Elevador\",\"Aceita Pet\"]', 'active', '2025-09-01 17:01:35', '2025-12-11 15:05:09', NULL, NULL, '0', '2019', '59.293-189', NULL, NULL, NULL, NULL, NULL),
('71', 'Terreno Central', 'commercial', 'aluguel', '8720.00', '500.00', '0', '0', '0', 'Terreno plano, ótima localização.', 'Rua das Flores', 'Centro', 'Cidade A', 'SP', '[]', 'active', '2025-09-02 11:53:02', '2025-10-15 14:13:35', NULL, NULL, '0', '2020', '12345-000', NULL, '[\"terreno1.jpg\"]', NULL, NULL, NULL),
('72', 'Luxo\'s house', 'house', 'venda', '18000000.00', '350.00', '5', '3', '4', 'Residencia de luxo para ricos morarem', 'Rua praia linda', 'IPANEMA', 'Rio de Janeiro', 'RJ', '[\"Piscina\",\"Jardim\",\"Academia\",\"Elevador\",\"Churrasqueira\",\"Mobiliado\",\"Aceita Pet\"]', 'active', '2025-09-02 11:53:02', '2025-10-17 15:46:43', NULL, NULL, '0', '2021', '23456-000', NULL, '[\"terreno2.jpg\"]', NULL, NULL, NULL),
('73', 'Terreno Comercial', 'land', 'venda', '250000.00', '800.00', '0', '0', '0', 'Terreno comercial, esquina movimentada.', 'Rua do Comércio', 'Comercial', 'Cidade C', 'MG', '[]', 'active', '2025-09-02 11:53:02', '2025-12-18 10:34:29', NULL, NULL, '0', NULL, '34567-000', '10', '[\"terreno3.jpg\"]', '2000.00', '4500.00', NULL),
('74', 'Terreno Rural', 'land', 'venda', '180000.00', '2.00', '0', '0', '0', 'Terreno rural, ideal para sítio.', 'Estrada Rural', 'Zona Rural', 'Cidade D', 'RS', '[]', 'active', '2025-09-02 11:53:02', '2025-10-14 10:00:01', NULL, NULL, '0', '2018', '45678-000', NULL, '[\"terreno4.jpg\"]', NULL, NULL, NULL),
('75', 'Terreno Industrial', 'land', 'venda', '400000.00', '1.50', '0', '0', '0', 'Terreno industrial, fácil acesso.', 'Rodovia 101', 'Industrial', 'Cidade E', 'SC', '[]', 'active', '2025-09-02 11:53:02', '2025-10-14 10:00:11', NULL, NULL, '0', '2017', '56789-000', NULL, '[\"terreno5.jpg\"]', NULL, NULL, NULL),
('76', 'Casa Moderna', 'commercial', 'aluguel', '350000.00', '180.00', '3', '2', '2', 'Casa moderna com piscina.', 'Rua Azul', 'Jardim Sulacap', 'Cidade F', 'SP', '[\"Piscina\",\"Churrasqueira\"]', 'active', '2025-09-02 11:53:02', '2025-12-18 09:13:31', NULL, NULL, '0', '2022', '67890-000', NULL, '[\"casa1.jpg\"]', NULL, '990.00', '1'),
('77', 'Apartamento Luxo', 'apartment', 'venda', '500000.00', '120.00', '2', '2', '1', 'Apartamento de luxo, vista panorâmica.', 'Av. Luxo', 'Centro', 'Cidade G', 'RJ', '[]', 'active', '2025-09-02 11:53:02', '2025-12-16 11:50:05', NULL, NULL, '0', '2023', '78901-000', '10', '[\"apto1.jpg\"]', NULL, NULL, NULL),
('78', 'Casa Simples', 'house', 'venda', '180000.00', '90.00', '2', '1', '1', 'Casa simples, ótima oportunidade.', 'Rua Simples', 'Bairro Novo', 'Cidade H', 'MG', '[]', 'active', '2025-09-02 11:53:02', '2025-12-16 14:08:27', NULL, NULL, '0', '2015', '89012-000', '9', '[\"casa2.jpg\"]', NULL, '1780.00', '2'),
('79', 'Apartamento Compacto', 'apartment', 'venda', '220000.00', '60.00', '1', '1', '1', 'Apartamento compacto, ideal para solteiro.', 'Rua Compacta', 'Centro', 'Cidade I', 'RS', '[\"Elevador\"]', 'active', '2025-09-02 11:53:02', '2025-12-16 14:08:13', NULL, NULL, '0', '2020', '90123-000', '9', '[\"apto2.jpg\"]', '3000.00', '2000.00', '4'),
('80', 'Casa de Praia', 'house', 'venda', '600000.00', '250.00', '4', '3', '2', 'Casa de praia, pé na areia.', 'Av. Beira Mar', 'Praia', 'Cidade J', 'SC', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Elevador\",\"Churrasqueira\"]', 'active', '2025-09-02 11:53:02', '2025-12-16 14:07:57', NULL, NULL, '0', '2021', '01234-000', '10', '[\"casa3.jpg\"]', '200.00', '900.00', '2'),
('81', 'Casa de Conjunto', 'house', 'aluguel', '1950000.00', '110.00', '2', '1', '2', 'EXCELENTE LOCALIZAÇÃO', 'R IBIA 517', 'TURIAÇU', 'Rio de Janeiro', 'RJ', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Churrasqueira\",\"Mobiliado\",\"Aceita Pet\"]', 'vendido', '2025-09-23 12:29:08', '2025-12-18 11:00:02', NULL, NULL, '0', '2024', '21540070', '10', NULL, '1100.00', '1200.00', '4');

DROP TABLE IF EXISTS `property_images`;
CREATE TABLE `property_images` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `image_url` varchar(255) NOT NULL,
  `is_featured` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `image_hash` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_property_images_property_id` (`property_id`),
  CONSTRAINT `property_images_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=138 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `property_images` (`id`, `property_id`, `image_url`, `is_featured`, `created_at`, `image_hash`) VALUES
('103', '66', '68b0a9c28771d.jpg', '0', '2025-08-28 16:10:58', NULL),
('107', '71', '68cab9fbd97f6.jpg', '0', '2025-09-17 10:39:07', NULL),
('108', '80', '68cdba9a42879.jpg', '0', '2025-09-19 17:18:34', NULL),
('113', '81', '68d2bcc4ca47d.jpg', '0', '2025-09-23 12:29:08', NULL),
('114', '81', '68d2bce664a07.jpg', '0', '2025-09-23 12:29:42', NULL),
('115', '81', '68d2bce6659bc.jpg', '0', '2025-09-23 12:29:42', NULL),
('116', '81', '68d2bce666b39.jpg', '0', '2025-09-23 12:29:42', NULL),
('117', '81', '68d2bce6681e9.png', '0', '2025-09-23 12:29:42', NULL),
('118', '72', '68d68ceb9fcb7.jpg', '0', '2025-09-26 09:54:03', NULL),
('120', '70', '68dd8581bbf10.jpg', '0', '2025-10-01 16:48:17', NULL),
('121', '73', '68e11b74af09a.jpg', '0', '2025-10-04 10:04:52', NULL),
('123', '65', '693815690860a.jpg', '0', '2025-12-09 09:26:17', NULL),
('124', '65', '6938166746da9.jpg', '0', '2025-12-09 09:30:31', NULL),
('125', '65', '6938166747b08.jpg', '0', '2025-12-09 09:30:31', NULL),
('126', '65', '69381667489d0.jpg', '0', '2025-12-09 09:30:31', NULL),
('127', '65', '6938166749863.jpg', '0', '2025-12-09 09:30:31', NULL),
('130', '65', '693816674d60f.jpg', '0', '2025-12-09 09:30:31', NULL),
('131', '65', '693816674ebc6.png', '0', '2025-12-09 09:30:31', NULL),
('132', '79', '6938556d3b7cd.jpg', '0', '2025-12-09 13:59:25', NULL),
('133', '78', '69385581ea869.jpg', '0', '2025-12-09 13:59:45', NULL),
('134', '77', '69385596e28f1.jpg', '0', '2025-12-09 14:00:06', NULL),
('135', '80', '69405d62c8a8b.jpg', '0', '2025-12-15 16:11:30', NULL),
('136', '80', '69405d62c9985.jpg', '0', '2025-12-15 16:11:30', NULL),
('137', '76', '6942c367c7876.jpg', '0', '2025-12-17 11:51:19', NULL);

DROP TABLE IF EXISTS `property_portal_sync`;
CREATE TABLE `property_portal_sync` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `portal` varchar(100) NOT NULL,
  `last_exported_at` datetime DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `response` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `portal` (`portal`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `property_portal_sync` (`id`, `property_id`, `portal`, `last_exported_at`, `status`, `response`) VALUES
('1', '81', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('2', '80', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('3', '79', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('4', '78', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('5', '77', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('6', '76', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('7', '75', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('8', '74', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('9', '73', 'zap', '2025-12-08 15:56:50', 'exported', NULL),
('10', '81', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('11', '80', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('12', '79', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('13', '78', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('14', '77', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('15', '76', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('16', '75', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('17', '74', 'olx', '2025-12-08 15:57:24', 'exported', NULL),
('18', '73', 'olx', '2025-12-08 15:57:24', 'exported', NULL);

DROP TABLE IF EXISTS `property_sales`;
CREATE TABLE `property_sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `property_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `property_title` varchar(255) NOT NULL,
  `property_price` decimal(12,2) NOT NULL,
  `commission_6percent` decimal(12,2) NOT NULL,
  `status` enum('pending','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_property_id` (`property_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `property_sales_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `property_sales` (`id`, `property_id`, `user_id`, `username`, `property_title`, `property_price`, `commission_6percent`, `status`, `created_at`, `updated_at`) VALUES
('1', '80', '10', 'Vitoria', 'Casa de Praia', '600000.00', '36000.00', 'pending', '2025-12-12 14:37:55', '2025-12-12 14:37:55'),
('2', '79', '10', 'Vitoria', 'Apartamento Compacto', '220000.00', '13200.00', 'pending', '2025-12-12 15:24:05', '2025-12-12 15:24:05'),
('3', '78', '10', 'Vitoria', 'Casa Simples', '180000.00', '10800.00', 'pending', '2025-12-12 15:33:22', '2025-12-12 15:33:22'),
('4', '81', '10', 'Vitoria', 'Casa de Conjunto', '1950000.00', '117000.00', 'pending', '2025-12-18 11:00:02', '2025-12-18 11:00:02');

DROP TABLE IF EXISTS `site_visits`;
CREATE TABLE `site_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `visitor_ua` text DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `visited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_visited_at` (`visited_at`),
  KEY `idx_visitor_ip` (`visitor_ip`),
  KEY `idx_session_id` (`session_id`)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `site_visits` (`id`, `visitor_ip`, `visitor_ua`, `page_url`, `referrer`, `visited_at`, `session_id`) VALUES
('1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-09 12:39:31', 'h758uvd10bohbvuhpcnrp29ugi'),
('2', '192.168.1.100', 'Mozilla/5.0', '/todos-imoveis.html', 'google.com', '2025-12-09 13:01:25', 'sess_123'),
('3', '192.168.1.101', 'Mozilla/5.0', '/', 'facebook.com', '2025-12-09 13:01:25', 'sess_124'),
('4', '192.168.1.102', 'Mozilla/5.0', '/todos-imoveis.html', '', '2025-12-09 13:01:25', 'sess_125'),
('5', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-09 14:00:14', '40avvuicvne28mu5n5mrfd2r49'),
('6', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-09 17:26:44', 'f1nvp5ipn71dkkn8jqun14oiim'),
('7', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-10 11:52:25', '15335i6224dhqm6kbcctdpssfh'),
('8', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-10 13:02:38', 'aakuqh0667juo82raqfp81eta0'),
('9', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-10 13:54:22', 'p4pqh3s87c181m3f2e6m8sn5q4'),
('10', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-10 13:55:11', '5fpon7sm8c86nqtff30ijah2vo'),
('11', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-11 09:39:25', '39kqv6bh9ke7vf66eng2c1vslg'),
('12', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-11 09:41:18', 'd855kd0uaosskrhp56eogtfng5'),
('13', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://127.0.0.1/', '2025-12-11 13:56:48', 'k9d0hrvo2an3n44cerr5v35evt'),
('14', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-11 14:48:43', '37s688oq9mg2fd8handiud76lo'),
('15', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-12 10:11:33', 'pcc5nes8vpddrhqfg4us2qcl68'),
('16', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-12 11:25:15', 'rqppsp3qn1q9esqhnbd3c947f7'),
('17', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-12 13:21:45', 'pr2elrsikud9v0q90j5brth85h'),
('18', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'https://localhost/', '2025-12-12 14:16:02', '5o2m8nvdome14gsgefpf9nln2f'),
('19', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-15 10:33:09', 'v07164hit944c5jo65ja8gqtan'),
('20', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'https://localhost/', '2025-12-15 13:19:53', 'pe7krqlsg2u104tdfn371qah73'),
('21', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'https://localhost/', '2025-12-15 14:11:21', 'orke7ji833nk71g9smpj6gumf6'),
('22', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-15 14:58:17', '4rjvpcpjmm0jliu4mu44t7gn1m'),
('23', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'https://localhost/', '2025-12-15 14:59:18', 'uvm5e2sirvtiv50hskp7r9hsmd'),
('24', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'https://localhost/', '2025-12-15 15:38:22', '9ctdgi113d41i7mpkpn9e7h0ea'),
('25', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-15 16:11:38', 'gr408sv4ot5b4q6udul3sfuj0l'),
('26', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-15 16:29:40', 'hcifaci5fjcnq9bb7b4q9gb38g'),
('27', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-16 09:46:54', '36bmppthlptgb1i9hvbh7aenvf'),
('28', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-16 09:48:37', '697ijn6pa63q74dl844s4kdkaj'),
('29', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-16 13:32:47', 'g4tqgnd2dv7gk9ja5jm29uflbe'),
('30', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-17 09:01:31', 'c75vptj2gqpheio1ojbcn0c31u'),
('31', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-17 09:07:15', 'rgaeumqs1vh7o19lobebjeetnf'),
('32', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-17 09:09:33', 'qu95hs585opc45kq7ft2clfp1d'),
('33', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/index.html', 'http://localhost/views/public/index.html', '2025-12-17 09:34:07', 'tq7b40sgi55tqvo70b7mq77olt'),
('34', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-18 09:12:04', '1egs0sk1mskjk1b5ubm4dakkdf'),
('35', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-18 09:13:58', 'r15qlcth94qu4imj9l1krd32vi'),
('36', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-18 10:55:18', 'dqkt2ghluusf82k84pr2s844bf'),
('37', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-18 11:09:49', '8s1j2tgl5mll3rmrt4eqnjc0n4'),
('38', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-18 11:12:07', 'j6jbprav9efjacv9c28rdbn438'),
('39', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-18 16:56:27', '5tfsvibrrqnapihno7smb7ridb'),
('40', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-18 17:02:03', 'mml9teo5bf9ahcdqcviueu01p6'),
('41', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-18 17:08:56', 'd5d7k8fral5do7mkk8b3kub70o'),
('42', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-18 17:11:37', 'rtvnajm7kmjactabms4e39qi7r'),
('43', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-18 17:21:49', 'g8b24nrde2gdf6mm6rcifa53el'),
('44', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-19 11:25:08', '5eemt97tbinjlpbke7faved0b0'),
('45', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-19 11:51:44', 'ek941ucubqt2jt6rlelh1hb2kh'),
('46', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-19 14:22:12', 'rj2ptgg65nlbch0tfmgequh1m6'),
('47', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/?property=81', '2025-12-19 15:05:59', 'hvk4gh07j4povbe9bochn79pq3'),
('48', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-19 15:17:20', '5ibga2a6iabq7jf4msdm7ca2fm'),
('49', '177.89.235.107', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/index.html', 'https://eleven-socks-greet.loca.lt/views/public/index.html', '2025-12-19 16:26:07', 'q634405gi7hev4m85dfmaasph3'),
('50', '177.89.235.107', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/index.html', 'https://young-kids-accept.loca.lt/views/public/index.html', '2025-12-19 16:34:41', 'q8pm09qcjq5v0589ir3rdmh240'),
('51', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Code/1.107.1 Chrome/142.0.7444.175 Electron/39.2.3 Safari/537.36', '/', 'http://localhost:8000/?property=80', '2025-12-19 16:43:43', 'qajqcarui0nc6st9h802emk1sf'),
('52', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-19 17:07:25', 'qrnfcvf317bh1r9h9067bqeso7'),
('53', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-22 10:11:16', '2lkvr8s0v6ququq5bdarcup7nq'),
('54', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-22 10:11:50', '7ebmts0gbdm63gqeobp3rsol95'),
('55', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/', 'http://localhost/', '2025-12-22 10:37:11', '0phsfmh99t2lfva6me3g17uji8'),
('56', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '/views/public/todos-imoveis.html', 'http://localhost/views/public/todos-imoveis.html', '2025-12-22 11:03:52', 'dv2q65s7q17fdoimoogevijfd8');

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `force_password_change` tinyint(1) DEFAULT 1,
  `password_changed_at` datetime DEFAULT NULL,
  `last_login_attempt` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username_unique` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`, `email`, `created_at`, `last_login`, `active`, `force_password_change`, `password_changed_at`, `last_login_attempt`, `login_attempts`) VALUES
('1', 'admin', '$2y$10$w.XvKaEc3uyftA5ohIdZ0.HNTwcrpqVF0/Zq6fM9akd.YyGrSqeaG', 'admin', 'DJ', 'lidercarioca@gmail.com', '2025-08-14 15:16:40', '2025-12-22 13:05:48', '1', '1', NULL, NULL, '0'),
('9', 'demo', '$2y$10$rV13VRhaYfgttnKeZA4r/e/DfTPxmzF9gW8wg0Kyi5rL.Y1PnZQjW', 'user', 'demo', 'demo@demo.com', '2025-10-06 10:32:54', '2025-12-22 13:00:09', '1', '1', NULL, NULL, '0'),
('10', 'Vitoria', '$2y$10$FeSX5hWI6dbh/mHAES/xmeTj1hiwsDfAUqvNSP0kz3ZW2aUINz8ey', 'admin', 'Vitoria Jardim', 'lidercarioca2@gmail.com', '2025-12-11 14:50:25', '2025-12-22 13:00:45', '1', '1', NULL, NULL, '0');

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=1;
