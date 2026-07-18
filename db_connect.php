<?php
// Disable display errors to avoid corrupting JSON responses in API output
// Keep error reporting enabled for logs but don't echo to clients
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$username = "root";
$password = "";
$database = "anipet_db";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    // Use JSON-friendly error output for Android clients during debugging
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'DB Error: ' . $conn->connect_error]);
    exit;
}

$conn->set_charset("utf8");

// Auto-migrate: ensure audit_logs has columns referenced by admin/super admin panels.
// (Base schema file was updated after this DB was created, so the live table can lag behind.)
$conn->query("ALTER TABLE `audit_logs`
    ADD COLUMN IF NOT EXISTS `before_data` JSON DEFAULT NULL AFTER `details`,
    ADD COLUMN IF NOT EXISTS `after_data` JSON DEFAULT NULL AFTER `before_data`");

// Auto-migrate: sso_tokens isn't in the base schema dump but is used by sso_login.php,
// cleanup_sso_tokens.php, and super_admin_api.php's create_sso_token/cleanup_sso_tokens actions.
$conn->query("CREATE TABLE IF NOT EXISTS `sso_tokens` (
    `id` VARCHAR(64) PRIMARY KEY,
    `user_id` INT NOT NULL,
    `token_hash` VARCHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_token_hash` (`token_hash`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate: these used to run only inside admin_api.php, so admin_pages/pets.php
// and admin_pages/notifications.php could fatal on a fresh DB reached via
// admin_workspace.php before admin_api.php ever ran once. Moved here so every
// entry point self-heals consistently.
$conn->query("ALTER TABLE `pets`
    ADD COLUMN IF NOT EXISTS `species` VARCHAR(50) DEFAULT NULL AFTER `name`,
    ADD COLUMN IF NOT EXISTS `shelter_id` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS `vaccination_records` TEXT DEFAULT NULL AFTER `health_status`,
    ADD COLUMN IF NOT EXISTS `medical_records` TEXT DEFAULT NULL AFTER `vaccination_records`");

$conn->query("CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `recipient_group` VARCHAR(50) NOT NULL,
    `notification_type` VARCHAR(50) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_recipient_group` (`recipient_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate: links an appointment to the adoption application it's an interview for
// (application_status_helper.php auto-creates/updates one when an application enters
// 'screening'), so the existing Appointments booking/management UI doubles as the
// interview-scheduling surface instead of a separate feature.
$conn->query("ALTER TABLE `appointments`
    ADD COLUMN IF NOT EXISTS `application_id` INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `appointment_type` VARCHAR(20) NOT NULL DEFAULT 'general'");

// Auto-migrate: manage_returns / manage_settings are new permission keys added for the
// return & penalty management feature (admin_pages/returns.php, admin_pages/settings.php).
// ensureRolePermissionsTable() only seeds its whole default list on a completely empty
// table, so on an install that was already seeded before these keys existed they'd never
// appear — INSERT IGNORE backfills just the missing rows without touching any permission
// a super admin has since customized.
require_once __DIR__ . '/role_permissions_helper.php';
ensureRolePermissionsTable($conn);
$conn->query("INSERT IGNORE INTO role_permissions (role, permission_key, is_allowed) VALUES
    ('super_admin','manage_returns',1),
    ('super_admin','manage_settings',1),
    ('admin','manage_returns',1),
    ('admin','manage_settings',1),
    ('user','manage_returns',0),
    ('user','manage_settings',0)");

// Auto-migrate: admin_notes for the admin returns/penalties view (admin_pages/returns.php),
// not present in the base return_requests table.
$conn->query("ALTER TABLE `return_requests`
    ADD COLUMN IF NOT EXISTS `admin_notes` TEXT DEFAULT NULL");

// Auto-migrate: donations table used by submit_donation.php / super_admin_donations.php /
// super_admin_donation_detail.php / super_admin_refund_donation.php. Like pet_pound, this
// table was referenced everywhere but never actually created, so every donation page
// fataled (mysqli throws via MYSQLI_REPORT_STRICT) the moment it ran a query.
$conn->query("CREATE TABLE IF NOT EXISTS `donations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT DEFAULT NULL,
    `donor_name` VARCHAR(150) NOT NULL,
    `pet_name` VARCHAR(150) DEFAULT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `reference_number` VARCHAR(100) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL DEFAULT 'GCash',
    `receipt_image` VARCHAR(255) DEFAULT NULL,
    `donation_date` DATETIME NOT NULL,
    `refund_deadline` DATETIME DEFAULT NULL,
    `refund_status` VARCHAR(20) NOT NULL DEFAULT 'Not Refunded',
    `refunded_at` DATETIME DEFAULT NULL,
    INDEX `idx_donation_date` (`donation_date`),
    INDEX `idx_refund_status` (`refund_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate: Pet Pound feature (admin_pages/pet_pound.php and friends). A pet taken due
// to a penalty is impounded here with a 48-hour claim grace period (impound_date/claim_deadline
// are DATETIME so the 48h window can be checked to the hour, not just the day). status drives
// both listings: Pending/Claimed/Paid/Expired/Posted = active pound, Deceased = the separate
// deceased-records listing. posted_for_adoption/adoption_pet_id track the one-way move into the
// public `pets` table once the grace period lapses unclaimed.
$conn->query("CREATE TABLE IF NOT EXISTS `pet_pound` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pet_name` VARCHAR(150) NOT NULL,
    `pet_photo` VARCHAR(255) DEFAULT NULL,
    `owner_name` VARCHAR(150) NOT NULL,
    `owner_id` INT DEFAULT NULL,
    `reason` TEXT NOT NULL,
    `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0,
    `impound_date` DATETIME NOT NULL,
    `claim_deadline` DATETIME NOT NULL,
    `species` VARCHAR(50) NOT NULL,
    `breed` VARCHAR(100) NOT NULL,
    `age` VARCHAR(30) NOT NULL,
    `gender` VARCHAR(20) NOT NULL,
    `health_status` VARCHAR(100) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Pending',
    `posted_for_adoption` TINYINT(1) NOT NULL DEFAULT 0,
    `adoption_pet_id` INT DEFAULT NULL,
    `payment_status` VARCHAR(20) NOT NULL DEFAULT 'Unpaid',
    `payment_reference` VARCHAR(100) DEFAULT NULL,
    `payment_date` DATETIME DEFAULT NULL,
    `cause_of_death` VARCHAR(50) DEFAULT NULL,
    `death_remarks` TEXT DEFAULT NULL,
    `death_date` DATETIME DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_claim_deadline` (`claim_deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate: penalty payment records tied to a pet_pound row. payment_date defaults to
// CURRENT_TIMESTAMP so the "date" is always the moment the payment was recorded as complete,
// never a value the admin/owner types in.
$conn->query("CREATE TABLE IF NOT EXISTS `pet_penalty_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pet_pound_id` INT NOT NULL,
    `owner_id` INT DEFAULT NULL,
    `payer_name` VARCHAR(150) NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `reference_number` VARCHAR(100) DEFAULT NULL,
    `receipt_photo` VARCHAR(255) DEFAULT NULL,
    `payment_date` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_pet_pound_id` (`pet_pound_id`),
    FOREIGN KEY (`pet_pound_id`) REFERENCES `pet_pound`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate: pet_pound/pet_penalty_payments management shares the existing manage_returns
// permission (already granted to admin + super_admin above) rather than introducing a new key.