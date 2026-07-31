<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = getenv('MYSQLHOST');
$port = (int) getenv('MYSQLPORT');
$username = getenv('MYSQLUSER');
$password = getenv('MYSQLPASSWORD');
$database = getenv('MYSQLDATABASE');

if (!$host || !$port || !$username || !$password || !$database) {
    throw new RuntimeException(
        'Missing Railway MySQL environment variables'
    );
}

$conn = new mysqli(
    $host,
    $username,
    $password,
    $database,
    $port
);

$conn->set_charset('utf8mb4');

function columnExists(mysqli $conn, string $table, string $column): bool
{
    $sql = "
        SELECT COUNT(*) AS total
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();

    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return (int)$row["total"] > 0;
}

if (!columnExists($conn, "audit_logs", "before_data")) {
    $conn->query("
        ALTER TABLE `audit_logs`
        ADD COLUMN `before_data` JSON DEFAULT NULL AFTER `details`
    ");
}

if (!columnExists($conn, "audit_logs", "after_data")) {
    $conn->query("
        ALTER TABLE `audit_logs`
        ADD COLUMN `after_data` JSON DEFAULT NULL AFTER `before_data`
    ");
}

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
if (!columnExists($conn, "pets", "species")) {
    $conn->query("
        ALTER TABLE `pets`
        ADD COLUMN `species` VARCHAR(50) DEFAULT NULL AFTER `name`
    ");
}

if (!columnExists($conn, "pets", "shelter_id")) {
    $conn->query("
        ALTER TABLE `pets`
        ADD COLUMN `shelter_id` INT DEFAULT NULL
    ");
}

if (!columnExists($conn, "pets", "is_archived")) {
    $conn->query("
        ALTER TABLE `pets`
        ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0
    ");
}

if (!columnExists($conn, "pets", "vaccination_records")) {
    $conn->query("
        ALTER TABLE `pets`
        ADD COLUMN `vaccination_records` TEXT DEFAULT NULL AFTER `health_status`
    ");
}

if (!columnExists($conn, "pets", "medical_records")) {
    $conn->query("
        ALTER TABLE `pets`
        ADD COLUMN `medical_records` TEXT DEFAULT NULL AFTER `vaccination_records`
    ");
}

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
if (!columnExists($conn, "appointments", "application_id")) {
    $conn->query("
        ALTER TABLE `appointments`
        ADD COLUMN `application_id` INT DEFAULT NULL
    ");
}

if (!columnExists($conn, "appointments", "appointment_type")) {
    $conn->query("
        ALTER TABLE `appointments`
        ADD COLUMN `appointment_type` VARCHAR(20) NOT NULL DEFAULT 'general'
    ");
}

require_once __DIR__ . '/role_permissions_helper.php';

try {

    ensureRolePermissionsTable($conn);

    $conn->query("
        INSERT IGNORE INTO role_permissions
        (role, permission_key, is_allowed)
        VALUES
        ('super_admin','manage_pet_pound',1),
        ('super_admin','manage_settings',1),

        ('admin','manage_pet_pound',1),
        ('admin','manage_settings',1),

        ('user','manage_pet_pound',0),
        ('user','manage_settings',0)
    ");

} catch (mysqli_sql_exception $e) {

    if ($e->getCode() != 1213) {
        throw $e;
    }

    error_log(
        'Role permission migration skipped: ' .
        $e->getMessage()
    );
}

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
    INDEX `idx_donation_date` (`donation_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-migrate: Pet Pound feature (admin_pages/pet_pound.php and friends).
// A pet taken due to a penalty is impounded here with a 14-day claim grace period.
// impound_date and claim_deadline are DATETIME so the 14-day window can be checked
// accurately, including the exact time of day.
//
// Status values:
// Pending / Claimed / Paid / Expired / Posted = active pound records
// Deceased = deceased pet records
//
// posted_for_adoption and adoption_pet_id track the move into the public pets table.
$conn->query("CREATE TABLE IF NOT EXISTS `pet_pound` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `pet_name` VARCHAR(150) NOT NULL,
    `pet_photo` VARCHAR(255) DEFAULT NULL,
    `owner_name` VARCHAR(150) NOT NULL,
    `owner_id` INT DEFAULT NULL,
    `reason` TEXT NOT NULL,
    `penalty_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `impound_date` DATETIME NOT NULL,
    `claim_deadline` DATETIME NOT NULL,
    `species` VARCHAR(50) NOT NULL DEFAULT 'Unknown',
    `breed` VARCHAR(100) NOT NULL DEFAULT 'Unknown',
    `age` VARCHAR(30) NOT NULL DEFAULT 'Unknown',
    `gender` VARCHAR(20) NOT NULL DEFAULT 'Unknown',
    `health_status` VARCHAR(100) NOT NULL DEFAULT 'Unknown',
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

// Upgrade older pet_pound tables that already existed before the
// cause_of_death, death_remarks, and death_date columns were added.
if (!columnExists($conn, "pet_pound", "cause_of_death")) {
    $conn->query("
        ALTER TABLE `pet_pound`
        ADD COLUMN `cause_of_death` VARCHAR(50) DEFAULT NULL
    ");
}

if (!columnExists($conn, "pet_pound", "death_remarks")) {
    $conn->query("
        ALTER TABLE `pet_pound`
        ADD COLUMN `death_remarks` TEXT DEFAULT NULL
    ");
}

if (!columnExists($conn, "pet_pound", "death_date")) {
    $conn->query("
        ALTER TABLE `pet_pound`
        ADD COLUMN `death_date` DATETIME DEFAULT NULL
    ");
}

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

// Auto-migrate: Pet Pound and Pet Penalty Payments use the
// manage_pet_pound permission.