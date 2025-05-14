-- Add location columns to users table
ALTER TABLE `users` 
ADD COLUMN `latitude` DECIMAL(10,6) NULL DEFAULT NULL AFTER `phone_number`,
ADD COLUMN `longitude` DECIMAL(10,6) NULL DEFAULT NULL AFTER `latitude`,
ADD COLUMN `last_location` VARCHAR(255) NULL DEFAULT NULL AFTER `longitude`;

-- Add index for faster location queries
ALTER TABLE `users` 
ADD INDEX `idx_user_location` (`latitude`, `longitude`);
