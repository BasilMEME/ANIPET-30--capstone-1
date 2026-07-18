<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST required"]);
    exit;
}

$user_id = $_POST['user_id'] ?? '';
$title = trim($_POST['title'] ?? '');
$details = trim($_POST['details'] ?? '');
$scheduled_at = trim($_POST['scheduled_at'] ?? '');
$pet_id = $_POST['pet_id'] ?? null;

if (empty($user_id) || empty($title)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

if ($pet_id === '') {
    $pet_id = null;
}

$stmt = $conn->prepare(
    "INSERT INTO appointments (user_id, pet_id, title, details, scheduled_at, status) VALUES (?, ?, ?, ?, ?, 'pending')"
);
$stmt->bind_param("iisss", $user_id, $pet_id, $title, $details, $scheduled_at);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Appointment requested", "appointment_id" => $stmt->insert_id]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to book appointment"]);
}

$stmt->close();
$conn->close();
