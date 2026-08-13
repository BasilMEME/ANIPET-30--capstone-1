<?php

declare(strict_types=1);

function getNotificationSetting(
    mysqli $conn,
    string $key,
    bool $default = true
): bool {
    $stmt = $conn->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");

    if (!$stmt) {
        return $default;
    }

    $stmt->bind_param('s', $key);
    $stmt->execute();
    $stmt->bind_result($value);

    if ($stmt->fetch()) {
        $stmt->close();
        return (string) $value === '1';
    }

    $stmt->close();

    return $default;
}

function emailNotificationsEnabled(mysqli $conn): bool
{
    return getNotificationSetting(
        $conn,
        'email_notifications_enabled'
    );
}

function fcmNotificationsEnabled(mysqli $conn): bool
{
    return getNotificationSetting(
        $conn,
        'fcm_notifications_enabled'
    );
}

function newApplicationNotificationsEnabled(mysqli $conn): bool
{
    return getNotificationSetting(
        $conn,
        'notify_new_application'
    );
}

function statusNotificationsEnabled(mysqli $conn): bool
{
    return getNotificationSetting(
        $conn,
        'notify_status_update'
    );
}

function donationNotificationsEnabled(mysqli $conn): bool
{
    return getNotificationSetting(
        $conn,
        'notify_donation_received'
    );
}

function pickupNotificationsEnabled(mysqli $conn): bool
{
    return getNotificationSetting(
        $conn,
        'notify_pickup_reminder'
    );
}