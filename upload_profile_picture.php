<?php
require_once __DIR__ . '/db_connect.php';

// TEMP DEBUGGING - remove after finding the issue
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Self-heal: profile_picture wasn't in the original users table.
$conn->query("ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `profile_picture` VARCHAR(255) DEFAULT NULL");

header('Content-Type: application/json');

$userId = $_POST['user_id'] ?? null;
if (!$userId || !isset($_FILES['image'])) {
    echo json_encode(["status" => "error", "message" => "Missing user_id or image"]);
    exit;
}

$uploadDir = __DIR__ . '/uploads/profile_pictures';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$file = $_FILES['image'];
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $allowed)) {
    echo json_encode(["status" => "error", "message" => "Invalid file type"]);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'jpg';
$filename = "user_{$userId}_" . time() . ".{$ext}";
$destPath = $uploadDir . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(["status" => "error", "message" => "Failed to save file"]);
    exit;
}

$relativePath = "uploads/profile_pictures/{$filename}";

$stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
$stmt->bind_param("si", $relativePath, $userId);
$stmt->execute();
$stmt->close();

// Return the same shape getUserProfile returns, so the Compose screen can just swap `profile` in place
$userStmt = $conn->prepare("SELECT id, full_name, username, email, phone, address, contact_preference, role, is_verified, profile_picture FROM users WHERE id = ?");
$userStmt->bind_param("i", $userId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
$userStmt->close();

$user['is_verified'] = (bool)$user['is_verified']; 

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
$user['profile_picture_url'] = $user['profile_picture'] ? $base_url . $user['profile_picture'] : null;

echo json_encode(["status" => "success", "user" => $user]);