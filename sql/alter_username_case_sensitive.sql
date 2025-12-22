-- Alter username column to be case-sensitive
-- This ensures that login credentials are verified exactly as they were entered

ALTER TABLE `users` 
MODIFY COLUMN `username` VARCHAR(50) COLLATE utf8mb4_bin NOT NULL;

-- Add index on username for performance
ALTER TABLE `users` 
ADD UNIQUE KEY `username_unique` (`username`);
