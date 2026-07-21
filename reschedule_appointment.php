<?php
// reschedule_appointment.php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["status" => "error", "message" => "POST required"]);
    exit;
}

$appointment_id = $_POST['appointment_id'] ?? '';
$scheduled_at   = trim($_POST['scheduled_at'] ?? '');

if (empty($appointment_id) || empty($scheduled_at)) {
    echo json_encode(["status" => "error", "message" => "Missing required fields"]);
    exit;
}

$stmt = $conn->prepare(
    "UPDATE appointments SET scheduled_at = ?, status = 'pending' WHERE id = ?"
);
$stmt->bind_param("si", $scheduled_at, $appointment_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "Appointment rescheduled"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Appointment not found or no change made"]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Failed to reschedule appointment"]);
}

$stmt->close();
$conn->close();