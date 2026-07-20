<?php

ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

session_start();

function createUserSession($conn, $userId)
{
    $sessionId = session_id();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';

    $stmt = $conn->prepare(
        "INSERT INTO user_sessions
        (
            user_id,
            session_id,
            ip_address,
            user_agent,
            created_at,
            last_active_at,
            is_active
        )
        VALUES (?, ?, ?, ?, NOW(), NOW(), 1)
        ON DUPLICATE KEY UPDATE
            last_active_at = NOW(),
            is_active = 1"
    );

    if ($stmt) {
        $stmt->bind_param(
            'isss',
            $userId,
            $sessionId,
            $ip,
            $userAgent
        );

        $stmt->execute();
        $stmt->close();
    }
}

try {
    require_once __DIR__ . '/db_connect.php';

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'OPTIONS') {
        http_response_code(204);
        exit;
    }

    $postData = $_POST;

    if ($method !== 'POST') {
        $postData = $_GET + $postData;
    }

    $identifier = urldecode(
        trim(
            $postData['email']
                ?? $postData['username']
                ?? ''
        )
    );

    $password = trim(
        $postData['password'] ?? ''
    );

    if ($identifier === '' || $password === '') {
        echo json_encode([
            'status' => 'error',
            'message' =>
                'Email or username and password required'
        ]);
        exit;
    }

    $stmt = $conn->prepare(
        "SELECT
            id,
            username,
            full_name,
            email,
            password,
            is_verified,
            role,
            is_suspended,
            is_deleted
        FROM users
        WHERE email = ?
           OR username = ?
        LIMIT 1"
    );

    if (!$stmt) {
        throw new RuntimeException(
            'Failed to prepare login query: ' .
            $conn->error
        );
    }

    $stmt->bind_param(
        'ss',
        $identifier,
        $identifier
    );

    $stmt->execute();
    $result = $stmt->get_result();

    if (!$row = $result->fetch_assoc()) {
        echo json_encode([
            'status' => 'error',
            'message' => 'User not found'
        ]);
        exit;
    }

    $stmt->close();

    if (!password_verify($password, $row['password'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid password'
        ]);
        exit;
    }

    if (!empty($row['is_deleted'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This account no longer exists'
        ]);
        exit;
    }

    if (!empty($row['is_suspended'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'This account has been suspended'
        ]);
        exit;
    }

    $adminLoginOnly =
        isset($postData['admin_login']) &&
        (int) $postData['admin_login'] === 1;

    $isAdminRole = in_array(
        $row['role'],
        ['admin', 'super_admin'],
        true
    );

    if ($adminLoginOnly && !$isAdminRole) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Admin access is required'
        ]);
        exit;
    }

    $requiresVerification =
        isset($row['is_verified']) &&
        (int) $row['is_verified'] === 0;

    $isRegularUser =
        $row['role'] === 'user';

    if ($isRegularUser && $requiresVerification) {
        echo json_encode([
            'status' => 'unverified',
            'message' => 'Account not verified',
            'user' => [
                'id' => $row['id'],
                'username' => $row['username'],
                'full_name' => $row['full_name'],
                'email' => $row['email'],
                'role' => $row['role']
            ]
        ]);
        exit;
    }

    $_SESSION['user_id'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['email'] = $row['email'];
    $_SESSION['role'] = $row['role'];
    $_SESSION['logged_in_at'] = date('c');

    createUserSession(
        $conn,
        $row['id']
    );

    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'user' => [
            'id' => $row['id'],
            'username' => $row['username'],
            'full_name' => $row['full_name'],
            'email' => $row['email'],
            'role' => $row['role']
        ]
    ]);

    exit;
} catch (Throwable $t) {
    error_log(
        'login.php error: ' .
        $t->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error'
    ]);
}