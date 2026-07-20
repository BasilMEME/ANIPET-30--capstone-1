<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

$resetKey = $_GET['key'] ?? '';

if ($resetKey !== getenv('ADMIN_RESET_KEY')) {
    http_response_code(403);
    exit(json_encode([
        'status' => 'error',
        'message' => 'Unauthorized'
    ]));
}

$host = getenv('MYSQLHOST');
$port = (int) (getenv('MYSQLPORT') ?: 3306);
$user = getenv('MYSQLUSER');
$pass = getenv('MYSQLPASSWORD');
$db   = getenv('MYSQLDATABASE');

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    http_response_code(500);
    exit(json_encode([
        'status' => 'error',
        'message' => 'Database connection failed'
    ]));
}

$conn->set_charset('utf8mb4');

$username = 'super_admin123';

/*
 * Replace this temporary password before uploading,
 * or preferably read it from a Railway variable.
 */
$newPassword = getenv('NEW_ADMIN_PASSWORD');

if (!$newPassword) {
    http_response_code(500);
    exit(json_encode([
        'status' => 'error',
        'message' => 'NEW_ADMIN_PASSWORD is not configured'
    ]));
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

$stmt = $conn->prepare(
    'UPDATE users SET password = ? WHERE username = ?'
);

if (!$stmt) {
    http_response_code(500);
    exit(json_encode([
        'status' => 'error',
        'message' => 'Failed to prepare query'
    ]));
}

$stmt->bind_param('ss', $passwordHash, $username);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $response = [
        'status' => 'success',
        'message' => 'Super-admin password updated'
    ];
} else {
    $response = [
        'status' => 'error',
        'message' => 'Account not found or password was unchanged'
    ];
}

$stmt->close();
$conn->close();

echo json_encode($response);