<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/return_policy_helper.php";

$application_id = $_POST['application_id'] ?? '';
$user_id = $_POST['user_id'] ?? '';
$pet_id = $_POST['pet_id'] ?? '';
$reason = trim($_POST['reason'] ?? '');
// Penalty amount is a configurable shelter policy value (see admin_pages/settings.php),
// not something the requesting user may set — any client-supplied value is ignored.
$penalty_amount = calculate_return_penalty($conn);

if ($application_id === '' || $user_id === '' || $pet_id === '' || $reason === '') {
    echo json_encode(["status" => "error", "message" => "Missing required fields for return request."]);
    exit;
}

$stmt = $conn->prepare("INSERT INTO return_requests (application_id, user_id, pet_id, reason, penalty_amount, penalty_paid, status) VALUES (?, ?, ?, ?, ?, 0, 'pending')");
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param("iiisd", $application_id, $user_id, $pet_id, $reason, $penalty_amount);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Return request submitted successfully.", "return_request_id" => (string)$stmt->insert_id]);
} else {
    echo json_encode(["status" => "error", "message" => "Insert failed: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>