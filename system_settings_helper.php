<?php

function ensureSystemSettingsTable(mysqli $conn): void
{
    $conn->query("
        CREATE TABLE IF NOT EXISTS system_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT DEFAULT NULL,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
                ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function get_system_setting(
    mysqli $conn,
    string $key,
    string $default = ''
): string {
    ensureSystemSettingsTable($conn);

    $stmt = $conn->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    $stmt->bind_param('s', $key);
    $stmt->execute();

    $row = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    return $row
        ? (string)$row['setting_value']
        : $default;
}

function save_system_setting(
    mysqli $conn,
    string $key,
    string $value
): bool {
    ensureSystemSettingsTable($conn);

    $stmt = $conn->prepare("
        INSERT INTO system_settings
        (
            setting_key,
            setting_value,
            updated_at
        )
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ");

    $stmt->bind_param(
        'ss',
        $key,
        $value
    );

    $success = $stmt->execute();

    $stmt->close();

    return $success;
}