-- Backup do banco de dados rr_imoveis
-- Data: 2025-09-20 16:18:39
SET FOREIGN_KEY_CHECKS=0;

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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `banners` (`id`, `title`, `description`, `image_path`, `link`, `active`, `order_position`, `created_at`, `updated_at`, `button_text`, `mobile_image_path`) VALUES
('1', 'Lançamento Residencial Jardins', 'Apartamentos de alto padrão a partir de R$ 650.000', '/assets/imagens/banners/banner1.jpg', '/lancamento-jardins', '1', '1', '2025-08-08 15:24:51', '2025-08-22 15:28:10', NULL, NULL),
('2', 'Casas em Alphaville', 'Conheça nosso portfólio de casas em condomínios', '/assets/imagens/banners/banner2.jpg', '/casas-alphaville', '1', '2', '2025-08-08 15:24:51', '2025-08-22 15:28:04', NULL, NULL),
('3', 'Salas Comerciais', 'Aluguel de salas comerciais em localização privilegiada', '/assets/imagens/banners/banner3.jpg', '/comercial', '1', '3', '2025-08-08 15:24:51', '2025-08-22 15:28:25', NULL, NULL);

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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `company_settings` (`id`, `company_name`, `company_email`, `company_email2`, `company_phone`, `company_whatsapp`, `company_address`, `company_weekday_hours`, `company_saturday_hours`, `company_description`, `company_facebook`, `company_instagram`, `company_linkedin`, `company_youtube`, `company_logo`, `company_color1`, `company_color2`, `company_font`, `updated_at`, `map_coordinates`, `business_hours`, `creci`, `company_color3`, `email_notifications`, `email_leads`, `notify_new_lead`, `notify_new_property`, `notify_property_status`, `notify_contact_form`) VALUES
('1', 'RR Imóveis', 'contato@rrimoveis.com.br', 'vendas@rrimoveis.com.br', '(11) 3456-7890', '(11) 98765-4321', 'Av. Paulista, 1000 - Bela Vista, São Paulo - SP', '9h às 17h', '9 às 14h', 'Há mais de 15 anos no mercado imobiliário, a RR Imóveis se destaca pela excelência e compromisso com seus clientes. Especializada em imóveis de alto padrão e investimentos seguros.wedwert', 'https://facebook.com/rrimoveis', 'https://instagram.com/rrimoveis', 'https://linkedin.com/company/rrimoveis', 'https://youtube.com/rrimoveis', 'assets/imagens/logo/logo.png', '#2563eb', '#10b981', 'Roboto', '2025-09-18 16:29:48', NULL, NULL, NULL, '#f59e0b', 'ozonvitanatural@gmail.com', 'ozonvitanatural@gmail.com', '1', '1', '1', '1');

DROP TABLE IF EXISTS `leads`;
CREATE TABLE `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `property_id` int(11) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('new','contacted','negotiating','closed','cancelled') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `source` varchar(50) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `property_id` (`property_id`),
  KEY `idx_leads_status` (`status`),
  CONSTRAINT `leads_ibfk_1` FOREIGN KEY (`property_id`) REFERENCES `properties` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=67 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leads` (`id`, `name`, `email`, `phone`, `property_id`, `message`, `status`, `created_at`, `updated_at`, `source`, `notes`) VALUES
