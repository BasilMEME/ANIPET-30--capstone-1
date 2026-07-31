<?php
header("Content-Type: application/json");
header('Access-Control-Allow-Origin: *');
require_once __DIR__ . "/db_connect.php";

$base_url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/';

$user_id = $_GET['user_id'] ?? '';

if ($user_id === '') {
    echo json_encode([
        "status" => "error",
        "message" => "Missing user_id"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        aa.id,
        aa.pet_id,
        aa.user_id,
        aa.applicant_name,
        aa.message,
        aa.id_documents,
        aa.house_photos,
        aa.status,
        aa.qr_code,
        aa.created_at,
        aa.interview_datetime,
        aa.admin_notes,
        p.name AS pet_name,
        p.breed,
        p.age,
        p.gender,
        p.image AS pet_image
    FROM adoption_applications aa
    JOIN pets p ON aa.pet_id = p.id
    WHERE aa.user_id = ?
    ORDER BY aa.id DESC
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Prepare failed: " . $conn->error
    ]);
    exit;
}

$stmt->bind_param("i", $user_id);

if (!$stmt->execute()) {
    echo json_encode([
        "status" => "error",
        "message" => "Execute failed: " . $stmt->error
    ]);
    exit;
}

$result = $stmt->get_result();
$applications = [];

while ($row = $result->fetch_assoc()) {
    // decode JSON fields if present so API returns arrays instead of JSON strings
    if (!empty($row['id_documents'])) {
        $decoded = json_decode($row['id_documents'], true);
        $decoded = $decoded !== null ? $decoded : [];
        // prefix with base URL
        $prefixed = array_map(function($p) use ($base_url) { return $base_url . $p; }, $decoded);
        $row['id_documents'] = $prefixed;
    } else {
        $row['id_documents'] = [];
    }

    if (!empty($row['house_photos'])) {
        $decoded = json_decode($row['house_photos'], true);
        $decoded = $decoded !== null ? $decoded : [];
        $prefixed = array_map(function($p) use ($base_url) { return $base_url . $p; }, $decoded);
        $row['house_photos'] = $prefixed;
    } else {
        $row['house_photos'] = [];
    }

    if (!empty($row['qr_code'])) {
        $row['qr_code'] = (strpos($row['qr_code'], 'http') === 0) ? $row['qr_code'] : $base_url . $row['qr_code'];
    } else {
        $row['qr_code'] = null;
    }

    if (!empty($row['pet_image'])) {
        $row['pet_image'] = (strpos($row['pet_image'], 'http') === 0) ? $row['pet_image'] : $base_url . 'images/' . $row['pet_image'];
    } else {
        $row['pet_image'] = null;
    }

    // include interview and admin notes if available
    $row['interview_datetime'] = $row['interview_datetime'] ?? null;
    $row['admin_notes'] = $row['admin_notes'] ?? null;

    $applications[] = $row;
}

echo json_encode([
    "status" => "success",
    "applications" => $applications
]);

$stmt->close();
$conn->close();
?>