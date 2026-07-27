<?php
header("Content-Type: application/json");

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/gmail_api_helper.php';

$user_id = intval($_POST['user_id'] ?? 0);

$checkUser = $conn->prepare("
    SELECT id, email
    FROM users
    WHERE id = ?
    LIMIT 1
");

$checkUser->bind_param("i", $user_id);
$checkUser->execute();

$userResult = $checkUser->get_result();

if ($userResult->num_rows == 0) {
    echo json_encode([
        "success" => false,
        "message" => "User not found."
    ]);
    exit;
}

$user = $userResult->fetch_assoc();
$recipientEmail = trim($user['email'] ?? '');

$donor_name = trim($_POST['donor_name'] ?? '');
$pet_name = trim($_POST['pet_name'] ?? '');
$amount = $_POST['amount'] ?? '';
$reference_number = trim($_POST['reference_number'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'GCash');

if (
    empty($donor_name) ||
    empty($amount) ||
    empty($reference_number)
) {
    echo json_encode([
        "success" => false,
        "message" => "Please complete all required fields."
    ]);
    exit;
}

if (
    !is_numeric($amount) ||
    (float) $amount < 1
) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid donation amount."
    ]);
    exit;
}

// Check duplicate reference number
$check = $conn->prepare(
    "SELECT id FROM donations WHERE reference_number = ?"
);

$check->bind_param("s", $reference_number);
$check->execute();

if ($check->get_result()->num_rows > 0) {
    echo json_encode([
        "success" => false,
        "message" => "This reference number has already been submitted."
    ]);
    exit;
}

// ==============================
// Upload receipt (optional)
// ==============================

$receiptFilename = null;

if (
    isset($_FILES["receipt"]) &&
    $_FILES["receipt"]["error"] === UPLOAD_ERR_OK
) {
    $uploadDir = __DIR__ . "/donation_receipts/";

    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = strtolower(
        pathinfo(
            $_FILES["receipt"]["name"],
            PATHINFO_EXTENSION
        )
    );

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($extension, $allowedExtensions, true)) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid receipt image type."
        ]);
        exit;
    }

    $receiptFilename = uniqid("receipt_", true) . "." . $extension;

    if (!move_uploaded_file(
        $_FILES["receipt"]["tmp_name"],
        $uploadDir . $receiptFilename
    )) {
        echo json_encode([
            "success" => false,
            "message" => "Unable to upload receipt."
        ]);
        exit;
    }
}

// Save donation
$payment_status = "Successful";

$stmt = $conn->prepare("
    INSERT INTO donations
    (
        user_id,
        donor_name,
        pet_name,
        amount,
        reference_number,
        payment_method,
        receipt_image,
        donation_date,
        payment_status
    )
    VALUES
    (
        ?, ?, ?, ?, ?, ?, ?, NOW(), ?
    )
");

$stmt->bind_param(
    "issdssss",
    $user_id,
    $donor_name,
    $pet_name,
    $amount,
    $reference_number,
    $payment_method,
    $receiptFilename,
    $payment_status
);

if ($stmt->execute()) {
    $emailSent = false;

    if (
        $recipientEmail !== '' &&
        filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)
    ) {
        try {
            $safeDonorName = htmlspecialchars(
                $donor_name,
                ENT_QUOTES,
                'UTF-8'
            );

            $safePaymentMethod = htmlspecialchars(
                $payment_method,
                ENT_QUOTES,
                'UTF-8'
            );

            $safeReference = htmlspecialchars(
                $reference_number,
                ENT_QUOTES,
                'UTF-8'
            );

            $formattedAmount = number_format(
                (float) $amount,
                2
            );

            $formattedDate = date(
                'F d, Y h:i A'
            );

            $htmlBody =
                '<!DOCTYPE html>' .
                '<html>' .
                '<body style="' .
                    'margin:0;' .
                    'background:#f8fafc;' .
                    'font-family:Arial,sans-serif;' .
                    'color:#1f2937;' .
                '">' .
                '<div style="' .
                    'max-width:620px;' .
                    'margin:30px auto;' .
                    'background:#ffffff;' .
                    'border-radius:16px;' .
                    'overflow:hidden;' .
                    'box-shadow:0 8px 28px rgba(15,23,42,.10);' .
                '">' .
                '<div style="' .
                    'background:#F2867E;' .
                    'color:#ffffff;' .
                    'padding:28px;' .
                    'text-align:center;' .
                '">' .
                '<h1 style="margin:0;font-size:26px;">Thank You for Supporting AniPet</h1>' .
                '</div>' .
                '<div style="padding:30px;">' .
                '<p>Dear <strong>' . $safeDonorName . '</strong>,</p>' .
                '<p>Thank you for your generous donation to AniPet.</p>' .
                '<p>Your support helps provide rescued animals with food, shelter, ' .
                'medical treatment, vaccinations, and daily care while they wait ' .
                'for their forever homes.</p>' .
                '<div style="' .
                    'margin:24px 0;' .
                    'padding:20px;' .
                    'background:#fff7f5;' .
                    'border:1px solid #fbd5d1;' .
                    'border-radius:12px;' .
                '">' .
                '<h2 style="margin-top:0;font-size:18px;color:#d76f68;">Donation Details</h2>' .
                '<p><strong>Amount:</strong> ₱' . $formattedAmount . '</p>' .
                '<p><strong>Payment Method:</strong> ' . $safePaymentMethod . '</p>' .
                '<p><strong>Reference Number:</strong> ' . $safeReference . '</p>' .
                '<p><strong>Payment Status:</strong> Successful</p>' .
                '<p style="margin-bottom:0;"><strong>Date:</strong> ' . $formattedDate . '</p>' .
                '</div>' .
                '<p>Every contribution makes a meaningful difference.</p>' .
                '<p>With gratitude,<br><strong>The AniPet Team</strong></p>' .
                '</div>' .
                '</div>' .
                '</body>' .
                '</html>';

            sendGmailMessage(
                $recipientEmail,
                'Thank You for Supporting AniPet',
                $htmlBody
            );

            $emailSent = true;

        } catch (Throwable $emailException) {
            error_log(
                'Donation email failed for donation ID ' .
                $stmt->insert_id .
                ': ' .
                $emailException->getMessage()
            );
        }
    }

    echo json_encode([
        "success" => true,
        "message" => "Donation submitted successfully.",
        "receipt_filename" => $receiptFilename,
        "payment_status" => $payment_status,
        "thank_you_email_sent" => $emailSent
    ]);

} else {
    error_log(
        'submit_donation.php insert error: ' .
        $stmt->error
    );

    echo json_encode([
        "success" => false,
        "message" => "Unable to save donation."
    ]);
}

$stmt->close();
$check->close();
$checkUser->close();
$conn->close();