('1', 'Maria Silva', 'maria.silva@email.com', '(11) 98765-1234', NULL, 'Tenho interesse neste apartamento. Gostaria de agendar uma visita para o próximo final de semana.', 'new', '2025-08-08 15:21:05', '2025-08-08 15:21:05', NULL, NULL),
('2', 'João Santos', 'joao.santos@email.com', '(11) 97654-5678', NULL, 'Procuro uma casa em condomínio fechado. Este imóvel ainda está disponível?', 'contacted', '2025-08-08 15:21:05', '2025-08-08 15:21:05', NULL, NULL),
('3', 'Ana Paula Ferreira', 'ana.ferreira@empresa.com.br', '(11) 96543-2109', NULL, 'Interesse na sala comercial para locação. Qual a disponibilidade para visitas?', 'new', '2025-08-08 15:21:05', '2025-09-19 14:09:26', NULL, NULL),
('47', 'vinicius junior', 'lideremespancao@junior.com', '(21) 98206-4891', NULL, 'avaliem meu imovel', 'new', '2025-09-13 18:53:26', '2025-09-13 18:53:26', 'site', NULL),
('48', 'Ozonvita', 'ozonvitanatural@gmail.com', '(84) 99645-7181', NULL, 'vamos com tudo mesmo hoje', 'new', '2025-09-13 20:44:32', '2025-09-13 20:44:32', 'site', NULL),
('49', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'contacted', '2025-09-13 21:04:26', '2025-09-19 14:09:13', 'site', NULL),
('50', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'new', '2025-09-13 21:04:26', '2025-09-13 21:04:26', 'site', NULL),
('51', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'new', '2025-09-13 21:04:26', '2025-09-13 21:04:26', 'site', NULL),
('52', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'new', '2025-09-13 21:04:50', '2025-09-13 21:04:50', 'site', NULL),
('53', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'new', '2025-09-13 21:04:50', '2025-09-13 21:04:50', 'site', NULL),
('54', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'new', '2025-09-13 21:04:50', '2025-09-13 21:04:50', 'site', NULL),
('55', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'new', '2025-09-13 21:04:50', '2025-09-13 21:04:50', 'site', NULL),
('56', 'jao cana brava', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', 'negotiating', '2025-09-13 21:06:54', '2025-09-19 16:48:20', 'site', NULL),
('57', 'Ozonvita', 'ozonvitanatural@gmail.com', '84996457182', NULL, 'Olá, tenho interesse em mais informações sobre imóveis.', 'contacted', '2025-09-15 17:25:36', '2025-09-19 14:09:16', 'site', NULL),
('58', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', '84996457182', NULL, 'Olá, tenho interesse em mais informações sobre imóveis.', 'closed', '2025-09-15 17:28:01', '2025-09-19 14:09:18', 'site', NULL),
('59', 'DANIEL Lider', 'porpinomaiza+1@gmail.com', '21982064891', NULL, 'Olá, tenho interesse em mais informações sobre imóveis.', 'cancelled', '2025-09-15 17:30:41', '2025-09-19 17:19:55', 'site', 'o');

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
) ENGINE=InnoDB AUTO_INCREMENT=48 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `messages` (`id`, `from_name`, `from_email`, `subject`, `message`, `is_read`, `created_at`, `property_id`, `user_id`) VALUES
('1', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', '[Compra de imóvel] vamos acelerar a venda', '0', '2025-09-03 11:20:30', NULL, NULL),
('2', 'joao vicente', 'jsedubarros@gmail.com', 'Novo contato do site', '[Venda de imóvel] venda do imvel', '0', '2025-09-03 11:23:23', NULL, NULL),
('3', 'DANIEL F JARDIM', 'porpinomaiza+1@gmail.com', 'Novo contato do site', '[Compra de imóvel] quero este imovel', '0', '2025-09-03 11:26:58', NULL, NULL),
('4', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] vamos, aparesça', '0', '2025-09-03 11:29:48', NULL, NULL),
('5', 'OZONVITA', 'lidercarioca@gmail.com', 'Contato através do site', '[Compra de imóvel] MOSTRE ISSO', '0', '2025-09-03 11:33:48', NULL, NULL),
('6', 'OZONVITA', 'lidercarioca@gmail.com', 'Novo contato do site', '[Compra de imóvel] MOSTRE ISSO', '0', '2025-09-03 11:33:48', NULL, NULL),
('7', 'OZONVITA', 'lidercarioca@gmail.com', 'Contato através do site', '[Compra de imóvel] MOSTRE ISSO', '0', '2025-09-03 11:33:59', NULL, NULL),
('8', 'OZONVITA', 'lidercarioca@gmail.com', 'Novo contato do site', '[Compra de imóvel] MOSTRE ISSO', '0', '2025-09-03 11:33:59', NULL, NULL),
('9', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] VVV', '0', '2025-09-03 11:35:49', NULL, NULL),
('10', 'SHARAN STONE', 'SHARON@LUZIA.COM', 'Novo contato do site', '[Venda de imóvel] VAMOS VENDER', '0', '2025-09-03 11:38:05', NULL, NULL),
('11', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', '[Aluguel] eee', '0', '2025-09-03 14:00:11', NULL, NULL),
('12', 'DANIEL Lider', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] vate', '0', '2025-09-03 14:02:59', NULL, NULL),
('13', 'jovem de sa', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTipo: land\nLocalização: Estrada Rural\nPreço: R$ 180.000,00\nQuartos: 0\nBanheiros: 0\nÁrea: 2.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-03 14:16:04', NULL, NULL),
('14', 'jovem de sa', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTipo: land\nLocalização: Estrada Rural\nPreço: R$ 180.000,00\nQuartos: 0\nBanheiros: 0\nÁrea: 2.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-03 14:16:04', NULL, NULL),
('15', 'chico cezar', 'juremar@hotmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTipo: land\nLocalização: Estrada Rural\nPreço: R$ 180.000,00\nQuartos: 0\nBanheiros: 0\nÁrea: 2.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-03 14:18:09', NULL, NULL),
('16', 'jose', 'joaomacarrao@coisa.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTipo: land\nLocalização: Rua Maria Betânia de Vasconcelos, 108\nPreço: R$ 150.000,00\nQuartos: 0\nBanheiros: 0\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-03 14:19:58', NULL, NULL),
('17', 'DANIEL Lider', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: CASA\nTipo: Casa\nLocalização: Praça Sumaré\nPreço: R$ 1.500,00\nQuartos: 1\nBanheiros: 1\nÁrea: 34.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-03 14:23:40', NULL, NULL),
('18', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\nQuartos: 0\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-13 13:07:57', NULL, NULL),
('19', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Terreno Residencial\nTipo: Terreno\nSituação: À Venda\nStatus: Ativo\nLocalização: Av. Brasil\nPreço: R$ 95.000,00\n\n\nÁrea: 350.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-13 13:20:21', NULL, NULL),
('20', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'ola como vai vc eu gostaria de estar adquirindo', '0', '2025-09-13 13:22:14', NULL, NULL),
('21', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'quero muito tudo isso', '0', '2025-09-13 13:40:24', NULL, NULL),
('22', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'precisamos muito ouvir vcs', '0', '2025-09-13 13:41:44', NULL, NULL),
('23', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'vamos com tudo', '0', '2025-09-13 13:43:20', NULL, NULL),
('24', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'vamos com tudo', '0', '2025-09-13 13:43:20', NULL, NULL),
('25', 'JULIAO RAMOS', 'lidercarioca@gmail.com', 'Novo contato do site', 'PRECISANDO ALUGAR É SO CHAMAR', '0', '2025-09-13 13:44:22', NULL, NULL),
('26', 'JULIAO RAMOS', 'lidercarioca@gmail.com', 'Novo contato do site', 'PRECISANDO ALUGAR É SO CHAMAR', '0', '2025-09-13 13:44:22', NULL, NULL),
('27', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'VAMOS COMPRAR GENTE', '0', '2025-09-13 13:46:06', NULL, NULL),
('28', 'vinicius junior', 'lideremespancao@junior.com', 'Novo contato do site', 'avaliem meu imovel', '0', '2025-09-13 13:53:26', NULL, NULL),
('29', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'vamos com tudo mesmo hoje', '0', '2025-09-13 15:44:32', NULL, NULL),
('30', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:26', NULL, NULL),
('31', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:26', NULL, NULL),
('32', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:26', NULL, NULL),
('33', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:50', NULL, NULL),
('34', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:50', NULL, NULL),
('35', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:50', NULL, NULL),
('36', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:04:50', NULL, NULL),
('37', 'jao cana brava', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre os imóveis disponíveis.', '0', '2025-09-13 16:06:54', NULL, NULL),
('38', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse em mais informações sobre imóveis.', '0', '2025-09-15 12:25:36', NULL, NULL),
('39', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse em mais informações sobre imóveis.', '0', '2025-09-15 12:28:01', NULL, NULL),
('40', 'DANIEL Lider', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse em mais informações sobre imóveis.', '0', '2025-09-15 12:30:41', NULL, NULL),
('41', 'DANIEL Lider', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse em mais informações sobre imóveis.', '0', '2025-09-15 12:32:22', NULL, NULL),
('42', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre imóveis.', '0', '2025-09-15 12:34:06', NULL, NULL),
('43', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, gostaria de mais informações sobre imóveis.', '0', '2025-09-15 12:34:16', NULL, NULL),
('44', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Interesse no imóvel: Amplo e bem localizado', 'Olá, tenho interesse no imóvel:\n\nTítulo: Amplo e bem localizado\nTipo: Terreno\nSituação: À Venda\nStatus: ativo\nLocalização: Rua Maria Betânia de Vasconcelos, 108\nPreço: R$ 150.000,00\nQuartos: 0\nBanheiros: 0\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-15 12:43:07', '70', NULL),
('45', 'julio ramos', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'vamos comprar', '0', '2025-09-15 12:51:28', NULL, NULL),
('46', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] vamos', '0', '2025-09-16 13:50:07', NULL, NULL),
('47', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] vamos comprar', '0', '2025-09-16 17:57:56', NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=69 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `notifications` (`id`, `type`, `title`, `message`, `link`, `is_read`, `created_at`, `user_id`) VALUES
('1', 'property', 'Imóvel Vendido', 'O imóvel \"Terreno Rural\" foi marcado como Vendido', 'painel.php?tab=properties&property=74', '1', '2025-09-02 13:41:15', NULL),
('2', 'property', 'Imóvel Vendido', 'O imóvel \"Terreno Central\" foi marcado como Vendido', 'painel.php?tab=properties&property=71', '1', '2025-09-02 13:50:30', NULL),
('3', 'property', 'Imóvel Vendido', 'O imóvel \"SALA COMERCIAL\" foi marcado como Vendido', 'painel.php?tab=properties&property=66', '1', '2025-09-02 13:56:00', NULL),
('4', 'lead', 'Novo Lead Recebido', 'Lead recebido de flavio venturine (lidercarioca2@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 14:02:25', NULL),
('5', 'lead', 'Novo Lead Recebido', 'Lead recebido de flavio venturine (lidercarioca2@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 14:02:25', NULL),
('6', 'lead', 'Novo Lead Recebido', 'Lead recebido de SUELI CAMPELO (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 14:14:26', NULL),
('7', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 14:28:07', NULL),
('8', 'system', 'NotificaþÒo de Teste', 'Esta Ú uma notificaþÒo de teste para validar o sistema', 'painel.php', '1', '2025-09-02 16:08:41', NULL),
('9', 'test', 'Teste de ValidaþÒo', 'NotificaþÒo criada para validar o sistema de notificaþ§es', 'painel.php', '1', '2025-09-02 16:11:58', NULL),
('10', 'test', 'NotificaþÒo de Teste', 'Teste de acentuaþÒo: ßÚÝ¾· Ò§ þ±', 'painel.php', '1', '2025-09-02 16:13:14', NULL),
('11', 'lead', 'Novo Lead Recebido', 'Lead recebido de Daniel Fernandes Jardim / OZONTECK NATAL (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 16:15:48', NULL),
('12', 'lead', 'Novo Lead Recebido', 'Lead recebido de Daniel Fernandes Jardim / OZONTECK NATAL (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 16:15:48', NULL),
('13', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL F JARDIM (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-02 16:17:07', NULL),
('14', 'lead', 'Novo Lead Recebido', 'Lead recebido de Sergio Ramos da Fonseca (sergioramosfonseca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:10:02', NULL),
('15', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:17:16', NULL),
('16', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:20:30', NULL),
('17', 'message', 'Nova Mensagem Recebida', 'Mensagem de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com): [Compra de imóvel] vamos acelerar a venda', 'painel.php?tab=messages', '1', '2025-09-03 11:20:30', NULL),
('18', 'lead', 'Novo Lead Recebido', 'Lead recebido de joao vicente (jsedubarros@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:23:23', NULL),
('19', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL F JARDIM (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:26:58', NULL),
('20', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:29:48', NULL),
('21', 'lead', 'Novo Lead Recebido', 'Lead recebido de OZONVITA (lidercarioca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:33:48', NULL),
('22', 'lead', 'Novo Lead Recebido', 'Lead recebido de OZONVITA (lidercarioca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:33:59', NULL),
('23', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 11:35:49', NULL),
('24', 'lead', 'Novo Lead Recebido', 'Lead recebido de SHARAN STONE (SHARON@LUZIA.COM)', 'painel.php?tab=leads', '1', '2025-09-03 11:38:05', NULL),
('25', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:00:11', NULL),
('26', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL Lider (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:02:59', NULL),
('27', 'lead', 'Novo Lead Recebido', 'Lead recebido de jovem de sa (lidercarioca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:16:04', NULL),
('28', 'lead', 'Novo Lead Recebido', 'Lead recebido de jovem de sa (lidercarioca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:16:04', NULL),
('29', 'lead', 'Novo Lead Recebido', 'Lead recebido de chico cezar (juremar@hotmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:18:09', NULL),
('30', 'lead', 'Novo Lead Recebido', 'Lead recebido de jose (joaomacarrao@coisa.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:19:58', NULL),
('31', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL Lider (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-03 14:23:40', NULL),
('32', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:07:57', NULL),
('33', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:20:21', NULL),
('34', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:22:14', NULL),
('35', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL FERNANDES JARDIM (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:40:24', NULL),
('36', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:41:44', NULL),
('37', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:43:20', NULL),
('38', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:43:20', NULL),
('39', 'lead', 'Novo Lead Recebido', 'Lead recebido de JULIAO RAMOS (lidercarioca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:44:22', NULL),
('40', 'lead', 'Novo Lead Recebido', 'Lead recebido de JULIAO RAMOS (lidercarioca@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:44:22', NULL),
('41', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:46:06', NULL),
('42', 'lead', 'Novo Lead Recebido', 'Lead recebido de vinicius junior (lideremespancao@junior.com)', 'painel.php?tab=leads', '1', '2025-09-13 13:53:26', NULL),
('43', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 15:44:32', NULL),
('44', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:26', NULL),
('45', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:26', NULL),
('46', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:26', NULL),
('47', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:50', NULL),
('48', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:50', NULL),
('49', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:50', NULL),
('50', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:04:50', NULL),
('51', 'lead', 'Novo Lead Recebido', 'Lead recebido de jao cana brava (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-13 16:06:54', NULL),
('52', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:25:36', NULL),
('53', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL FERNANDES JARDIM (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:28:01', NULL),
('54', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL Lider (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:30:41', NULL),
('55', 'lead', 'Novo Lead Recebido', 'Lead recebido de DANIEL Lider (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:32:22', NULL),
('56', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:34:06', NULL),
('57', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:34:16', NULL),
('58', 'lead', 'Novo Lead Recebido', 'Lead recebido de FRANCISCA BARBOSA DE OLIVEIRA (porpinomaiza+1@gmail.com) sobre o imóvel: Amplo e bem localizado', 'painel.php?tab=leads', '1', '2025-09-15 12:43:07', NULL),
('59', 'lead', 'Novo Lead Recebido', 'Lead recebido de julio ramos (porpinomaiza+1@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-15 12:51:28', NULL),
('60', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-16 13:50:07', NULL),
('61', 'lead', 'Novo Lead Recebido', 'Lead recebido de Ozonvita (ozonvitanatural@gmail.com)', 'painel.php?tab=leads', '1', '2025-09-16 17:57:56', NULL),
('62', 'property', 'Imóvel Alugado', 'O imóvel \"Apartamento\" foi marcado como Alugado', 'painel.php?tab=properties&property=65', '1', '2025-09-17 07:13:38', NULL),
('63', 'property', 'Imóvel Alugado', 'O imóvel \"SALA COMERCIAL\" foi marcado como Alugado', 'painel.php?tab=properties&property=66', '1', '2025-09-17 08:25:36', NULL),
('64', 'property', 'Imóvel Alugado', 'O imóvel \"SALA COMERCIAL\" foi marcado como Alugado', 'painel.php?tab=properties&property=66', '1', '2025-09-17 09:35:05', NULL),
('65', 'property', 'Imóvel Vendido', 'O imóvel \"Apartamento\" foi marcado como Vendido', 'painel.php?tab=properties&property=65', '1', '2025-09-17 10:04:22', NULL),
('66', 'property', 'Imóvel Alugado', 'O imóvel \"Apartamento\" foi marcado como Alugado', 'painel.php?tab=properties&property=65', '1', '2025-09-17 10:05:41', NULL),
('67', 'property', 'Imóvel Vendido', 'O imóvel \"Terreno Residencial\" foi marcado como Vendido', 'painel.php?tab=properties&property=72', '1', '2025-09-18 16:22:51', NULL),
('68', 'property', 'Imóvel Alugado', 'O imóvel \"Apartamento\" foi marcado como Alugado', 'painel.php?tab=properties&property=65', '1', '2025-09-18 16:27:03', NULL);

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
  `images` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_properties_status` (`status`),
  KEY `idx_properties_type` (`type`),
  KEY `idx_properties_transaction_type` (`transactionType`)
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `properties` (`id`, `title`, `type`, `transactionType`, `price`, `area`, `bedrooms`, `bathrooms`, `garage`, `description`, `location`, `neighborhood`, `city`, `state`, `features`, `status`, `created_at`, `updated_at`, `coordinates`, `video_url`, `featured`, `yearBuilt`, `zip`, `images`) VALUES
('65', 'Apartamento', 'apartment', 'aluguel', '1000000.00', '55.00', '1', '1', '1', 'lindo', 'Praça Sumaré N32 Potengi', 'Jardins', 'Natal', 'RN', '[\"Piscina\",\"Academia\"]', 'alugado', '2025-08-28 16:02:55', '2025-09-18 16:27:03', NULL, NULL, '0', '2010', '59124500', NULL),
('66', 'SALA COMERCIAL', 'commercial', 'aluguel', '234000.00', '456.00', '0', '1', '2', 'SHOW', 'Praça Sumaré', 'Potengi', 'Natal', 'RN', '[]', 'active', '2025-08-28 16:10:58', '2025-09-18 16:20:36', NULL, NULL, '0', '2019', '59124-500', NULL),
('70', 'Amplo e bem localizado', 'commercial', 'aluguel', '150000.00', '456.00', '0', '0', '0', 'Perto de tudo', 'Rua Maria Betânia de Vasconcelos, 108', '108', 'São Gonçalo do Amarante', 'RN', '[]', 'active', '2025-09-01 17:01:35', '2025-09-17 08:42:39', NULL, NULL, '0', '2019', '59.293-189', NULL),
('71', 'Terreno Central', 'commercial', 'venda', '120000.00', '500.00', '0', '0', '0', 'Terreno plano, ótima localização.', 'Rua das Flores', 'Centro', 'Cidade A', 'SP', '[]', 'active', '2025-09-02 11:53:02', '2025-09-18 16:19:16', NULL, NULL, '0', '2020', '12345-000', '[\"terreno1.jpg\"]'),
('72', 'Terreno Residencial', 'land', 'venda', '95000.00', '350.00', '0', '0', '0', 'Terreno residencial, pronto para construir.', 'Av. Brasil', 'Residencial', 'Cidade B', 'RJ', '[]', 'vendido', '2025-09-02 11:53:02', '2025-09-18 16:22:51', NULL, NULL, '0', '2021', '23456-000', '[\"terreno2.jpg\"]'),
('73', 'Terreno Comercial', 'land', 'venda', '250000.00', '800.00', NULL, NULL, NULL, 'Terreno comercial, esquina movimentada.', 'Rua do Comércio', 'Comercial', 'Cidade C', 'MG', '[\"Esquina\",\"Documentação OK\"]', 'active', '2025-09-02 11:53:02', '2025-09-02 11:53:02', NULL, NULL, '0', '2019', '34567-000', '[\"terreno3.jpg\"]'),
('74', 'Terreno Rural', 'land', 'venda', '180000.00', '2.00', '0', '0', '0', 'Terreno rural, ideal para sítio.', 'Estrada Rural', 'Zona Rural', 'Cidade D', 'RS', '[]', 'vendido', '2025-09-02 11:53:02', '2025-09-02 13:41:15', NULL, NULL, '0', '2018', '45678-000', '[\"terreno4.jpg\"]'),
('75', 'Terreno Industrial', 'land', 'venda', '400000.00', '1500.00', NULL, NULL, NULL, 'Terreno industrial, fácil acesso.', 'Rodovia 101', 'Industrial', 'Cidade E', 'SC', '[\"Fácil acesso\"]', 'active', '2025-09-02 11:53:02', '2025-09-02 11:53:02', NULL, NULL, '0', '2017', '56789-000', '[\"terreno5.jpg\"]'),
('76', 'Casa Moderna', 'house', 'venda', '350000.00', '180.00', '3', '2', '2', 'Casa moderna com piscina.', 'Rua Azul', 'Jardim', 'Cidade F', 'SP', '[\"Piscina\",\"Churrasqueira\"]', 'active', '2025-09-02 11:53:02', '2025-09-03 14:28:25', NULL, NULL, '0', '2022', '67890-000', '[\"casa1.jpg\"]'),
('77', 'Apartamento Luxo', 'apartment', 'venda', '500000.00', '120.00', '2', '2', '1', 'Apartamento de luxo, vista panorâmica.', 'Av. Luxo', 'Centro', 'Cidade G', 'RJ', '[\"Vista panorâmica\",\"Portaria 24h\"]', 'active', '2025-09-02 11:53:02', '2025-09-02 11:53:02', NULL, NULL, '0', '2023', '78901-000', '[\"apto1.jpg\"]'),
('78', 'Casa Simples', 'house', 'venda', '180000.00', '90.00', '2', '1', '1', 'Casa simples, ótima oportunidade.', 'Rua Simples', 'Bairro Novo', 'Cidade H', 'MG', '[\"Quintal\"]', 'active', '2025-09-02 11:53:02', '2025-09-02 11:53:02', NULL, NULL, '0', '2015', '89012-000', '[\"casa2.jpg\"]'),
('79', 'Apartamento Compacto', 'apartment', 'venda', '220000.00', '60.00', '1', '1', '1', 'Apartamento compacto, ideal para solteiro.', 'Rua Compacta', 'Centro', 'Cidade I', 'RS', '[\"Elevador\"]', 'inactive', '2025-09-02 11:53:02', '2025-09-02 13:12:40', NULL, NULL, '0', '2020', '90123-000', '[\"apto2.jpg\"]'),
('80', 'Casa de Praia', 'house', 'venda', '600000.00', '250.00', '4', '3', '2', 'Casa de praia, pé na areia.', 'Av. Beira Mar', 'Praia', 'Cidade J', 'SC', '[]', 'vendido', '2025-09-02 11:53:02', '2025-09-02 13:12:12', NULL, NULL, '0', '2021', '01234-000', '[\"casa3.jpg\"]');

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
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `property_images` (`id`, `property_id`, `image_url`, `is_featured`, `created_at`, `image_hash`) VALUES
('102', '65', '68b0a7df05eff.jpg', '0', '2025-08-28 16:02:55', NULL),
('103', '66', '68b0a9c28771d.jpg', '0', '2025-08-28 16:10:58', NULL),
('104', '70', '68b5fb9fe652b.jpeg', '0', '2025-09-01 17:01:35', NULL),
('107', '71', '68cab9fbd97f6.jpg', '0', '2025-09-17 10:39:07', NULL),
('108', '80', '68cdba9a42879.jpg', '0', '2025-09-19 17:18:34', NULL);

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
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
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`, `email`, `created_at`, `last_login`, `active`, `force_password_change`, `password_changed_at`, `last_login_attempt`, `login_attempts`) VALUES
('1', 'admin', '$2y$10$W.5aicAIoV2s/hnSNKf5QuExkprASLzRMyKnZ5Tg.EFCOiUWFBWdC', 'admin', 'Administrador', 'admin@rrimoveis.com', '2025-08-14 15:16:40', '2025-09-20 10:56:37', '1', '1', NULL, NULL, '0'),
('2', 'lidercarioca', '$2y$10$SmwkRDLjK2fRhwUQTooCu.qRXTM95/aNidA1aOwwh.xyIya7cT2Zi', 'user', 'daniel fernandes jardim', 'lidercarioca@gmail.com', '2025-08-25 11:15:55', '2025-09-02 13:18:31', '1', '1', NULL, NULL, '0');

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=1;
