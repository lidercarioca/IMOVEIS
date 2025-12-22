-- Tabela para rastreamento de visitas do site
CREATE TABLE IF NOT EXISTS `site_visits` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `visitor_ip` varchar(45) DEFAULT NULL,
  `visitor_ua` text DEFAULT NULL,
  `page_url` varchar(500) DEFAULT NULL,
  `referrer` varchar(500) DEFAULT NULL,
  `visited_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `session_id` varchar(100) DEFAULT NULL,
  INDEX `idx_visited_at` (`visited_at`),
  INDEX `idx_visitor_ip` (`visitor_ip`),
  INDEX `idx_session_id` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
