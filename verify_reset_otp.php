<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db_connect.php";

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = $_POST;
}

$email = strtolower(trim($input["email"] ?? ""));
$otp   = trim($input["otp"] ?? "");

if ($email === "" || $otp === "") {
    echo json_encode([
        "success" => false,
        "message" => "Email and OTP are required."
    ]);
    exit;
}

$stmt = $conn->prepare("
SELECT id
FROM password_reset_otps
WHERE email = ?
AND otp = ?
AND expires_at > NOW()
LIMIT 1
");

$stmt->bind_param("ss", $email, $otp);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {

    echo json_encode([
        "success" => false,
        "message" => "Invalid or expired OTP."
    ]);
    exit;
}

echo json_encode([
    "success" => true,
    "message" => "OTP verified."
]);