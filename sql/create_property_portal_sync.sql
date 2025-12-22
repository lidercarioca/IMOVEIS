-- Migration: create table to track property export syncs to portals
-- Execute this SQL in your database to create the table used for incremental syncs.

CREATE TABLE IF NOT EXISTS `property_portal_sync` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `property_id` INT NOT NULL,
  `portal` VARCHAR(100) NOT NULL,
  `last_exported_at` DATETIME NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `response` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  INDEX (`property_id`),
  INDEX (`portal`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
