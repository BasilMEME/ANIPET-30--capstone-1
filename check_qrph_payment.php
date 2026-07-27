<?php

header("Content-Type: application/json");

$secretKey = getenv("PAYMONGO_SECRET_KEY");

if (!$secretKey) {
    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" => "PayMongo Secret Key not found."
    ]);
    exit;
}

$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

if (!is_array($input)) {
    $input = [];
}

$paymentIntentId = trim($input["payment_intent_id"] ?? "");

if ($paymentIntentId === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Payment Intent ID is required."
    ]);
    exit;
}

$url = "https://api.paymongo.com/v1/payment_intents/"
    . urlencode($paymentIntentId);

$curl = curl_init($url);

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Basic " . base64_encode($secretKey . ":"),
        "Accept: application/json"
    ],
    CURLOPT_TIMEOUT => 30
]);

$responseBody = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);

curl_close($curl);

if ($responseBody === false || $curlError !== "") {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Unable to connect to PayMongo: " . $curlError
    ]);
    exit;
}

$response = json_decode($responseBody, true);

if ($httpCode < 200 || $httpCode >= 300) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" => "Failed to retrieve the PayMongo payment.",
        "paymongo_error" => $response
    ]);
    exit;
}

$attributes = $response["data"]["attributes"] ?? [];
$status = $attributes["status"] ?? "unknown";

echo json_encode([
    "success" => true,
    "payment_intent_id" => $paymentIntentId,
    "status" => $status,
    "paid" => $status === "succeeded"
]);