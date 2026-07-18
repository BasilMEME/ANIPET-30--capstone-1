<?php
header("Content-Type: application/json");

require_once "db_connect.php";

$user_id = intval($_POST['user_id'] ?? 0);

$checkUser = $conn->prepare("SELECT id FROM users WHERE id = ?");
$checkUser->bind_param("i", $user_id);
$checkUser->execute();

if ($checkUser->get_result()->num_rows == 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit;
}

$donor_name = trim($_POST['donor_name'] ?? '');
$pet_name = trim($_POST['pet_name'] ?? '');
$amount = $_POST['amount'] ?? '';
$reference_number = trim($_POST['reference_number'] ?? '');
$payment_method = $_POST['payment_method'] ?? 'GCash';

if (
    empty($donor_name) ||
    empty($amount) ||
    empty($reference_number)
) {
    echo json_encode([
        "success" => false,
        "message" => "Please complete all required fields."
    ]);
    exit;
}

// Check duplicate reference number
$check = $conn->prepare(
    "SELECT id FROM donations WHERE reference_number = ?"
);

$check->bind_param("s", $reference_number);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "This reference number has already been submitted."
    ]);
    exit;
}

// ==============================
// Upload receipt (optional)
// ==============================

$receiptFilename = null;

if (
    isset($_FILES["receipt"]) &&
    $_FILES["receipt"]["error"] === UPLOAD_ERR_OK
) {

    $uploadDir = "donation_receipts/";

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo(
        $_FILES["receipt"]["name"],
        PATHINFO_EXTENSION
    );

    $receiptFilename = uniqid("receipt_") . "." . $extension;

    move_uploaded_file(
        $_FILES["receipt"]["tmp_name"],
        $uploadDir . $receiptFilename
    );
}

$refund_deadline = date(
    "Y-m-d H:i:s",
    strtotime("+48 hours")
);

// Save donation
$stmt = $conn->prepare("
INSERT INTO donations
(
    user_id,
    donor_name,
    pet_name,
    amount,
    reference_number,
    payment_method,
    receipt_image,
    donation_date,
    refund_deadline
)
VALUES
(
    ?, ?, ?, ?, ?, ?, ?, NOW(), ?
)
");

$stmt->bind_param(
    "issdssss",
    $user_id,
    $donor_name,
    $pet_name,
    $amount,
    $reference_number,
    $payment_method,
    $receiptFilename,
    $refund_deadline
);

if ($stmt->execute()) {

    echo json_encode([
        "success" => true,
        "message" => "Donation submitted successfully.",
        "receipt_filename" => $receiptFilename
    ]);

} else {

    echo json_encode([
        "success" => false,
        "message" => "Unable to save donation."
    ]);

}

$stmt->close();
$check->close();
$conn->close();