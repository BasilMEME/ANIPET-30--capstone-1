<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

header("Content-Type: application/json");

$id = intval($_POST['id'] ?? 0);
$recordType = trim($_POST['record_type'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid ID."
    ]);
    exit;
}

$allowedTypes = ["Illness", "Euthanasia"];

if (!in_array($recordType, $allowedTypes, true)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid cause of death."
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id, status
    FROM pet_pound
    WHERE id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$pet = $stmt->get_result()->fetch_assoc();

if (!$pet) {
    echo json_encode([
        "success" => false,
        "message" => "Pet not found."
    ]);
    exit;
}

if ($pet['status'] === 'Posted') {
    echo json_encode([
        "success" => false,
        "message" => "This pet has already been posted for adoption and cannot be marked deceased here."
    ]);
    exit;
}

$deathDate = date('Y-m-d H:i:s');
$status = 'Deceased';

$update = $conn->prepare("
    UPDATE pet_pound
    SET
        status = ?,
        cause_of_death = ?,
        death_remarks = ?,
        death_date = ?
    WHERE id = ?
");
$update->bind_param(
    "ssssi",
    $status,
    $recordType,
    $remarks,
    $deathDate,
    $id
);

if (!$update->execute()) {
    echo json_encode([
        "success" => false,
        "message" => $update->error
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "pet_id" => $id,
    "cause_of_death" => $recordType
]);
