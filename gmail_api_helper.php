<?php

declare(strict_types=1);

require_once __DIR__ . '/smtp_config.php';

/**
 * Sends an HTTPS POST request and returns the HTTP status and body.
 */
function gmailPostRequest(
    string $url,
    array $headers,
    string $body
): array {
    $curl = curl_init($url);

    if ($curl === false) {
        throw new RuntimeException('Failed to initialize HTTP request.');
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
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

    curl_close($curl);

    if ($responseBody === false) {
        throw new RuntimeException(
            $curlError !== '' ? $curlError : 'HTTP request failed.'
        );
    }

    return [
        'http_code' => $httpCode,
        'body' => $responseBody
    ];
}

/**
 * Exchanges the stored Google refresh token for a temporary access token.
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

    $response = gmailPostRequest(
        'https://oauth2.googleapis.com/token',
        [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ],
        $body
    );

    $decoded = json_decode($response['body'], true);

    if (
        $response['http_code'] < 200 ||
        $response['http_code'] >= 300
    ) {
        $googleMessage = is_array($decoded)
            ? ($decoded['error_description']
                ?? $decoded['error']
                ?? $response['body'])
            : $response['body'];

        throw new RuntimeException(
            'Google token request failed: ' . $googleMessage
        );
    }

    $accessToken = is_array($decoded)
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
function gmailBase64UrlEncode(string $value): string
{
    return rtrim(
        strtr(base64_encode($value), '+/', '-_'),
        '='
    );
}

/**
 * Encodes an email header safely as UTF-8.
 */
function encodeGmailHeader(string $value): string
{
    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

/**
 * Sends an HTML email through the Gmail API.
 */
function sendGmailMessage(
    string $recipientEmail,
    string $subject,
    string $htmlBody
): void {
    if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException(
            'Invalid recipient email address.'
        );
    }

    $accessToken = getGoogleAccessToken();
    $fromName = encodeGmailHeader(GMAIL_FROM_NAME);

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
        encodeGmailHeader($subject) .
        "\r\n" .
        "MIME-Version: 1.0\r\n" .
        "Content-Type: text/html; charset=UTF-8\r\n" .
        "Content-Transfer-Encoding: base64\r\n" .
        "\r\n" .
        chunk_split(base64_encode($htmlBody));

    $payload = json_encode(
        ['raw' => gmailBase64UrlEncode($mimeMessage)],
        JSON_UNESCAPED_SLASHES
    );

    if ($payload === false) {
        throw new RuntimeException(
            'Failed to encode Gmail request.'
        );
    }

    $response = gmailPostRequest(
        'https://gmail.googleapis.com/gmail/v1/users/me/messages/send',
        [
            'Authorization: Bearer ' . $accessToken,
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
