<?php

require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

header("Content-Type: application/json");

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request."
    ]);
    exit;
}

$id = isset($_POST["id"]) ? intval($_POST["id"]) : 0;

$stmt = $conn->prepare("
    SELECT status, posted_for_adoption
    FROM pet_pound
    WHERE id=?
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

if ($pet['status'] === 'Deceased') {
    echo json_encode([
        "success" => false,
        "message" => "This pet is recorded as deceased and cannot be claimed."
    ]);
    exit;
}

if (!empty($pet['posted_for_adoption'])) {
    echo json_encode([
        "success" => false,
        "message" => "This pet has already been posted for adoption and cannot be claimed here."
    ]);
    exit;
}

$stmt = $conn->prepare("
    UPDATE pet_pound
    SET status='Claimed'
    WHERE id=?
");

$stmt->bind_param("i", $id);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Unable to claim pet."
    ]);

}
