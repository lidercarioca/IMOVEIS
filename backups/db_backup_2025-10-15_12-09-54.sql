-- Backup do banco de dados rr_imoveis
-- Data: 2025-10-15 12:09:54
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
('1', 'DJ Imóveis', 'contato@rrimoveis.com.br', 'vendas@rrimoveis.com.br', '(11)3456-7890', '5521965025035', 'Av. Paulista, 1000 - Bela Vista, São Paulo - SP', '8h às 17h', '9 às 13h', 'Há mais de 15 anos no mercado imobiliário, a RR Imóveis se destaca pela excelência e compromisso com seus clientes. Especializada em imóveis de alto padrão e investimentos seguros.', 'https://facebook.com/rrimoveis', 'https://instagram.com/rrimoveis', 'https://linkedin.com/company/rrimoveis', 'https://youtube.com/rrimoveis', 'assets/imagens/logo/logo.png', '#6B4423', '#4B2E2B', 'Poppins', '2025-10-15 12:08:14', NULL, NULL, NULL, '#3C2F2F', 'ozonvitanatural@gmail.com', 'ozonvitanatural@gmail.com', '1', '1', '1', '1');

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
) ENGINE=InnoDB AUTO_INCREMENT=123 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `leads` (`id`, `name`, `email`, `phone`, `property_id`, `message`, `status`, `created_at`, `updated_at`, `source`, `notes`) VALUES
('121', 'Daniel Jardim', 'lidercarioca@gmail.com', '84996457182', NULL, 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', 'new', '2025-10-13 15:30:29', '2025-10-13 15:30:29', 'site', NULL),
('122', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', '84991752674', NULL, 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', 'new', '2025-10-13 16:53:46', '2025-10-13 16:53:46', 'site', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=104 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
('47', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] vamos comprar', '0', '2025-09-16 17:57:56', NULL, NULL),
('48', 'DANIEL F JARDIM', 'porpinomaiza+1@gmail.com', 'Novo contato do site', '[Compra de imóvel] QUERO ADQUIRIR O MEU IMOVEL NA PLANTA , VCS FAZERM ESSA NEGOCIAÇÃO?', '0', '2025-09-22 16:34:28', NULL, NULL),
('49', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\nQuartos: 0\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-22 16:35:51', NULL, NULL),
('50', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Avaliação de imóvel] vcs tambem fazerm avaliação do imovel?', '0', '2025-09-22 16:41:23', NULL, NULL),
('51', 'jose da silveira', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\nQuartos: 0\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-22 16:42:27', NULL, NULL),
('52', 'chico cezar', 'joaomacarrao@coisa.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\nQuartos: 0\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-22 16:43:02', NULL, NULL),
('53', 'MICHELL SANTANA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-22 16:46:22', NULL, NULL),
('54', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: CASA DE VILA\nTipo: Casa\nSituação: À Venda\nStatus: Ativo\nLocalização: R IBIA 517\nPreço: R$ 195.000,00\nQuartos: 2\nBanheiros: 1\nÁrea: 110.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-23 12:30:26', NULL, NULL),
('55', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Terreno Central\nTipo: Comercial\nSituação: À Venda\nStatus: Ativo\nLocalização: Rua das Flores\nPreço: R$ 120.000,00\n\n\nÁrea: 500.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-25 14:02:47', NULL, NULL),
('56', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 15:36:48', NULL, NULL),
('57', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', '[Compra de imóvel] vamos pra cima com tudo', '0', '2025-09-26 15:37:32', NULL, NULL),
('58', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:50:53', NULL, NULL),
('59', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Terreno Industrial\nTipo: Terreno\nSituação: À Venda\nStatus: Ativo\nLocalização: Rodovia 101\nPreço: R$ 400.000,00\n\n\nÁrea: 1500.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:51:18', NULL, NULL),
('60', 'DANIEL Lider', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:52:52', NULL, NULL),
('61', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:54:13', NULL, NULL),
('62', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Luxo\'s house\nTipo: Casa\nSituação: À Venda\nStatus: Ativo\nLocalização: Rua praia linda\nPreço: R$ 18.000.000,00\nQuartos: 5\nBanheiros: 3\nÁrea: 350.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:55:41', NULL, NULL),
('63', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:57:08', NULL, NULL),
('64', 'Daniel Fernandes Jardim / OZONTECK NATAL', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:57:35', NULL, NULL),
('65', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:59:14', NULL, NULL),
('66', 'DANIEL Lider', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Luxo\'s house\nTipo: Casa\nSituação: À Venda\nStatus: Ativo\nLocalização: Rua praia linda\nPreço: R$ 18.000.000,00\nQuartos: 5\nBanheiros: 3\nÁrea: 350.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 16:59:35', NULL, NULL),
('67', 'DANIEL Lider', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:01:39', NULL, NULL),
('68', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:01:59', NULL, NULL),
('69', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:03:51', NULL, NULL),
('70', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:04:14', NULL, NULL),
('71', 'DANIEL FERNANDES JARDIM', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:04:40', NULL, NULL),
('72', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Terreno Industrial\nTipo: Terreno\nSituação: À Venda\nStatus: Ativo\nLocalização: Rodovia 101\nPreço: R$ 400.000,00\n\n\nÁrea: 1500.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:07:03', NULL, NULL),
('73', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Luxo\'s house\nTipo: Casa\nSituação: À Venda\nStatus: Ativo\nLocalização: Rua praia linda\nPreço: R$ 18.000.000,00\nQuartos: 5\nBanheiros: 3\nÁrea: 350.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:07:38', NULL, NULL),
('74', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:09:41', NULL, NULL),
('75', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:09:54', NULL, NULL),
('76', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:13:08', NULL, NULL),
('77', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Amplo e bem localizado\nTipo: Comercial\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Rua Maria Betânia de Vasconcelos, 108\nPreço: R$ 150.000,00\n\n\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:14:17', NULL, NULL),
('78', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:14:45', NULL, NULL),
('79', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:15:36', NULL, NULL),
('80', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:19:48', NULL, NULL),
('81', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:30:39', NULL, NULL),
('82', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-26 17:32:04', NULL, NULL),
('83', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-27 09:09:51', NULL, NULL),
('84', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-27 09:20:12', NULL, NULL),
('85', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Terreno Central\nTipo: Comercial\nSituação: À Venda\nStatus: Ativo\nLocalização: Rua das Flores\nPreço: R$ 120.000,00\n\n\nÁrea: 500.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-27 09:22:40', NULL, NULL),
('86', 'silvio braga', 'radicaischics@outlook.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: SALA COMERCIAL\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-27 09:54:46', NULL, NULL),
('87', 'silvio braga', 'radicaischics@outlook.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-27 11:18:29', NULL, NULL),
('88', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-27 11:37:53', NULL, NULL),
('89', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: À Venda\nStatus: Ativo\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-29 12:45:04', NULL, NULL),
('90', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-29 12:45:31', NULL, NULL),
('91', 'JOAO MENDES', 'djardim322@gmail.com', 'Novo contato do site', '[Aluguel] QUERO ALUGAR AGORA', '0', '2025-09-29 12:46:17', NULL, NULL),
('92', 'DAVID DE LIMA JARDIM', 'davidserverrpg@hotmail.com', 'Novo contato do site', '[Compra de imóvel] DESEJO COMPRAR UMA MANSAO COM VCS', '0', '2025-09-30 17:47:10', NULL, NULL),
('93', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-09-30 17:48:30', NULL, NULL),
('94', 'jose mapfra', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-01 09:20:25', NULL, NULL),
('95', 'vitoria', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-01 09:31:05', NULL, NULL),
('96', 'jose mapfra', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: Para Alugar\nStatus: Alugado\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-01 09:36:27', NULL, NULL),
('97', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Amplo e Bem Localizado\nTipo: Apartamento\nSituação: Para Alugar\nStatus: Ativo\nLocalização: Rua Maria Betânia de Vasconcelos, 108\nPreço: R$ 150.000,00\n\n\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-01 09:37:14', NULL, NULL),
('98', 'JOAO MENDES', 'djardim322@gmail.com', 'Novo contato do site', '[Venda de imóvel] quero que funcione', '0', '2025-10-01 09:37:39', NULL, NULL),
('99', 'Ozonvita', 'ozonvitanatural@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: À Venda\nStatus: Ativo\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-01 11:17:16', NULL, NULL),
('100', 'daniel', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-04 11:30:20', NULL, NULL),
('101', 'jose mapfra', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-04 11:31:37', NULL, NULL),
('102', 'Daniel Jardim', 'lidercarioca@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Sala Comercial\nTipo: Comercial\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré\nPreço: R$ 234.000,00\n\nBanheiros: 1\nÁrea: 456.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-13 15:30:29', NULL, NULL),
('103', 'FRANCISCA BARBOSA DE OLIVEIRA', 'porpinomaiza+1@gmail.com', 'Novo contato do site', 'Olá, tenho interesse no imóvel:\n\nTítulo: Apartamento\nTipo: Apartamento\nSituação: À Venda\nStatus: Vendido\nLocalização: Praça Sumaré N32 Potengi\nPreço: R$ 1.000.000,00\nQuartos: 1\nBanheiros: 1\nÁrea: 55.00m²\n\nPor favor, gostaria de mais informações sobre este imóvel.', '0', '2025-10-13 16:53:46', NULL, NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=134 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `properties` (`id`, `title`, `type`, `transactionType`, `price`, `area`, `bedrooms`, `bathrooms`, `garage`, `description`, `location`, `neighborhood`, `city`, `state`, `features`, `status`, `created_at`, `updated_at`, `coordinates`, `video_url`, `featured`, `yearBuilt`, `zip`, `images`) VALUES
('65', 'Apartamento', 'apartment', 'venda', '1000000.00', '55.00', '1', '1', '1', 'Cobertura Duplex de Alto Padrão — Luxo, Conforto e Privacidade\r\nApresentamos uma cobertura duplex de 420 m², projetada por escritório de arquitetura renomado e concluída com materiais premium. O living envidraçado conecta-se a uma ampla varanda gourmet com churrasqueira e piscina de borda infinita, oferecendo vista panorâmica da cidade e do mar. São 4 suítes plenas com closet e acabamentos em marcenaria personalizada; suíte master com banheiro Sr. e Sra., banheira de imersão e varanda privativa.\r\n\r\nCozinha gourmet com ilha central, despensa técnica e área de serviço completa. Espaços de convivência com pé-direito duplo, lareira e home cinema integrado a um sistema de som embutido. Projeto de iluminação DALI, ar-condicionado VRF e automação que controla iluminação, cortinas, áudio e segurança via app. 4 vagas na garagem com depósito privativo.\r\n\r\nLocalização privilegiada, ao lado de serviços exclusivos, restaurantes e acesso rápido às principais vias. Ideal para quem busca um estilo de vida sofisticado sem abrir mão de conforto e privacidade.', 'Praça Sumaré N32', 'Jardins', 'Natal', 'RN', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Elevador\",\"Churrasqueira\",\"Aceita Pet\"]', 'vendido', '2025-08-28 16:02:55', '2025-10-13 17:10:22', NULL, NULL, '0', '2010', '59124500', NULL),
('66', 'Sala Comercial', 'commercial', 'venda', '234000.00', '456.00', '0', '1', '2', 'SHOW', 'Praça Sumaré', 'Potengi', 'Natal', 'RN', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Elevador\",\"Churrasqueira\"]', 'vendido', '2025-08-28 16:10:58', '2025-10-04 10:15:31', NULL, NULL, '0', '2019', '59124-500', NULL),
('70', 'Amplo e Bem Localizado', 'land', 'venda', '150000.00', '456.00', '0', '0', '0', 'Perto de tudo', 'Rua Maria Betânia de Vasconcelos, 108', 'Jardins', 'São Gonçalo do Amarante', 'RN', '[\"Piscina\",\"Academia\",\"Elevador\",\"Aceita Pet\"]', 'active', '2025-09-01 17:01:35', '2025-10-13 17:10:41', NULL, NULL, '0', '2019', '59.293-189', NULL),
('71', 'Terreno Central', 'commercial', 'venda', '120000.00', '500.00', '0', '0', '0', 'Terreno plano, ótima localização.', 'Rua das Flores', 'Centro', 'Cidade A', 'SP', '[]', 'active', '2025-09-02 11:53:02', '2025-10-02 17:36:51', NULL, NULL, '0', '2020', '12345-000', '[\"terreno1.jpg\"]'),
('72', 'Luxo\'s house', 'house', 'venda', '18000000.00', '350.00', '5', '3', '4', 'Residencia de luxo para ricos morarem', 'Rua praia linda', 'IPANEMA', 'Rio de Janeiro', 'RJ', '[\"Piscina\",\"Jardim\",\"Academia\",\"Elevador\",\"Churrasqueira\",\"Mobiliado\",\"Aceita Pet\"]', 'active', '2025-09-02 11:53:02', '2025-10-01 15:37:20', NULL, NULL, '0', '2021', '23456-000', '[\"terreno2.jpg\"]'),
('73', 'Terreno Comercial', 'land', 'venda', '250000.00', '800.00', '0', '0', '0', 'Terreno comercial, esquina movimentada.', 'Rua do Comércio', 'Comercial', 'Cidade C', 'MG', '[]', 'active', '2025-09-02 11:53:02', '2025-10-02 17:37:02', NULL, NULL, '0', '2019', '34567-000', '[\"terreno3.jpg\"]'),
('74', 'Terreno Rural', 'land', 'venda', '180000.00', '2.00', '0', '0', '0', 'Terreno rural, ideal para sítio.', 'Estrada Rural', 'Zona Rural', 'Cidade D', 'RS', '[]', 'active', '2025-09-02 11:53:02', '2025-10-14 10:00:01', NULL, NULL, '0', '2018', '45678-000', '[\"terreno4.jpg\"]'),
('75', 'Terreno Industrial', 'land', 'venda', '400000.00', '1.50', '0', '0', '0', 'Terreno industrial, fácil acesso.', 'Rodovia 101', 'Industrial', 'Cidade E', 'SC', '[]', 'active', '2025-09-02 11:53:02', '2025-10-14 10:00:11', NULL, NULL, '0', '2017', '56789-000', '[\"terreno5.jpg\"]'),
('76', 'Casa Moderna', 'house', 'venda', '350000.00', '180.00', '3', '2', '2', 'Casa moderna com piscina.', 'Rua Azul', 'Jardim Sulacap', 'Cidade F', 'SP', '[\"Piscina\",\"Churrasqueira\"]', 'active', '2025-09-02 11:53:02', '2025-10-14 09:59:42', NULL, NULL, '0', '2022', '67890-000', '[\"casa1.jpg\"]'),
('77', 'Apartamento Luxo', 'apartment', 'venda', '500000.00', '120.00', '2', '2', '1', 'Apartamento de luxo, vista panorâmica.', 'Av. Luxo', 'Centro', 'Cidade G', 'RJ', '[]', 'active', '2025-09-02 11:53:02', '2025-10-14 09:59:33', NULL, NULL, '0', '2023', '78901-000', '[\"apto1.jpg\"]'),
('78', 'Casa Simples', 'house', 'venda', '180000.00', '90.00', '2', '1', '1', 'Casa simples, ótima oportunidade.', 'Rua Simples', 'Bairro Novo', 'Cidade H', 'MG', '[]', 'active', '2025-09-02 11:53:02', '2025-10-13 17:11:55', NULL, NULL, '0', '2015', '89012-000', '[\"casa2.jpg\"]'),
('79', 'Apartamento Compacto', 'apartment', 'venda', '220000.00', '60.00', '1', '1', '1', 'Apartamento compacto, ideal para solteiro.', 'Rua Compacta', 'Centro', 'Cidade I', 'RS', '[\"Elevador\"]', 'active', '2025-09-02 11:53:02', '2025-10-14 09:59:52', NULL, NULL, '0', '2020', '90123-000', '[\"apto2.jpg\"]'),
('80', 'Casa de Praia', 'house', 'venda', '600000.00', '250.00', '4', '3', '2', 'Casa de praia, pé na areia.', 'Av. Beira Mar', 'Praia', 'Cidade J', 'SC', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Elevador\",\"Churrasqueira\"]', 'active', '2025-09-02 11:53:02', '2025-09-26 15:31:01', NULL, NULL, '0', '2021', '01234-000', '[\"casa3.jpg\"]'),
('81', 'CASA DE CONJUNTO', 'house', 'venda', '195000.00', '110.00', '2', '1', '2', 'EXCELENTE LOCALIZAÇÃO', 'R IBIA 517', 'TURIAÇU', 'Rio de Janeiro', 'RJ', '[\"Piscina\",\"Jardim\",\"Academia\",\"Segurança\",\"Churrasqueira\",\"Mobiliado\",\"Aceita Pet\"]', 'vendido', '2025-09-23 12:29:08', '2025-10-04 10:15:49', NULL, NULL, '0', '2024', '21540070', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=122 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `property_images` (`id`, `property_id`, `image_url`, `is_featured`, `created_at`, `image_hash`) VALUES
('103', '66', '68b0a9c28771d.jpg', '0', '2025-08-28 16:10:58', NULL),
('107', '71', '68cab9fbd97f6.jpg', '0', '2025-09-17 10:39:07', NULL),
('108', '80', '68cdba9a42879.jpg', '0', '2025-09-19 17:18:34', NULL),
('111', '65', '68d182ffa1b2e.jpg', '0', '2025-09-22 14:10:23', NULL),
('113', '81', '68d2bcc4ca47d.jpg', '0', '2025-09-23 12:29:08', NULL),
('114', '81', '68d2bce664a07.jpg', '0', '2025-09-23 12:29:42', NULL),
('115', '81', '68d2bce6659bc.jpg', '0', '2025-09-23 12:29:42', NULL),
('116', '81', '68d2bce666b39.jpg', '0', '2025-09-23 12:29:42', NULL),
('117', '81', '68d2bce6681e9.png', '0', '2025-09-23 12:29:42', NULL),
('118', '72', '68d68ceb9fcb7.jpg', '0', '2025-09-26 09:54:03', NULL),
('120', '70', '68dd8581bbf10.jpg', '0', '2025-10-01 16:48:17', NULL),
('121', '73', '68e11b74af09a.jpg', '0', '2025-10-04 10:04:52', NULL);

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
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `users` (`id`, `username`, `password`, `role`, `name`, `email`, `created_at`, `last_login`, `active`, `force_password_change`, `password_changed_at`, `last_login_attempt`, `login_attempts`) VALUES
('1', 'admin', '$2y$10$WUvFdsfWPLS0AAU0YN/bR.jHiDyQqCcK4ZA5cXbV5p8H9XqP30VFG', 'admin', 'DJ', 'lidercarioca@gmail.com', '2025-08-14 15:16:40', '2025-10-15 10:42:28', '1', '1', NULL, NULL, '0'),
('9', 'demo', '$2y$10$CgFOxetvgJu86oLELaOv/OFKWBd9jFME4E1NUPYYHFnMjEe60jf8m', 'admin', 'demo', 'demo@demo.com', '2025-10-06 10:32:54', '2025-10-06 11:25:39', '1', '1', NULL, NULL, '0');

SET FOREIGN_KEY_CHECKS=1;

SET FOREIGN_KEY_CHECKS=1;
