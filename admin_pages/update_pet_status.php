<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

header("Content-Type: application/json");

$id = intval($_POST['id'] ?? 0);
$status = trim($_POST['status'] ?? '');

// "Posted" and "Deceased" are deliberately excluded: those must go through
// post_pet_for_adoption.php / mark_pet_deceased.php, which perform the matching side
// effects (inserting into `pets`, copying the photo, stamping death fields). Allowing
// them here would let this free status field claim "Posted" without the pet ever
// actually existing in the adoptable `pets` table.
$allowedStatuses = ["Pending", "Claimed", "Paid", "Expired"];

if ($id <= 0) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid ID."
    ]);
    exit;
}

if (!in_array($status, $allowedStatuses, true)) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid status value."
    ]);
    exit;
}

$stmt = $conn->prepare("
    SELECT id
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

$update = $conn->prepare("
    UPDATE pet_pound
    SET status = ?
    WHERE id = ?
");
$update->bind_param("si", $status, $id);

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
    "new_status" => $status,
    "affected_rows" => $update->affected_rows
]);