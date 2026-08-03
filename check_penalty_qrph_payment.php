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

$paymentIntentId = trim(
    $input["payment_intent_id"] ?? ""
);

if ($paymentIntentId === "") {
    http_response_code(400);

    echo json_encode([
        "success" => false,
        "message" => "Payment Intent ID is required."
    ]);
    exit;
}

/*
 * Load the locally saved penalty-payment record.
 */
$stmt = $conn->prepare("
    SELECT
        pp.id,
        pp.pet_pound_id,
        pp.owner_id,
        pp.payer_name,
        pp.amount,
        pp.reference_number,
        pp.payment_intent_id,
        pp.payment_method_id,
        pp.payment_method,
        pp.payment_status,
        pp.payment_date,
        p.impound_date
    FROM pet_penalty_payments pp
    INNER JOIN pet_pound p
        ON p.id = pp.pet_pound_id
    WHERE pp.payment_intent_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "s",
    $paymentIntentId
);

$stmt->execute();

$paymentRecord = $stmt
    ->get_result()
    ->fetch_assoc();

$stmt->close();

if (!$paymentRecord) {
    http_response_code(404);

    echo json_encode([
        "success" => false,
        "message" => "Penalty payment record was not found."
    ]);
    exit;
}

/*
 * Return immediately if this payment has already been finalized.
 * This prevents duplicate updates during repeated polling.
 */
if (
    strtolower($paymentRecord["payment_status"]) === "succeeded"
    && !empty($paymentRecord["payment_date"])
) {
    echo json_encode([
        "success" => true,
        "paid" => true,
        "status" => "succeeded",
        "message" => "This penalty payment is already marked as paid.",
        "payment_record_id" => (int)$paymentRecord["id"],
        "pet_pound_id" => (int)$paymentRecord["pet_pound_id"],
        "payment_intent_id" => $paymentIntentId,
        "reference_number" =>
            $paymentRecord["reference_number"]
                ?: $paymentIntentId,
        "payer_name" => $paymentRecord["payer_name"],
        "amount" => (float)$paymentRecord["amount"],
        "payment_method" => $paymentRecord["payment_method"],
        "payment_date" => $paymentRecord["payment_date"]
    ]);

    $conn->close();
    exit;
}

/*
 * Ask PayMongo for the latest Payment Intent status.
 */
$url =
    "https://api.paymongo.com/v1/payment_intents/"
    . urlencode($paymentIntentId);

$curl = curl_init($url);

curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPGET => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Basic "
            . base64_encode($secretKey . ":"),
        "Accept: application/json"
    ],
    CURLOPT_TIMEOUT => 30
]);

$responseBody = curl_exec($curl);
$httpCode = curl_getinfo(
    $curl,
    CURLINFO_HTTP_CODE
);
$curlError = curl_error($curl);

curl_close($curl);

if (
    $responseBody === false
    || $curlError !== ""
) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" =>
            "Unable to connect to PayMongo: "
            . $curlError
    ]);

    $conn->close();
    exit;
}

$paymongoResponse = json_decode(
    $responseBody,
    true
);

if (
    $httpCode < 200
    || $httpCode >= 300
) {
    http_response_code(502);

    echo json_encode([
        "success" => false,
        "message" =>
            "Failed to retrieve the PayMongo payment.",
        "paymongo_error" => $paymongoResponse
    ]);

    $conn->close();
    exit;
}

$attributes =
    $paymongoResponse["data"]["attributes"]
    ?? [];

$paymentStatus =
    strtolower(
        trim(
            $attributes["status"]
            ?? "unknown"
        )
    );

/*
 * Keep the local status synchronized even while payment is pending.
 */
