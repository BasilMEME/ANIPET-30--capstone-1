<?php
date_default_timezone_set('Asia/Manila');
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/db_connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'status' => 'error',
        'message' => 'POST required'
    ]);
    exit;
}

$email = urldecode(trim($_POST['email'] ?? ''));
$otp = trim($_POST['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Email and OTP are required'
    ]);
    exit;
}

// Verify OTP
$verifyStmt = $conn->prepare("SELECT id FROM otps WHERE email = ? AND otp = ? AND expires_at > NOW()");
$verifyStmt->bind_param("ss", $email, $otp);
$verifyStmt->execute();
$verifyResult = $verifyStmt->get_result();

if ($verifyResult->num_rows === 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or expired OTP'
    ]);
    $verifyStmt->close();
    $conn->close();
    exit;
}
$verifyStmt->close();

// Delete OTP after use
$deleteStmt = $conn->prepare("DELETE FROM otps WHERE email = ?");
$deleteStmt->bind_param("s", $email);
$deleteStmt->execute();
$deleteStmt->close();

$updateStmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE email = ?");
$updateStmt->bind_param("s", $email);
if ($updateStmt->execute()) {
    echo json_encode([
        'status' => 'success',
        'message' => 'OTP verified'
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Verification failed'
    ]);
}
$updateStmt->close();
$conn->close();