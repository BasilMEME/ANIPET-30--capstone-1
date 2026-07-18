<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
SELECT *
FROM pet_pound
WHERE id=?
");
$stmt->bind_param("i", $id);
$stmt->execute();

$pet = $stmt->get_result()->fetch_assoc();

if(!$pet){
    die("Pet not found.");
}

/* ===========================
   SAVE PAYMENT (AJAX)
=========================== */

if(isset($_POST['save'])){

    $reference = trim($_POST['reference_number'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $payerName = trim($_POST['payer_name'] ?? '');

    if($payerName === ''){
        die("Payer name is required.");
    }

    if($amount <= 0){
        die("A valid payment amount is required.");
    }

    if(empty($_FILES['receipt_photo']) || $_FILES['receipt_photo']['error'] !== UPLOAD_ERR_OK){
        die("Receipt photo is required.");
    }

    /* Handle receipt photo upload */

    $uploadDir = __DIR__ . "/../images/payment_receipts/";

    if(!is_dir($uploadDir)){
        mkdir($uploadDir, 0755, true);
    }

    $ext = strtolower(pathinfo($_FILES['receipt_photo']['name'], PATHINFO_EXTENSION));
    $allowedExt = ["jpg", "jpeg", "png", "webp"];

    if(!in_array($ext, $allowedExt, true)){
        die("Invalid receipt photo format. Allowed: jpg, jpeg, png, webp.");
    }

    $receiptFilename = "receipt_" . $id . "_" . time() . "." . $ext;
    $destination = $uploadDir . $receiptFilename;

    if(!move_uploaded_file($_FILES['receipt_photo']['tmp_name'], $destination)){
        die("Failed to upload receipt photo.");
    }

    // The payment date is never taken from client input — it's the moment the
    // payment is recorded as complete on the server, per shelter policy.
    $paymentDate = date('Y-m-d H:i:s');

    $insert = $conn->prepare("
        INSERT INTO pet_penalty_payments
        (
            pet_pound_id,
            owner_id,
            amount,
            payer_name,
            reference_number,
            receipt_photo,
            payment_date
        )
        VALUES
        (
            ?, ?, ?, ?, ?, ?, ?
        )
    ");

    if(!$insert){
        die("Prepare failed (insert): " . $conn->error);
    }

    $ownerId = !empty($pet['owner_id']) ? $pet['owner_id'] : NULL;

    $insert->bind_param(
        "iidssss",
        $id,
        $ownerId,
        $amount,
        $payerName,
        $reference,
        $receiptFilename,
        $paymentDate
    );

    if(!$insert->execute()){
        die("Insert Error: ".$insert->error);
    }

    $status = 'Paid';

    $update = $conn->prepare("
        UPDATE pet_pound
        SET
            status=?,
            payment_status='Paid',
            payment_reference=?,
            payment_date=?
        WHERE id=?
    ");

    if(!$update){
        die("Prepare failed (update): " . $conn->error);
    }

    $update->bind_param(
        "sssi",
        $status,
        $reference,
        $paymentDate,
        $id
    );

    if(!$update->execute()){
        die("Update Error: ".$update->error);
    }

    echo "success";
    exit;

}
?>

<div class="info-grid">

    <div class="info-item">
        <label>Pet</label>
        <span><?= htmlspecialchars($pet['pet_name']) ?></span>
    </div>

    <div class="info-item">
        <label>Owner</label>
        <span><?= htmlspecialchars($pet['owner_name']) ?></span>
    </div>

    <div class="info-item">
        <label>Penalty</label>
        <span>₱<?= number_format($pet['penalty_amount'],2) ?></span>
    </div>

</div>

<div class="divider"></div>

<?php if($pet['payment_status'] === 'Paid'): ?>

    <p style="color:var(--muted);">
        This penalty was already marked Paid on
        <?= $pet['payment_date'] ? date("M d, Y g:i A", strtotime($pet['payment_date'])) : '—' ?>.
        Recording another payment below will add an additional payment record.
    </p>

    <div class="divider"></div>

<?php endif; ?>

<form id="paymentForm" enctype="multipart/form-data">

    <div class="form-group">

        <label class="form-label">Name of Payer</label>

        <input
            type="text"
            name="payer_name"
            class="form-control"
            required>

    </div>

    <div class="form-group">

        <label class="form-label">Amount Paid</label>

        <input
            type="number"
            name="amount"
            step="0.01"
            value="<?= $pet['penalty_amount'] ?>"
            class="form-control"
            required>

    </div>

    <div class="form-group">

        <label class="form-label">Reference Number</label>

        <input
            type="text"
            name="reference_number"
            class="form-control"
            required>

    </div>

    <div class="form-group">
        <label class="form-label">Date</label>
        <input type="text" class="form-control" value="Recorded automatically once payment is saved" disabled>
    </div>

    <div class="form-group">

        <label class="form-label">Photo of Receipt</label>

        <input
            type="file"
            name="receipt_photo"
            class="form-control"
            accept="image/*"
            required>

    </div>

    <button
        type="button"
        class="btn btn-primary"
        onclick="savePayment()">

        Save Payment

    </button>

</form>
