<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

$id = (int)($_GET['id'] ?? 0);

// Lazy grace-period expiry for this row, evaluated entirely in SQL (see
// post_pet_for_adoption.php for why this can't be a PHP time()/strtotime() check).
$conn->query("UPDATE pet_pound SET status='Expired' WHERE id=" . $id . " AND status='Pending' AND claim_deadline < NOW()");

$stmt = $conn->prepare("SELECT * FROM pet_pound WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    echo '<div class="empty-state">Pet record not found.</div>';
    exit;
}

$paymentCount = 0;
$stmt = $conn->prepare("SELECT COUNT(*) FROM pet_penalty_payments WHERE pet_pound_id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->bind_result($paymentCount);
$stmt->fetch();
$stmt->close();

$badge = "badge";
switch ($row['status']) {
    case "Pending":  $badge = "badge-pending";  break;
    case "Claimed":
    case "Paid":     $badge = "badge-approved"; break;
    case "Posted":   $badge = "badge-info";     break;
    case "Expired":
    case "Deceased": $badge = "badge-danger";   break;
}

// "Posted"/"Deceased" are excluded here — those only ever happen through their
// dedicated action buttons below, never a free-form status edit.
$statusOptions = ["Pending", "Claimed", "Paid", "Expired"];

$isDeceased = ($row['status'] === 'Deceased');
$isPosted   = !empty($row['posted_for_adoption']);
$isClaimed  = in_array($row['status'], ['Claimed', 'Paid'], true);
// $row['status'] is authoritative here — the lazy-expiry UPDATE above already
// flipped Pending -> Expired if claim_deadline had passed, entirely in SQL.
$graceExpired = (
    $row['status'] === 'Expired' ||
    strtotime($row['claim_deadline']) <= time()
);
?>

<div class="info-grid">

    <div class="info-item">
        <label>Pet Name</label>
        <span><?= htmlspecialchars($row['pet_name']) ?></span>
    </div>

    <div class="info-item">
        <label>Owner</label>
        <span><?= htmlspecialchars($row['owner_name']) ?></span>
    </div>

    <div class="info-item">
        <label>Reason</label>
        <span><?= htmlspecialchars($row['reason']) ?></span>
    </div>

    <div class="info-item">
        <label>Penalty</label>
        <span>₱<?= number_format($row['penalty_amount'], 2) ?></span>
    </div>

    <div class="info-item">
        <label>Impounded</label>
        <span><?= date("M d, Y g:i A", strtotime($row['impound_date'])) ?></span>
    </div>

    <div class="info-item">
        <label>Grace Period Ends</label>
        <span><?= date("M d, Y g:i A", strtotime($row['claim_deadline'])) ?></span>
    </div>

    <div class="info-item">
        <label>Status</label>
        <span class="badge <?= $badge ?>"><?= htmlspecialchars($row['status']) ?></span>
    </div>

</div>

<?php if (!$isDeceased && !$isPosted && !$isClaimed): ?>
    <div class="divider"></div>

    <?php if ($graceExpired): ?>
        <p style="color:var(--danger);font-weight:600;">
            The 14-day grace period has expired. This pet is now eligible to be posted for adoption.
        </p>
    <?php else: ?>
        <p style="color:var(--muted);">
            The owner has until
            <strong><?= date("M d, Y g:i A", strtotime($row['claim_deadline'])) ?></strong>
            (14 days from impoundment) to claim this pet before it can be posted for adoption.
        </p>
    <?php endif; ?>
<?php endif; ?>

<?php if ($isDeceased): ?>

    <div class="divider"></div>

    <div class="info-grid">
        <div class="info-item">
            <label>Cause of Death</label>
            <span><?= htmlspecialchars($row['cause_of_death'] ?? '') ?></span>
        </div>
        <div class="info-item">
            <label>Recorded</label>
            <span><?= $row['death_date'] ? date("M d, Y g:i A", strtotime($row['death_date'])) : '—' ?></span>
        </div>
    </div>
    <?php if (!empty($row['death_remarks'])): ?>
        <div class="info-item" style="margin-top:10px;">
            <label>Remarks</label>
            <span><?= nl2br(htmlspecialchars($row['death_remarks'])) ?></span>
        </div>
    <?php endif; ?>

<?php else: ?>

<?php if (!empty($row['pet_photo'])): ?>
    <div class="divider"></div>
    <div style="display:flex;justify-content:center;">
        <img
            src="images/pet_pound/<?= htmlspecialchars($row['pet_photo']) ?>"
            style="
                width:240px;
                height:150px;
                object-fit:cover;
                object-position:center;
                border-radius:var(--radius-sm);
                border:1px solid var(--border);
                display:block;
            ">
    </div>
<?php endif; ?>

<div class="divider"></div>

<!-- ===========================
FREE STATUS CHANGE
=========================== -->

<div class="form-group">

    <label class="form-label">Change Status</label>

    <div style="display:flex;gap:10px;">

        <select id="statusSelect" class="form-control">
            <?php foreach ($statusOptions as $option): ?>
                <option value="<?= $option ?>" <?= ($option === $row['status']) ? 'selected' : '' ?>>
                    <?= $option ?>
                </option>
            <?php endforeach; ?>
        </select>

        <button class="btn btn-primary" onclick="updatePetStatus()" style="white-space:nowrap;">
            Update Status
        </button>

    </div>

</div>

<div class="divider"></div>

<div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;">

    <button class="btn btn-success" onclick="paymentPet()">
        💳 Penalty Payment
    </button>

    <?php if ($paymentCount > 0): ?>
        <button class="btn btn-secondary" onclick="viewPaymentHistory()">
            🧾 Payment History
        </button>
    <?php endif; ?>

    <?php if (!$isPosted): ?>
        <button class="btn btn-warning" onclick="claimPet()">
            🐾 Claim Pet
        </button>
    <?php endif; ?>

    <?php if (!$isPosted && !$isClaimed): ?>
        <button
    type="button"
    class="btn btn-info"
    onclick="openAdoptionPostModal(this)"
    data-name="<?= htmlspecialchars($row['pet_name'] ?? '', ENT_QUOTES) ?>"
    data-species="<?= htmlspecialchars($row['species'] ?? '', ENT_QUOTES) ?>"
    data-breed="<?= htmlspecialchars($row['breed'] ?? '', ENT_QUOTES) ?>"
    data-age="<?= htmlspecialchars($row['age'] ?? '', ENT_QUOTES) ?>"
    data-gender="<?= htmlspecialchars($row['gender'] ?? '', ENT_QUOTES) ?>"
    data-health="<?= htmlspecialchars($row['health_status'] ?? '', ENT_QUOTES) ?>"
    data-description="<?= htmlspecialchars(
        "Impounded pet.\nReason: " . ($row['reason'] ?? ''),
        ENT_QUOTES
    ) ?>"
    <?= !$graceExpired ? 'disabled title="The 14-day grace period is still active"' : '' ?>
>
    ❤️ Post for Adoption
</button>
<?php endif; ?>

<?php if (!$isPosted): ?>
    <button class="btn btn-danger" onclick="openDeceasedModal()">
        ☠ Mark as Deceased
    </button>
<?php endif; ?>

</div>

<?php endif; ?>
