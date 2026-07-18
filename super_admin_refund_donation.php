<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_donations');

header("Content-Type: application/json");

$donation_id = intval($_POST["donation_id"] ?? 0);

// Check if donation exists
$stmt = $conn->prepare("SELECT * FROM donations WHERE id=?");
$stmt->bind_param("i", $donation_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0){

    echo json_encode([
        "status"=>"error",
        "message"=>"Donation not found."
    ]);
    exit;
}

// Check if already refunded
$donation = $result->fetch_assoc();

if($donation["refund_status"] == "Refunded"){

    echo json_encode([
        "status"=>"error",
        "message"=>"This donation has already been refunded."
    ]);
    exit;
}

// Refund donation
$update = $conn->prepare("
UPDATE donations
SET
refund_status='Refunded',
refunded_at=NOW()
WHERE id=?
");
$update->bind_param("i", $donation_id);
$update->execute();

echo json_encode([
    "status"=>"success",
    "message"=>"Donation refunded successfully."
]);
?>