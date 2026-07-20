<?php

declare(strict_types=1);

date_default_timezone_set('Asia/Manila');

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

ob_start();

header('Content-Type: application/json; charset=utf-8');

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

/**
 * Performs an HTTPS POST request.
 */
function postRequest(
    string $url,
    array $headers,
    string $body
): array {
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException(
            'Failed to initialize HTTP request.'
        );
    }

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => $body
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
                : 'HTTP request failed.'
        );
    }

    return [
        'http_code' => $httpCode,
        'body' => $responseBody
    ];
}

/**
 * Exchanges the stored refresh token for a temporary access token.
 */
function getGoogleAccessToken(): string
{
    if (
        GOOGLE_CLIENT_ID === '' ||
        GOOGLE_CLIENT_SECRET === '' ||
        GOOGLE_REFRESH_TOKEN === ''
    ) {
        throw new RuntimeException(
            'Google OAuth credentials are not configured.'
        );
    }

    $body = http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'refresh_token' => GOOGLE_REFRESH_TOKEN,
        'grant_type' => 'refresh_token'
    ]);

    $response = postRequest(
        'https://oauth2.googleapis.com/token',
        [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        $body
    );

    $decoded = json_decode(
        $response['body'],
        true
    );

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300
    ) {
        $googleMessage =
            is_array($decoded)
                ? ($decoded['error_description']
                    ?? $decoded['error']
                    ?? $response['body'])
                : $response['body'];

        throw new RuntimeException(
            'Google token request failed: ' .
            $googleMessage
        );
    }

    $accessToken =
        is_array($decoded)
            ? ($decoded['access_token'] ?? '')
            : '';

    if ($accessToken === '') {
        throw new RuntimeException(
            'Google did not return an access token.'
        );
    }

    return $accessToken;
}

/**
 * Converts standard Base64 into Gmail-compatible Base64URL.
 */
function base64UrlEncode(string $value): string
{
    return rtrim(
        strtr(
            base64_encode($value),
            '+/',
            '-_'
        ),
        '='
    );
}

/**
 * Encodes email headers safely.
 */
function encodeMailHeader(string $value): string
{
    return '=?UTF-8?B?' .
        base64_encode($value) .
        '?=';
}

/**
 * Sends the OTP using Gmail API over HTTPS.
 */
function sendOtpWithGmailApi(
    string $recipientEmail,
    string $otp
): void {
    $accessToken = getGoogleAccessToken();

    $subject = 'Your AniPet OTP Code';

    $htmlBody =
        '<!DOCTYPE html>' .
        '<html>' .
        '<body style="font-family:Arial,sans-serif;">' .
        '<h2>AniPet Verification</h2>' .
        '<p>Your verification code is:</p>' .
        '<div style="' .
            'font-size:32px;' .
            'font-weight:bold;' .
            'letter-spacing:8px;' .
            'margin:20px 0;' .
        '">' .
            htmlspecialchars(
                $otp,
                ENT_QUOTES,
                'UTF-8'
            ) .
        '</div>' .
        '<p>This code expires in 5 minutes.</p>' .
        '<p>If you did not request this code, ' .
        'you may ignore this email.</p>' .
        '</body>' .
        '</html>';

    $fromName = encodeMailHeader(
        GMAIL_FROM_NAME
    );

    $mimeMessage =
        'From: ' .
        $fromName .
        ' <' .
        GMAIL_FROM_EMAIL .
        ">\r\n" .
        'To: ' .
        $recipientEmail .
        "\r\n" .
        'Subject: ' .
        encodeMailHeader($subject) .
        "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n" .
        "\r\n" .
        chunk_split(
            base64_encode($htmlBody)
        );

    $payload = json_encode([
        'raw' => base64UrlEncode($mimeMessage)
    ], JSON_UNESCAPED_SLASHES);

    if ($payload === false) {
        throw new RuntimeException(
            'Failed to encode Gmail request.'
        );
    }

    $response = postRequest(
        'https://gmail.googleapis.com/' .
        'gmail/v1/users/me/messages/send',
        [
            'Authorization: Bearer ' .
                $accessToken,
            'Content-Type: application/json',
            'Accept: application/json'
        ],
        $payload
    );

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300
    ) {
        throw new RuntimeException(
            'Gmail API returned HTTP ' .
            $response['http_code'] .
            ': ' .
            $response['body']
        );
    }
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

    $deleteStmt->bind_param(
        's',
        $email
    );

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

    sendOtpWithGmailApi(
        $email,
        $otp
    );

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