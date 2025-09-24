-- Adiciona a coluna notes à tabela leads
ALTER TABLE `leads` 
ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `source`;
