<?php

header('Content-Type: application/json');
require_once __DIR__ . '/db_connect.php';

$notificationId = intval($_POST['notification_id'] ?? 0);
$userId = intval($_POST['user_id'] ?? 0);

if ($notificationId <= 0 || $userId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid notification or user ID'
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE user_notifications
    SET is_read = 1
    WHERE id = ?
      AND user_id = ?
");

$stmt->bind_param('ii', $notificationId, $userId);

if (!$stmt->execute()) {
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update notification'
    ]);

    $stmt->close();
    $conn->close();
    exit;
}

if ($stmt->affected_rows === 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Notification not found or already read'
    ]);
} else {
    echo json_encode([
        'success' => true,
        'message' => 'Notification marked as read'
    ]);
}

$stmt->close();
$conn->close();