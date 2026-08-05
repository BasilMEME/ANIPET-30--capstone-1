<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db_connect.php";

function respond($success, $message, $statusCode = 200)
{
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "status" => $success ? "success" : "error",
        "message" => $message
    ]);

    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = $_POST;
}

$email = strtolower(trim($input["email"] ?? ""));
$password = trim($input["password"] ?? "");

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, "Please enter a valid email address.", 400);
}

if (strlen($password) < 8) {
    respond(false, "Password must be at least 8 characters.", 400);
}

/*
|--------------------------------------------------------------------------
| Find verified reset request
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
SELECT
    id,
    user_id,
    verified_at,
    used_at
FROM password_reset_otps
WHERE email = ?
AND verified_at IS NOT NULL
AND used_at IS NULL
ORDER BY id DESC
LIMIT 1
");

$stmt->bind_param("s", $email);
$stmt->execute();

$reset = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$reset) {

    respond(
        false,
        "Password reset request was not verified.",
        400
    );
}

/*
|--------------------------------------------------------------------------
| Update password
|--------------------------------------------------------------------------
*/

$passwordHash = password_hash(
    $password,
    PASSWORD_DEFAULT
);

$updateUser = $conn->prepare("
UPDATE users
SET password = ?
WHERE id = ?
");

$updateUser->bind_param(
    "si",
    $passwordHash,
    $reset["user_id"]
);

if (!$updateUser->execute()) {

    respond(
        false,
        "Unable to update password.",
        500
    );
}

$updateUser->close();

/*
|--------------------------------------------------------------------------
| Mark OTP as used
|--------------------------------------------------------------------------
*/

$used = $conn->prepare("
UPDATE password_reset_otps
SET used_at = NOW()
WHERE id = ?
");

$used->bind_param(
    "i",
    $reset["id"]
);

$used->execute();
$used->close();

/*
|--------------------------------------------------------------------------
| Remove old reset requests
|--------------------------------------------------------------------------
*/

$cleanup = $conn->prepare("
DELETE FROM password_reset_otps
WHERE user_id = ?
AND id <> ?
");

$cleanup->bind_param(
    "ii",
    $reset["user_id"],
    $reset["id"]
);

$cleanup->execute();
$cleanup->close();

respond(
    true,
    "Password changed successfully."
);