<?php

header("Content-Type: application/json");

require_once __DIR__ . "/db_connect.php";

$secretKey = getenv("PAYMONGO_SECRET_KEY");

if (!$secretKey) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "PayMongo Secret Key was not found."
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true);

if (!is_array($input)) {
    $input = [];
}

$petPoundId = intval($input["pet_pound_id"] ?? 0);

if ($petPoundId <= 0) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "A valid impounded pet ID is required."
    ]);
    exit;
}

/*
 * Load all payment details from the database.
 * The frontend is not allowed to send or change the amount.
 */
$stmt = $conn->prepare("
    SELECT
        id,
        owner_id,
        owner_name,
        penalty_amount,
        payment_status,
        payment_reference,
        payment_date,
        impound_date
    FROM pet_pound
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $petPoundId);
$stmt->execute();

$petPound = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$petPound) {
    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Impounded pet record was not found."
    ]);
    exit;
}

if (strcasecmp($petPound["payment_status"], "Paid") === 0) {
    echo json_encode([
        "success" => false,
        "message" => "This penalty has already been paid.",
        "payment_status" => "paid",
        "reference_number" => $petPound["payment_reference"],
        "payment_date" => $petPound["payment_date"]
    ]);
    exit;
}

$amount = (float)$petPound["penalty_amount"];

if ($amount < 1) {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "The penalty amount must be at least ₱1.00."
    ]);
    exit;
}

/*
 * Return an existing active QR instead of creating duplicate
 * PayMongo Payment Intents whenever the modal is reopened.
 */
$existing = $conn->prepare("
    SELECT
        id,
        payment_intent_id,
        payment_method_id,
        qr_image_url,
        payment_status,
        amount
    FROM pet_penalty_payments
    WHERE pet_pound_id = ?
      AND payment_status IN ('pending', 'awaiting_next_action', 'processing')
    ORDER BY id DESC
    LIMIT 1
");

$existing->bind_param("i", $petPoundId);
$existing->execute();

$existingPayment = $existing->get_result()->fetch_assoc();
$existing->close();

if (
    $existingPayment &&
    !empty($existingPayment["payment_intent_id"]) &&
    !empty($existingPayment["qr_image_url"])
) {
    echo json_encode([
        "success" => true,
        "message" => "Existing QR Ph payment loaded.",
        "payment_record_id" => (int)$existingPayment["id"],
        "pet_pound_id" => $petPoundId,
        "amount" => (float)$existingPayment["amount"],
        "payer_name" => $petPound["owner_name"] ?: "Unknown",
        "payment_intent_id" => $existingPayment["payment_intent_id"],
        "payment_method_id" => $existingPayment["payment_method_id"],
        "qr_image_url" => $existingPayment["qr_image_url"],
        "status" => $existingPayment["payment_status"]
    ]);
    exit;
}

function penaltyPaymongoRequest(
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

$amountInCentavos = (int)round($amount * 100);

$paymentIntentPayload = [
    "data" => [
        "attributes" => [
            "amount" => $amountInCentavos,
            "payment_method_allowed" => ["qrph"],
            "currency" => "PHP",
            "capture_type" => "automatic",
            "description" =>
                "AniPet penalty payment for impoundment #" . $petPoundId
        ]
    ]
];

$paymentIntentResponse = penaltyPaymongoRequest(
    "https://api.paymongo.com/v1/payment_intents",
    $secretKey,
    $paymentIntentPayload
);

if (!$paymentIntentResponse["success"]) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create the PayMongo Payment Intent.",
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

$paymentMethodResponse = penaltyPaymongoRequest(
    "https://api.paymongo.com/v1/payment_methods",
    $secretKey,
    [
        "data" => [
            "attributes" => [
                "type" => "qrph"
            ]
        ]
    ]
);

if (!$paymentMethodResponse["success"]) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Failed to create the QR Ph payment method.",
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

$attachResponse = penaltyPaymongoRequest(
    "https://api.paymongo.com/v1/payment_intents/"
        . urlencode($paymentIntentId)
        . "/attach",
    $secretKey,
    [
        "data" => [
            "attributes" => [
                "payment_method" => $paymentMethodId
            ]
        ]
    ]
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

$attributes =
    $attachResponse["body"]["data"]["attributes"] ?? [];

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
        "status" => $paymentStatus
    ]);
    exit;
}

$payerName = trim($petPound["owner_name"] ?? "");

if ($payerName === "") {
    $payerName = "Unknown";
}

$ownerId = !empty($petPound["owner_id"])
    ? (int)$petPound["owner_id"]
    : null;

$insert = $conn->prepare("
    INSERT INTO pet_penalty_payments
    (
        pet_pound_id,
        owner_id,
        payer_name,
        amount,
        reference_number,
        payment_intent_id,
        payment_method_id,
        qr_image_url,
        payment_method,
        payment_status,
        receipt_photo,
        payment_date
    )
    VALUES (?, ?, ?, ?, NULL, ?, ?, ?, 'QR Ph', ?, NULL, NULL)
");

$insert->bind_param(
    "iisdssss",
    $petPoundId,
    $ownerId,
    $payerName,
    $amount,
    $paymentIntentId,
    $paymentMethodId,
    $qrImageUrl,
    $paymentStatus
);

if (!$insert->execute()) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "The QR was generated, but the payment record could not be saved.",
        "database_error" => $insert->error
    ]);

    $insert->close();
    exit;
}

$paymentRecordId = $insert->insert_id;
$insert->close();

echo json_encode([
    "success" => true,
    "message" => "QR Ph penalty payment created successfully.",
    "payment_record_id" => $paymentRecordId,
    "pet_pound_id" => $petPoundId,
    "payer_name" => $payerName,
    "amount" => $amount,
    "payment_method" => "QR Ph",
    "payment_intent_id" => $paymentIntentId,
    "payment_method_id" => $paymentMethodId,
    "qr_image_url" => $qrImageUrl,
    "status" => $paymentStatus,
    "impound_date" => $petPound["impound_date"]
]);

$conn->close();