<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/db_connect.php';

$userId = intval($_GET['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user ID',
        'notifications' => [],
        'unread_count' => 0
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        user_id,
        application_id,
        title,
        message,
        type,
        is_read,
        created_at
    FROM user_notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 100
");

$stmt->bind_param('i', $userId);
$stmt->execute();

$result = $stmt->get_result();
$notifications = [];
$unreadCount = 0;

while ($row = $result->fetch_assoc()) {
    $row['id'] = (int) $row['id'];
    $row['user_id'] = (int) $row['user_id'];
    $row['application_id'] = $row['application_id'] !== null
        ? (int) $row['application_id']
        : null;
    $row['is_read'] = (int) $row['is_read'];

    if ($row['is_read'] === 0) {
        $unreadCount++;
    }

    $notifications[] = $row;
}

$stmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Notifications retrieved successfully',
    'notifications' => $notifications,
    'unread_count' => $unreadCount
]);

$conn->close();