if ($paymentStatus !== "succeeded") {
    $updatePending = $conn->prepare("
        UPDATE pet_penalty_payments
        SET payment_status = ?
        WHERE id = ?
    ");

    $paymentRecordId =
        (int)$paymentRecord["id"];

    $updatePending->bind_param(
        "si",
        $paymentStatus,
        $paymentRecordId
    );

    $updatePending->execute();
    $updatePending->close();

    echo json_encode([
        "success" => true,
        "paid" => false,
        "status" => $paymentStatus,
        "message" =>
            "The QR Ph payment is still awaiting completion.",
        "payment_record_id" =>
            $paymentRecordId,
        "pet_pound_id" =>
            (int)$paymentRecord["pet_pound_id"],
        "payment_intent_id" =>
            $paymentIntentId,
        "payer_name" =>
            $paymentRecord["payer_name"],
        "amount" =>
            (float)$paymentRecord["amount"],
        "payment_method" =>
            $paymentRecord["payment_method"]
    ]);

    $conn->close();
    exit;
}

/*
 * PayMongo has confirmed that the payment succeeded.
 *
 * We use the Payment Intent ID as the guaranteed unique reference.
 * If PayMongo exposes a payment ID, use that as the visible reference.
 */
$referenceNumber = $paymentIntentId;

$payments =
    $attributes["payments"]
    ?? [];

if (
    is_array($payments)
    && !empty($payments)
    && !empty($payments[0]["id"])
) {
    $referenceNumber =
        $payments[0]["id"];
}

$paymentRecordId =
    (int)$paymentRecord["id"];

$petPoundId =
    (int)$paymentRecord["pet_pound_id"];

try {
    $conn->begin_transaction();

    /*
     * Complete the payment-history record.
     */
    $updatePayment = $conn->prepare("
        UPDATE pet_penalty_payments
        SET
            reference_number = ?,
            payment_status = 'succeeded',
            payment_method = 'QR Ph',
            payment_date = NOW()
        WHERE id = ?
          AND payment_status <> 'succeeded'
    ");

    $updatePayment->bind_param(
        "si",
        $referenceNumber,
        $paymentRecordId
    );

    if (!$updatePayment->execute()) {
        throw new Exception(
            "Unable to update the penalty payment."
        );
    }

    $updatePayment->close();

    /*
     * Mark the linked impounded-pet record as paid.
     */
    $updatePound = $conn->prepare("
        UPDATE pet_pound
        SET
            payment_status = 'Paid',
            payment_reference = ?,
            payment_date = NOW(),
            status = 'Paid'
        WHERE id = ?
    ");

    $updatePound->bind_param(
        "si",
        $referenceNumber,
        $petPoundId
    );

    if (!$updatePound->execute()) {
        throw new Exception(
            "Unable to update the impoundment record."
        );
    }

    $updatePound->close();

    $conn->commit();

} catch (Throwable $e) {
    $conn->rollback();

    http_response_code(500);

    echo json_encode([
        "success" => false,
        "message" =>
            "Payment was confirmed, but the database could not be updated: "
            . $e->getMessage()
    ]);

    $conn->close();
    exit;
}

/*
 * Read the final saved payment date for display.
 */
$final = $conn->prepare("
    SELECT payment_date
    FROM pet_penalty_payments
    WHERE id = ?
    LIMIT 1
");

$final->bind_param(
    "i",
    $paymentRecordId
);

$final->execute();

$finalRow = $final
    ->get_result()
    ->fetch_assoc();

$final->close();

echo json_encode([
    "success" => true,
    "paid" => true,
    "status" => "succeeded",
    "message" =>
        "Penalty payment completed successfully.",
    "payment_record_id" =>
        $paymentRecordId,
    "pet_pound_id" =>
        $petPoundId,
    "payment_intent_id" =>
        $paymentIntentId,
    "reference_number" =>
        $referenceNumber,
    "payer_name" =>
        $paymentRecord["payer_name"],
    "amount" =>
        (float)$paymentRecord["amount"],
    "payment_method" =>
        "QR Ph",
    "payment_date" =>
        $finalRow["payment_date"]
        ?? null
]);

$conn->close();