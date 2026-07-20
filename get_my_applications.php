<?php
require_once __DIR__ . '/db_connect.php';
header("Content-Type: application/json");

$user_id = $_GET['user_id'] ?? '';

if ($user_id == '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing user_id"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, pet_id, user_id, applicant_name, message, status, qr_code, created_at
    FROM adoption_applications
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$applications = [];

while ($row = $result->fetch_assoc()) {
    $applications[] = $row;
}

echo json_encode([
    "status" => "success",
    "applications" => $applications
]);

$stmt->close();
$conn->close();
?>