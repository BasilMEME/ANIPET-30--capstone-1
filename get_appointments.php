<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

$user_id = $_GET['user_id'] ?? '';
if (empty($user_id)) {
    echo json_encode(["status" => "error", "message" => "Missing user_id"]);
    exit;
}

$stmt = $conn->prepare(
    "SELECT a.id, a.user_id, a.pet_id, a.title, a.details, a.scheduled_at, a.status, a.created_at,
            a.application_id, a.appointment_type, p.name AS pet_name
     FROM appointments a
     LEFT JOIN pets p ON a.pet_id = p.id
     WHERE a.user_id = ?
     ORDER BY a.created_at DESC"
);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    $appointments[] = [
        "id" => $row['id'],
        "user_id" => $row['user_id'],
        "pet_id" => $row['pet_id'],
        "title" => $row['title'],
        "details" => $row['details'],
        "scheduled_at" => $row['scheduled_at'],
        "status" => $row['status'],
        "created_at" => $row['created_at'],
        "application_id" => $row['application_id'],
        "appointment_type" => $row['appointment_type']
    ];
}

echo json_encode(["status" => "success", "appointments" => $appointments]);

$stmt->close();
$conn->close();
