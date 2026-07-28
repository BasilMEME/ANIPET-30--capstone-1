<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db_connect.php";

$userId = intval($_POST["user_id"] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid user ID."
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE user_notifications
    SET is_read = 1
    WHERE user_id = ?
      AND is_read = 0
");

if (!$stmt) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to prepare request."
    ]);
    exit;
}

$stmt->bind_param("i", $userId);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "All notifications marked as read."
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to mark notifications as read."
    ]);
}

$stmt->close();
$conn->close();