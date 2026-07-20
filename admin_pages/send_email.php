<?php

declare(strict_types=1);

require_once __DIR__ . '/../smtp_config.php';

if (!function_exists('gmailRequest')) {
    function gmailRequest(
        string $url,
        array $headers,
        string $body
    ): array {
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'Failed to initialize cURL.'
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
                    : 'Gmail request failed.'
            );
        }

        return [
            'http_code' => $httpCode,
            'body' => $responseBody
        ];
    }
}

if (!function_exists('getGmailAccessToken')) {
    function getGmailAccessToken(): string
    {
        if (
            GOOGLE_CLIENT_ID === '' ||
            GOOGLE_CLIENT_SECRET === '' ||
            GOOGLE_REFRESH_TOKEN === ''
        ) {
            throw new RuntimeException(
                'Google OAuth credentials are missing.'
            );
        }

        $requestBody = http_build_query([
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'refresh_token' => GOOGLE_REFRESH_TOKEN,
            'grant_type' => 'refresh_token'
        ]);

        $response = gmailRequest(
            'https://oauth2.googleapis.com/token',
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ],
            $requestBody
        );

        $decoded = json_decode(
            $response['body'],
            true
        );

        if (
            $response['http_code'] < 200 ||
            $response['http_code'] >= 300
        ) {
            $errorMessage =
                is_array($decoded)
                    ? (
                        $decoded['error_description']
                        ?? $decoded['error']
                        ?? $response['body']
                    )
                    : $response['body'];

            throw new RuntimeException(
                'Google token request failed: ' .
                $errorMessage
            );
        }

        $accessToken =
            is_array($decoded)
                ? ($decoded['access_token'] ?? '')
                : '';

        if ($accessToken === '') {
            throw new RuntimeException(
                'Google access token was not returned.'
            );
        }

        return $accessToken;
    }
}

if (!function_exists('gmailBase64UrlEncode')) {
    function gmailBase64UrlEncode(
        string $value
    ): string {
        return rtrim(
            strtr(
                base64_encode($value),
                '+/',
                '-_'
            ),
            '='
        );
    }
}

if (!function_exists('gmailEncodeHeader')) {
    function gmailEncodeHeader(
        string $value
    ): string {
        return '=?UTF-8?B?' .
            base64_encode($value) .
            '?=';
    }
}

if (!function_exists('sendEmail')) {
    function sendEmail(
        string $to,
        string $subject,
        string $message,
        bool $isHtml = true
    ): array {
        try {
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'Invalid recipient email: ' . $to
                );
            }

            $accessToken = getGmailAccessToken();

            $contentType = $isHtml
                ? 'text/html'
                : 'text/plain';

            $fromName = gmailEncodeHeader(
                GMAIL_FROM_NAME
            );

            $rawMessage =
                'From: ' .
                $fromName .
                ' <' .
                GMAIL_FROM_EMAIL .
                ">\r\n" .
                'To: ' .
                $to .
                "\r\n" .
                'Subject: ' .
                gmailEncodeHeader($subject) .
                "\r\n" .
                "MIME-Version: 1.0\r\n" .
                'Content-Type: ' .
                $contentType .
                "; charset=UTF-8\r\n" .
                "Content-Transfer-Encoding: base64\r\n" .
                "\r\n" .
                chunk_split(
                    base64_encode($message)
                );

            $payload = json_encode([
                'raw' => gmailBase64UrlEncode(
                    $rawMessage
                )
            ]);

            if ($payload === false) {
                throw new RuntimeException(
                    'Failed to create Gmail payload.'
                );
            }

            $response = gmailRequest(
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
                    'Gmail API error HTTP ' .
                    $response['http_code'] .
                    ': ' .
                    $response['body']
                );
            }

            return [
                'success' => true,
                'message' => 'Email sent successfully.'
            ];
        } catch (Throwable $exception) {
            error_log(
                'Gmail send error: ' .
                $exception->getMessage()
            );

            return [
                'success' => false,
                'message' => $exception->getMessage()
            ];
        }
    }
}