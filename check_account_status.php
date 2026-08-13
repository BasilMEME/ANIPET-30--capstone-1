<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    require_once __DIR__ . '/db_connect.php';

    $userId = (int) ($_GET['user_id'] ?? 0);

    if ($userId <= 0) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Missing user id'
        ]);

        exit;
    }

    $stmt = $conn->prepare(
        "SELECT
            is_suspended,
            is_deleted,
            suspension_reason
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException(
            'Failed to prepare account status query.'
        );
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    if (!$user) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'status' => 'not_found',
            'message' => 'User account not found'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'is_suspended' =>
            (int) $user['is_suspended'] === 1,
        'is_deleted' =>
            (int) $user['is_deleted'] === 1,
        'reason' =>
            trim(
                (string) (
                    $user['suspension_reason']
                    ?? ''
                )
            )
    ]);

    exit;

} catch (Throwable $error) {
    error_log(
        'check_account_status.php error: ' .
        $error->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
