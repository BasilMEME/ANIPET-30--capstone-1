-- anipet_db.sql
-- Create database and tables for Anipet app with sample data

CREATE DATABASE IF NOT EXISTS `anipet_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `anipet_db`;

-- users
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) DEFAULT NULL UNIQUE,
  `full_name` VARCHAR(150) NOT NULL,
  `first_name` VARCHAR(60) NOT NULL,
  `middle_name` VARCHAR(60) DEFAULT NULL,
  `last_name` VARCHAR(60) NOT NULL,
  `suffix` VARCHAR(60) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `contact_preference` VARCHAR(20) DEFAULT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `is_suspended` TINYINT(1) NOT NULL DEFAULT 0,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- pets
CREATE TABLE IF NOT EXISTS `pets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(120) NOT NULL,
  `breed` VARCHAR(120) DEFAULT NULL,
  `age` VARCHAR(50) DEFAULT NULL,
  `gender` VARCHAR(20) DEFAULT NULL,
  `description` TEXT,
  `health_status` VARCHAR(120) DEFAULT NULL,
  `image` VARCHAR(255) DEFAULT NULL,
  `shelter_id` INT DEFAULT NULL,
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `status` VARCHAR(50) DEFAULT 'available',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- shelters
CREATE TABLE IF NOT EXISTS `shelters` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) DEFAULT NULL,
  `phone` VARCHAR(80) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- system settings
CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(120) NOT NULL UNIQUE,
  `setting_value` TEXT DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- audit logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT DEFAULT NULL,
  `action_type` VARCHAR(120) NOT NULL,
  `target_type` VARCHAR(120) DEFAULT NULL,
  `target_id` VARCHAR(80) DEFAULT NULL,
  `details` TEXT DEFAULT NULL,
  `before_data` JSON DEFAULT NULL,
  `after_data` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SSO one-time login tokens (super_admin_api.php create_sso_token / sso_login.php)
CREATE TABLE IF NOT EXISTS `sso_tokens` (
  `id` VARCHAR(64) PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token_hash` VARCHAR(64) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_token_hash` (`token_hash`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-role permission overrides (super_admin_api.php / super_admin_security.php)
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` VARCHAR(20) NOT NULL,
  `permission_key` VARCHAR(120) NOT NULL,
  `is_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY `role_perm_unique` (`role`, `permission_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Scheduled report definitions (super_admin_api.php save_report_schedule)
CREATE TABLE IF NOT EXISTS `report_schedules` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `report_type` VARCHAR(100) NOT NULL,
  `frequency` VARCHAR(20) NOT NULL DEFAULT 'daily',
  `schedule_hour` TINYINT(2) NOT NULL DEFAULT 8,
  `recipient_email` VARCHAR(150) DEFAULT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `last_run_at` DATETIME DEFAULT NULL,
  `next_run_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- session tracking for Super Admin session management
CREATE TABLE IF NOT EXISTS `user_sessions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `session_id` VARCHAR(128) NOT NULL UNIQUE,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` TEXT DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_active_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- otps
CREATE TABLE IF NOT EXISTS `otps` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `email` VARCHAR(150) NOT NULL,
  `otp` VARCHAR(32) NOT NULL,
  `expires_at` DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- adoption applications
CREATE TABLE IF NOT EXISTS `adoption_applications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pet_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `applicant_name` VARCHAR(150) NOT NULL,
  `message` TEXT,
  `qr_code` VARCHAR(255) DEFAULT NULL,
  `qr_data` VARCHAR(255) DEFAULT NULL,
  `id_documents` TEXT DEFAULT NULL,
  `house_photos` TEXT DEFAULT NULL,
  `form_data` TEXT DEFAULT NULL,
  `terms_accepted` TINYINT(1) NOT NULL DEFAULT 0,
  `privacy_consent` TEXT DEFAULT NULL,
  `interview_datetime` DATETIME DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `screened_by` INT DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'pending',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- adoption records
CREATE TABLE IF NOT EXISTS `adoption_records` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `pet_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `adoption_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `qr_code_path` VARCHAR(255) DEFAULT NULL,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- appointments
CREATE TABLE IF NOT EXISTS `appointments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `pet_id` INT DEFAULT NULL,
  `title` VARCHAR(150) NOT NULL,
  `details` TEXT DEFAULT NULL,
  `scheduled_at` DATETIME DEFAULT NULL,
  `status` VARCHAR(50) NOT NULL DEFAULT 'pending',
  `application_id` INT DEFAULT NULL,
  `appointment_type` VARCHAR(20) NOT NULL DEFAULT 'general',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`pet_id`) REFERENCES `pets`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sample Super Admin user
INSERT INTO `users` (`username`, `full_name`, `first_name`, `middle_name`, `last_name`, `suffix`, `email`, `password`, `role`, `is_verified`) VALUES
('super_admin123', 'SuperAdmin', 'SuperAdmin', NULL, '', NULL, 'demo@local', '$2y$10$abcdefghijklmnopqrstuv', 'super_admin', 1);

INSERT INTO `shelters` (`name`, `address`, `phone`, `email`, `status`) VALUES
('AniPet Main Shelter', '123 Pet Care Blvd, Manila', '+63 912 345 6789', 'shelter@anipet.com', 'active');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `description`) VALUES
('site_title', 'AniPet Adoption System', 'Primary system title'),
('contact_email', 'support@anipet.com', 'Main support email address'),
('notification_enabled', '1', 'Master notification switch');

COMMIT;
