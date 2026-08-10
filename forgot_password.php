<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/smtp_config.php";
require_once __DIR__ . "/gmail_api_helper.php";

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

$input = json_decode(
    file_get_contents("php://input"),
    true
);

if (!is_array($input)) {
    $input = $_POST;
}

$email = strtolower(
    trim($input["email"] ?? "")
);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond([
        "success" => false,
        "message" => "Please enter a valid email address."
    ], 400);
}

try {
    $userStmt = $conn->prepare("
        SELECT id, full_name
        FROM users
        WHERE LOWER(email) = ?
          AND role = 'user'
        LIMIT 1
    ");

    if (!$userStmt) {
        throw new RuntimeException(
            "Unable to prepare user lookup: " . $conn->error
        );
    }

    $userStmt->bind_param("s", $email);
    $userStmt->execute();

    $user = $userStmt
        ->get_result()
        ->fetch_assoc();

    $userStmt->close();

    if (!$user) {
        respond([
            "success" => false,
            "message" => "This email is not registered in AniPet."
        ]);
    }

    $userId = (int)$user["id"];
    $fullName = trim($user["full_name"] ?? "");

    $otp = sprintf(
        "%06d",
        random_int(0, 999999)
    );

    $otpHash = password_hash(
        $otp,
        PASSWORD_DEFAULT
    );

    if ($otpHash === false) {
        throw new RuntimeException(
            "Unable to secure the reset code."
        );
    }

    /*
     * Remove any previous unused reset codes for this user.
     */
    $deleteStmt = $conn->prepare("
        DELETE FROM password_reset_otps
        WHERE user_id = ?
          AND used_at IS NULL
    ");

    if (!$deleteStmt) {
        throw new RuntimeException(
            "Unable to prepare OTP cleanup: " . $conn->error
        );
    }

    $deleteStmt->bind_param(
        "i",
        $userId
    );

    if (!$deleteStmt->execute()) {
        throw new RuntimeException(
            "Unable to remove previous reset codes: "
            . $deleteStmt->error
        );
    }

    $deleteStmt->close();

    /*
     * Save the new reset code for 10 minutes.
     */
    $insertStmt = $conn->prepare("
        INSERT INTO password_reset_otps
        (
            user_id,
            email,
            otp_hash,
            expires_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            DATE_ADD(NOW(), INTERVAL 10 MINUTE)
        )
    ");

    if (!$insertStmt) {
        throw new RuntimeException(
            "Unable to prepare OTP insert: " . $conn->error
        );
    }

    $insertStmt->bind_param(
        "iss",
        $userId,
        $email,
        $otpHash
    );

    if (!$insertStmt->execute()) {
        throw new RuntimeException(
            "Unable to save the reset code: "
            . $insertStmt->error
        );
    }

    $insertStmt->close();

    $safeName = htmlspecialchars(
        $fullName !== "" ? $fullName : "AniPet User",
        ENT_QUOTES,
        "UTF-8"
    );

    $safeOtp = htmlspecialchars(
        $otp,
        ENT_QUOTES,
        "UTF-8"
    );

    $htmlBody =
        '<!DOCTYPE html>' .
        '<html>' .
        '<body style="font-family:Arial,sans-serif;color:#111827;">' .
        '<div style="max-width:560px;margin:auto;padding:24px;">' .
        '<h2 style="color:#F2867E;">AniPet Password Reset</h2>' .
        '<p>Hello ' . $safeName . ',</p>' .
        '<p>We received a request to reset your AniPet password.</p>' .
        '<p>Your verification code is:</p>' .
        '<div style="' .
            'font-size:32px;' .
            'font-weight:bold;' .
            'letter-spacing:8px;' .
            'margin:20px 0;' .
        '">' .
            $safeOtp .
        '</div>' .
        '<p>This code expires in 10 minutes.</p>' .
        '<p>If you did not request a password reset, you may ignore this email.</p>' .
        '</div>' .
        '</body>' .
        '</html>';

    sendGmailMessage(
        $email,
        "AniPet Password Reset Code",
        $htmlBody
    );

    respond([
        "success" => true,
        "message" => "A 6-digit OTP was sent to your email."
    ]);

} catch (Throwable $exception) {
    error_log(
        "forgot_password.php error: "
        . $exception->getMessage()
    );

    respond([
        "success" => false,
        "message" =>
            "Unable to process the password reset request right now."
    ], 500);
}
