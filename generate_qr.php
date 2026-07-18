<?php
require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/phpqrcode/qrlib.php";

header("Content-Type: application/json");

$record_id = $_GET['record_id'] ?? '';

if ($record_id === '') {
    echo json_encode(["status" => "error", "message" => "Missing record_id"]);
    exit;
}

// Get adoption record + user + pet
$sql = "
SELECT ar.id, ar.user_id, ar.pet_id, ar.adoption_date,
       u.full_name, u.email,
       p.name AS pet_name, p.breed
FROM adoption_records ar
JOIN users u ON ar.user_id = u.id
JOIN pets p ON ar.pet_id = p.id
WHERE ar.id = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $record_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(["status" => "error", "message" => "Record not found"]);
    exit;
}

$data = $res->fetch_assoc();

// QR content (JSON string)
$qrText = json_encode([
    "record_id" => (int)$data["id"],
    "adopter" => $data["full_name"],
    "email" => $data["email"],
    "pet_name" => $data["pet_name"],
    "breed" => $data["breed"],
    "adoption_date" => $data["adoption_date"]
]);

// File path
$filename = "qr_" . $data["id"] . ".png";
$filepath = __DIR__ . "/qr_codes/" . $filename;

// Generate PNG
QRcode::png($qrText, $filepath, QR_ECLEVEL_L, 6);

// Save path in DB (relative path)
$relativePath = "qr_codes/" . $filename;

$up = $conn->prepare("UPDATE adoption_records SET qr_code_path = ? WHERE id = ?");
$up->bind_param("si", $relativePath, $record_id);
$up->execute();

echo json_encode([
    "status" => "success",
    "qr_path" => $relativePath,
    "qr_url" => "http://localhost/pet_adoption_api/" . $relativePath
]);

$stmt->close();
$up->close();
$conn->close();
?>