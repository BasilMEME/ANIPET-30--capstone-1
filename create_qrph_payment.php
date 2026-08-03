<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db_connect.php";
require_once __DIR__ . "/vendor/autoload.php";

// Read the PayMongo Secret Key from Railway
$secretKey = getenv("PAYMONGO_SECRET_KEY");

if (!$secretKey) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "PayMongo Secret Key not found."
    ]);
    exit;
}

// Read JSON data sent by Android
$input = json_decode(file_get_contents("php://input"), true);

$user_id    = intval($input["user_id"] ?? 0);
$donor_name = trim($input["donor_name"] ?? "");
$pet_name   = trim($input["pet_name"] ?? "");
$amount     = floatval($input["amount"] ?? 0);

if ($user_id <= 0 || $donor_name === "" || $amount < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "User, donor name, and an amount of at least ₱1.00 are required."
    ]);
    exit;
}

// PayMongo uses centavos.
// Example: ₱100.00 becomes 10000.
$amountInCentavos = (int) round($amount * 100);

if ($amountInCentavos < 100) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Minimum donation amount is ₱1.00."
    ]);
    exit;
}

// Reusable function for sending requests to PayMongo
function paymongoRequest(
    string $url,
    string $secretKey,
    array $payload
): array {
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Basic " . base64_encode($secretKey . ":"),
            "Content-Type: application/json",
            "Accept: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30
    ]);

    $responseBody = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);

    curl_close($curl);

    if ($responseBody === false || $curlError !== "") {
        return [
            "success" => false,
            "http_code" => $httpCode,
            "message" => "Unable to connect to PayMongo: " . $curlError
        ];
    }

    $decodedResponse = json_decode($responseBody, true);

    return [
        "success" => $httpCode >= 200 && $httpCode < 300,
        "http_code" => $httpCode,
        "body" => $decodedResponse
    ];
}

// Create the Payment Intent
$paymentIntentPayload = [
    "data" => [
        "attributes" => [
            "amount" => $amountInCentavos,
            "payment_method_allowed" => ["qrph"],
            "currency" => "PHP",
            "capture_type" => "automatic",
            "description" => "AniPet donation from " . $donor_name
        ]
    ]
];

$paymentIntentResponse = paymongoRequest(
    "https://api.paymongo.com/v1/payment_intents",
    $secretKey,
    $paymentIntentPayload
);

if (!$paymentIntentResponse["success"]) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create PayMongo payment intent.",
        "paymongo_error" => $paymentIntentResponse["body"] ?? null
    ]);
    exit;
}

$paymentIntentId =
    $paymentIntentResponse["body"]["data"]["id"] ?? null;

if (!$paymentIntentId) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "PayMongo did not return a Payment Intent ID."
    ]);
    exit;
}
// Create a QR Ph Payment Method
$paymentMethodPayload = [
    "data" => [
        "attributes" => [
            "type" => "qrph"
        ]
    ]
];

$paymentMethodResponse = paymongoRequest(
    "https://api.paymongo.com/v1/payment_methods",
    $secretKey,
    $paymentMethodPayload
);

if (!$paymentMethodResponse["success"]) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create QR Ph payment method.",
        "paymongo_error" => $paymentMethodResponse["body"] ?? null
    ]);
    exit;
}

$paymentMethodId =
    $paymentMethodResponse["body"]["data"]["id"] ?? null;

if (!$paymentMethodId) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "PayMongo did not return a Payment Method ID."
    ]);
    exit;
}

// Attach the QR Ph Payment Method to the Payment Intent
$attachPayload = [
    "data" => [
        "attributes" => [
            "payment_method" => $paymentMethodId
        ]
    ]
];

$attachResponse = paymongoRequest(
    "https://api.paymongo.com/v1/payment_intents/"
        . urlencode($paymentIntentId)
        . "/attach",
    $secretKey,
    $attachPayload
);

if (!$attachResponse["success"]) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Failed to attach the QR Ph payment method.",
        "paymongo_error" => $attachResponse["body"] ?? null
    ]);
    exit;
}

// Read the generated QR image and current status
$attachedData = $attachResponse["body"]["data"] ?? [];
$attributes = $attachedData["attributes"] ?? [];

$qrImageUrl =
    $attributes["next_action"]["code"]["image_url"] ?? null;

$paymentStatus =
    $attributes["status"] ?? "awaiting_next_action";

if (!$qrImageUrl) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "PayMongo did not return a QR Ph image.",
        "payment_intent_id" => $paymentIntentId,
        "status" => $paymentStatus,
        "paymongo_response" => $attachResponse["body"] ?? null
    ]);
    exit;
}

// Return the QR information to Android
echo json_encode([
    "success" => true,
    "message" => "QR Ph payment created successfully.",
    "payment_intent_id" => $paymentIntentId,
    "payment_method_id" => $paymentMethodId,
    "qr_image_url" => $qrImageUrl,
    "status" => $paymentStatus,
    "amount" => $amount,
    "user_id" => $user_id,
    "donor_name" => $donor_name,
    "pet_name" => $pet_name
]);

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}