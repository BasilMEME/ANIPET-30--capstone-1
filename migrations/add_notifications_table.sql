-- Add notifications table to existing AniPet database
-- This migration adds support for admin notifications management

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `recipient_group` VARCHAR(50) NOT NULL,
  `notification_type` VARCHAR(50) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_recipient_group` (`recipient_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
