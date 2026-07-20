<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();

header(
    'Content-Type: application/json; charset=utf-8'
);

function respond(
    array $response,
    int $httpCode = 200
): never {
    http_response_code($httpCode);

    if (
        ob_get_length() !== false &&
        ob_get_length() > 0
    ) {
        ob_clean();
    }

    echo json_encode(
        $response,
        JSON_UNESCAPED_SLASHES |
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

try {
    require_once __DIR__ . '/db_connect.php';
    require_once __DIR__ . '/smtp_config.php';

    $email = trim($_POST['email'] ?? '');

    if ($email === '') {
        respond([
            'status' => 'error',
            'message' => 'Email required'
        ], 400);
    }

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {
        respond([
            'status' => 'error',
            'message' => 'Invalid email address'
        ], 400);
    }

    if (
        !isset($conn) ||
        !($conn instanceof mysqli)
    ) {
        throw new RuntimeException(
            'Database connection is unavailable.'
        );
    }

    $otp = sprintf(
        '%06d',
        random_int(0, 999999)
    );

    $deleteStmt = $conn->prepare(
        'DELETE FROM otps WHERE email = ?'
    );

    if (!$deleteStmt) {
        throw new RuntimeException(
            'Failed to prepare OTP cleanup query: ' .
            $conn->error
        );
    }

    $deleteStmt->bind_param('s', $email);

    if (!$deleteStmt->execute()) {
        throw new RuntimeException(
            'Failed to remove previous OTP: ' .
            $deleteStmt->error
        );
    }

    $deleteStmt->close();

    $insertStmt = $conn->prepare(
        'INSERT INTO otps
            (email, otp, expires_at)
         VALUES
            (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE))'
    );

    if (!$insertStmt) {
        throw new RuntimeException(
            'Failed to prepare OTP insert query: ' .
            $conn->error
        );
    }

    $insertStmt->bind_param(
        'ss',
        $email,
        $otp
    );

    if (!$insertStmt->execute()) {
        throw new RuntimeException(
            'Failed to save OTP: ' .
            $insertStmt->error
        );
    }

    $insertStmt->close();

    if (RESEND_API_KEY === '') {
        throw new RuntimeException(
            'RESEND_API_KEY is not configured.'
        );
    }

    $payload = json_encode([
        'from' =>
            RESEND_FROM_NAME .
            ' <' .
            RESEND_FROM_EMAIL .
            '>',
        'to' => [$email],
        'subject' => 'Your Anipet OTP',
        'html' =>
            '<p>Your Anipet verification code is:</p>' .
            '<h2 style="letter-spacing:4px;">' .
            htmlspecialchars(
                $otp,
                ENT_QUOTES,
                'UTF-8'
            ) .
            '</h2>' .
            '<p>This code expires in 5 minutes.</p>',
        'text' =>
            "Your Anipet OTP code is {$otp}. " .
            'It expires in 5 minutes.'
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException(
            'Failed to encode email request.'
        );
    }

    $curl = curl_init(
        'https://api.resend.com/emails'
    );

    if ($curl === false) {
        throw new RuntimeException(
            'Failed to initialize email request.'
        );
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' .
                RESEND_API_KEY,
            'Content-Type: application/json'
        ],
        CURLOPT_POSTFIELDS => $payload
    ]);

    $responseBody = curl_exec($curl);
    $curlError = curl_error($curl);

    $httpCode = (int) curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if ($responseBody === false) {
        throw new RuntimeException(
            $curlError !== ''
                ? $curlError
                : 'Email API request failed.'
        );
    }

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        throw new RuntimeException(
            "Resend returned HTTP {$httpCode}: " .
            $responseBody
        );
    }

    respond([
        'status' => 'success',
        'message' => 'OTP emailed successfully.'
    ]);
} catch (Throwable $exception) {
    error_log(
        'send_otp.php error: ' .
        $exception->getMessage()
    );

    respond([
        'status' => 'error',
        'message' =>
            'Unable to generate or send OTP.',
        'debug' =>
            $exception->getMessage()
    ], 500);
}