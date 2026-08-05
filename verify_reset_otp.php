<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

require_once __DIR__ . "/db_connect.php";

function respond(
    bool $success,
    string $message,
    int $statusCode = 200
): void {
    http_response_code($statusCode);

    echo json_encode([
        "success" => $success,
        "status" => $success ? "success" : "error",
        "message" => $message
    ]);

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

$otp = trim(
    $input["otp"] ?? ""
);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(
        false,
        "Please enter a valid email address.",
        400
    );
}

if (!preg_match('/^\d{6}$/', $otp)) {
    respond(
        false,
        "Please enter the 6-digit verification code.",
        400
    );
}

try {
    /*
     * Find the most recent unused reset request.
     */
    $stmt = $conn->prepare("
        SELECT
            id,
            otp_hash,
            expires_at,
            attempts,
            verified_at,
            used_at
        FROM password_reset_otps
        WHERE email = ?
          AND used_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");

    if (!$stmt) {
        throw new RuntimeException(
            "Unable to prepare OTP lookup: " .
            $conn->error
        );
    }

    $stmt->bind_param(
        "s",
        $email
    );

    $stmt->execute();

    $resetRequest = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();

    if (!$resetRequest) {
        respond(
            false,
            "No active password reset request was found.",
            404
        );
    }

    $resetId = (int)$resetRequest["id"];
    $attempts = (int)$resetRequest["attempts"];

    /*
     * Prevent unlimited OTP guessing.
     */
    if ($attempts >= 5) {
        respond(
            false,
            "Too many incorrect attempts. Please request a new code.",
            429
        );
    }

    /*
     * Check expiration using the database server time.
     */
    $expiryStmt = $conn->prepare("
        SELECT
            CASE
                WHEN expires_at > NOW() THEN 1
                ELSE 0
            END AS is_valid
        FROM password_reset_otps
        WHERE id = ?
        LIMIT 1
    ");

    if (!$expiryStmt) {
        throw new RuntimeException(
            "Unable to prepare expiration check: " .
            $conn->error
        );
    }

    $expiryStmt->bind_param(
        "i",
        $resetId
    );

    $expiryStmt->execute();

    $expiryResult = $expiryStmt
        ->get_result()
        ->fetch_assoc();

    $expiryStmt->close();

    if (
        !$expiryResult ||
        (int)$expiryResult["is_valid"] !== 1
    ) {
        respond(
            false,
            "The verification code has expired. Please request a new one.",
            400
        );
    }

    /*
     * Compare the entered OTP with the hashed OTP.
     */
    if (
        !password_verify(
            $otp,
            $resetRequest["otp_hash"]
        )
    ) {
        $attemptUpdate = $conn->prepare("
            UPDATE password_reset_otps
            SET attempts = attempts + 1
            WHERE id = ?
        ");

        if ($attemptUpdate) {
            $attemptUpdate->bind_param(
                "i",
                $resetId
            );

            $attemptUpdate->execute();
            $attemptUpdate->close();
        }

        respond(
            false,
            "The verification code is incorrect.",
            400
        );
    }

    /*
     * Mark the OTP as verified.
     */
    $verifyStmt = $conn->prepare("
        UPDATE password_reset_otps
        SET verified_at = NOW()
        WHERE id = ?
          AND used_at IS NULL
    ");

    if (!$verifyStmt) {
        throw new RuntimeException(
            "Unable to prepare OTP verification: " .
            $conn->error
        );
    }

    $verifyStmt->bind_param(
        "i",
        $resetId
    );

    if (!$verifyStmt->execute()) {
        throw new RuntimeException(
            "Unable to verify the reset code: " .
            $verifyStmt->error
        );
    }

    $verifyStmt->close();

    respond(
        true,
        "Verification code confirmed."
    );

} catch (Throwable $exception) {
    error_log(
        "verify_reset_otp.php error: " .
        $exception->getMessage()
    );

    respond(
        false,
        "Unable to verify the reset code right now.",
        500
    );
}