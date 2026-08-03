<?php

require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_pet_pound');

$petPoundId = intval($_GET['id'] ?? 0);

if ($petPoundId <= 0) {
    echo '<div class="empty-state">Invalid impoundment ID.</div>';
    exit;
}

$stmt = $conn->prepare("
    SELECT
        id,
        owner_name,
        penalty_amount,
        impound_date,
        claim_deadline,
        payment_status,
        payment_reference,
        payment_date,
        status
    FROM pet_pound
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $petPoundId);
$stmt->execute();

$petPound = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$petPound) {
    echo '<div class="empty-state">Impoundment record was not found.</div>';
    exit;
}

$ownerName = trim($petPound['owner_name'] ?? '');

if ($ownerName === '') {
    $ownerName = 'Unknown';
}

$paymentStatus = strtolower(
    trim($petPound['payment_status'] ?? 'unpaid')
);

$isPaid = $paymentStatus === 'paid';
?>

<div id="penaltyPaymentPanel">

    <input
        type="hidden"
        id="penaltyPetPoundId"
        value="<?= (int)$petPound['id'] ?>"
    >

    <div
        style="
            padding:16px;
            background:var(--surface-alt);
            border:1px solid var(--border);
            border-radius:10px;
            margin-bottom:16px;
        "
    >
        <div class="form-row cols-2">

            <div class="form-group">
                <label class="form-label">
                    Impoundment ID
                </label>

                <div class="form-control" style="background:#f8fafc;">
                    #<?= (int)$petPound['id'] ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Owner
                </label>

                <div class="form-control" style="background:#f8fafc;">
                    <?= htmlspecialchars($ownerName) ?>
                </div>
            </div>

        </div>

        <div class="form-row cols-2">

            <div class="form-group">
                <label class="form-label">
                    Penalty Amount
                </label>

                <div
                    class="form-control"
                    style="
                        background:#f8fafc;
                        font-weight:700;
                        color:var(--danger);
                    "
                >
                    ₱<?= number_format((float)$petPound['penalty_amount'], 2) ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Payment Method
                </label>

                <div class="form-control" style="background:#f8fafc;">
                    QR Ph
                </div>
            </div>

        </div>

        <div class="form-row cols-2">

            <div class="form-group">
                <label class="form-label">
                    Impounded Date
                </label>

                <div class="form-control" style="background:#f8fafc;">
                    <?= date(
                        "M d, Y g:i A",
                        strtotime($petPound['impound_date'])
                    ) ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">
                    Payment Status
                </label>

                <div class="form-control" style="background:#f8fafc;">
                    <span
                        id="penaltyPaymentStatusText"
                        class="badge <?= $isPaid ? 'badge-approved' : 'badge-pending' ?>"
                    >
                        <?= $isPaid ? 'Paid' : 'Unpaid' ?>
                    </span>
                </div>
            </div>

        </div>
    </div>

    <?php if ($isPaid): ?>

        <div
            style="
                padding:16px;
                border-radius:10px;
                background:#ecfdf5;
                border:1px solid #86efac;
            "
        >
            <h3 style="margin-bottom:10px;color:#166534;">
                Payment Completed
            </h3>

            <p>
                <strong>Reference:</strong>
                <?= htmlspecialchars(
                    $petPound['payment_reference'] ?: '—'
                ) ?>
            </p>

            <p style="margin-top:6px;">
                <strong>Date Paid:</strong>
                <?= !empty($petPound['payment_date'])
                    ? date(
                        "M d, Y g:i A",
                        strtotime($petPound['payment_date'])
                    )
                    : '—'
                ?>
            </p>
        </div>

        <div class="action-row" style="margin-top:16px;">
            <button
                type="button"
                class="btn btn-primary"
                onclick="location.href='?page=penalty_payments'"
            >
                View Penalty Payments
            </button>
        </div>

    <?php else: ?>

        <div id="penaltyQrArea">

            <div
                id="penaltyQrPlaceholder"
                class="empty-state"
                style="padding:24px;"
            >
                <div class="empty-icon">💳</div>

                <p>
                    Generate a secure QR Ph payment code for this penalty.
                </p>
            </div>

            <div
                id="penaltyQrLoading"
                style="
                    display:none;
                    text-align:center;
                    padding:24px;
                "
            >
                <div
                    class="spinner"
                    style="
                        width:38px;
                        height:38px;
                        border:4px solid #e5e7eb;
                        border-top-color:var(--accent);
                        border-radius:50%;
                        margin:0 auto 12px;
                        animation:spin 1s linear infinite;
                    "
                ></div>

                <p>Generating secure QR Ph payment...</p>
            </div>

            <div
                id="penaltyQrResult"
                style="
                    display:none;
                    text-align:center;
                    padding:16px;
                    border:1px solid var(--border);
                    border-radius:10px;
                "
            >
                <h3 style="margin-bottom:8px;">
                    Scan to Pay
                </h3>

                <p
                    style="
                        margin-bottom:12px;
                        color:var(--text-light);
                    "
                >
                    Scan this QR using GCash, Maya, or another QR Ph-supported app.
                </p>

                <img
                    id="penaltyQrImage"
                    src=""
                    alt="Penalty QR Ph"
                    style="
                        width:240px;
                        max-width:100%;
                        background:#fff;
                        padding:10px;
                        border-radius:10px;
                        border:1px solid var(--border);
                    "
                >

                <p
                    style="
                        margin-top:12px;
                        font-weight:700;
                        font-size:1.15rem;
                    "
                >
                    ₱<?= number_format(
                        (float)$petPound['penalty_amount'],
                        2
                    ) ?>
                </p>

                <p
                    id="penaltyPollingStatus"
                    style="
                        margin-top:10px;
                        color:var(--warning);
                        font-weight:600;
                    "
                >
                    Waiting for payment...
                </p>

                <div
                    id="penaltyPaymentReferenceBox"
                    style="
                        display:none;
                        margin-top:12px;
                        padding:12px;
                        background:#ecfdf5;
                        border:1px solid #86efac;
                        border-radius:8px;
                        text-align:left;
                    "
                >
                    <p>
                        <strong>Payment Status:</strong>
                        Paid
                    </p>

                    <p style="margin-top:5px;">
                        <strong>Reference:</strong>
                        <span id="penaltyPaidReference"></span>
                    </p>

                    <p style="margin-top:5px;">
                        <strong>Date Paid:</strong>
                        <span id="penaltyPaidDate"></span>
                    </p>
                </div>
            </div>

        </div>

        <div class="action-row" style="margin-top:16px;">

            <button
                type="button"
                id="generatePenaltyQrButton"
                class="btn btn-primary"
                onclick="generatePenaltyQrPayment()"
            >
                Generate QR Ph Payment
            </button>

            <button
                type="button"
                class="btn btn-ghost"
                onclick="closeModal('paymentModal')"
            >
                Close
            </button>

        </div>

    <?php endif; ?>

</div>

<style>
@keyframes spin {
    to {
        transform: rotate(360deg);
    }
}
</style>