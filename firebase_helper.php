<?php

function base64UrlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function getFirebaseAccessToken(): ?string
{
    $projectId = getenv("FIREBASE_PROJECT_ID");
    $clientEmail = getenv("FIREBASE_CLIENT_EMAIL");
    $privateKey = getenv("FIREBASE_PRIVATE_KEY");

    if (!$projectId || !$clientEmail || !$privateKey) {
        error_log("Firebase environment variables are missing.");
        return null;
    }

    // Convert literal \n into real line breaks
    $privateKey = str_replace("\\n", "\n", $privateKey);

    $header = [
        "alg" => "RS256",
        "typ" => "JWT"
    ];

    $now = time();

    $claims = [
        "iss" => $clientEmail,
        "scope" => "https://www.googleapis.com/auth/firebase.messaging",
        "aud" => "https://oauth2.googleapis.com/token",
        "iat" => $now,
        "exp" => $now + 3600
    ];

    $jwtHeader = base64UrlEncode(json_encode($header));
    $jwtClaims = base64UrlEncode(json_encode($claims));

    $signatureInput = $jwtHeader . "." . $jwtClaims;

    openssl_sign(
        $signatureInput,
        $signature,
        $privateKey,
        OPENSSL_ALGO_SHA256
    );

    $jwt = $signatureInput . "." . base64UrlEncode($signature);

    $postFields = http_build_query([
        "grant_type" => "urn:ietf:params:oauth:grant-type:jwt-bearer",
        "assertion" => $jwt
    ]);

    $ch = curl_init("https://oauth2.googleapis.com/token");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postFields
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log(curl_error($ch));
        curl_close($ch);
        return null;
    }

    curl_close($ch);

    $json = json_decode($response, true);

    return $json["access_token"] ?? null;
}

function sendFirebaseNotification(
    string $fcmToken,
    string $title,
    string $message,
    array $data = []
): bool {
    $projectId = getenv("FIREBASE_PROJECT_ID");

    if (!$projectId) {
        error_log("FIREBASE_PROJECT_ID is missing.");
        return false;
    }

    if (trim($fcmToken) === "") {
        error_log("FCM token is empty.");
        return false;
    }

    $accessToken = getFirebaseAccessToken();

    if (!$accessToken) {
        error_log("Could not generate Firebase access token.");
        return false;
    }

    // Firebase data values must all be strings.
    $stringData = [];

    foreach ($data as $key => $value) {
        $stringData[(string)$key] = (string)$value;
    }

    $payload = [
        "message" => [
            "token" => $fcmToken,

            "notification" => [
                "title" => $title,
                "body" => $message
            ],

            "data" => $stringData,

            "android" => [
                "priority" => "high",
                "notification" => [
                    "sound" => "default",
                    "channel_id" => "anipet_notifications"
                ]
            ]
        ]
    ];

    $url =
        "https://fcm.googleapis.com/v1/projects/"
        . rawurlencode($projectId)
        . "/messages:send";

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer " . $accessToken,
            "Content-Type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($response === false) {
        error_log(
            "Firebase request failed: " . curl_error($ch)
        );

        curl_close($ch);
        return false;
    }

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            "Firebase returned HTTP {$httpCode}: {$response}"
        );

        return false;
    }

    error_log("Firebase notification sent successfully: {$response}");

    return true;
}