<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db_connect.php";

$application_id = $_POST['application_id'] ?? '';

if ($application_id === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Application ID is required."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Check if the application exists and can still be withdrawn
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT status
    FROM adoption_applications
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $application_id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode([
        "status" => "error",
        "message" => "Application not found."
    ]);
    exit;
}

$row = $result->fetch_assoc();
$currentStatus = strtolower($row['status']);

$stmt->close();

/*
|--------------------------------------------------------------------------
| Only allow withdrawal before approval
|--------------------------------------------------------------------------
*/

$allowedStatuses = [
    "pending",
    "screening"
];

if (!in_array($currentStatus, $allowedStatuses)) {
    echo json_encode([
        "status" => "error",
        "message" => "This application can no longer be withdrawn."
    ]);
    exit;
}

/*
|--------------------------------------------------------------------------
| Update application status
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    UPDATE adoption_applications
    SET status = 'withdrawn'
    WHERE id = ?
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $application_id);

if ($stmt->execute()) {

    echo json_encode([
        "status" => "success",
        "message" => "Application withdrawn successfully."
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Update failed: " . $stmt->error
    ]);

}

$stmt->close();
$conn->close();