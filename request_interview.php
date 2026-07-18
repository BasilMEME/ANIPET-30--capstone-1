<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/application_status_helper.php";

$application_id = $_POST['application_id'] ?? '';

if ($application_id === '') {
    echo json_encode(["status" => "error", "message" => "Missing application_id"]);
    exit;
}

// The applicant is only requesting an interview here, not choosing its date/time.
// Routed through the shared helper (not a raw UPDATE) so this also creates the
// linked 'pending' interview appointment admin schedules via Appointment Management.
$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';
$result = applyApplicationStatusChange($conn, (int)$application_id, 'screening', null, 'Interview requested by applicant', null, $base_url);

if ($result['success']) {
    echo json_encode(["status" => "success", "message" => "Interview requested"]);
} else {
    echo json_encode(["status" => "error", "message" => $result['message']]);
}

$conn->close();
?>
