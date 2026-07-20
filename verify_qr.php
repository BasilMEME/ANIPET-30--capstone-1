<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db_connect.php";

$qr_data = $_GET['qr_code'] ?? '';

// The scanner decodes the QR image back into the string that was encoded into it
// ("ANIPET|APP:...|PET:...|DATE:..."), which is stored in qr_data — not qr_code,
// which holds the image *file path* used for display/email instead.
$qr_data = $_GET['qr_code'] ?? '';

if (empty($qr_data)) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing qr_code parameter"
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT
        aa.id,
        aa.user_id,
        aa.pet_id,
        aa.applicant_name,
        aa.message,
        aa.status,
        aa.qr_code,
        aa.created_at,
        p.name AS pet_name,
        p.breed,
        p.age,
        p.gender
    FROM adoption_applications aa
    JOIN pets p ON aa.pet_id = p.id
    WHERE aa.qr_data = ?
    LIMIT 1
");

if (!$stmt) {
    echo json_encode([
        "status" => "error",
        "message" => "Database query failed: " . $conn->error
    ]);
    $conn->close();
    exit;
}

$stmt->bind_param("s", $qr_data);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "status" => "success",
        "message" => "QR verified successfully",
        "application" => $row  // ✅ Matches your VerifiedApplication model
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "QR code not found or invalid"
    ]);
}

$stmt->close();
$conn->close();
?>