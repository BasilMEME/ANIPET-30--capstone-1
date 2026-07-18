<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_returns');

$result = $conn->query("
    SELECT pp.*, p.pet_name, p.owner_name AS pound_owner_name, p.penalty_amount
    FROM pet_penalty_payments pp
    JOIN pet_pound p ON p.id = pp.pet_pound_id
    ORDER BY pp.payment_date DESC
");

$payments = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
}

$totalCollected = 0;
foreach ($payments as $p) {
    $totalCollected += (float)$p['amount'];
}
?>

<div class="card">

    <div class="card-header">
        <div>
            <div class="card-title">Penalty Payments</div>
            <div class="card-sub">
                <?= count($payments) ?> payment(s) recorded &middot; ₱<?= number_format($totalCollected, 2) ?> total collected
            </div>
        </div>
    </div>

    <div class="filters-bar">
        <div class="search-wrap">
            <span class="search-icon">🔍</span>
            <input type="text" id="paymentSearch" placeholder="Search pet, payer, reference…" oninput="filterTable('paymentSearch','paymentHistoryTable')">
        </div>
    </div>

    <?php if (empty($payments)): ?>

        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <p>No penalty payments recorded yet.</p>
        </div>

    <?php else: ?>

    <div class="table-wrap">
        <table id="paymentHistoryTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Pet</th>
                    <th>Payer</th>
                    <th>Amount</th>
                    <th>Reference Number</th>
                    <th>Date Paid</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $row): ?>
                <tr>
                    <td><?= $row['id'] ?></td>
                    <td><?= htmlspecialchars($row['pet_name']) ?></td>
                    <td><?= htmlspecialchars($row['payer_name']) ?></td>
                    <td>₱<?= number_format($row['amount'], 2) ?></td>
                    <td><?= htmlspecialchars($row['reference_number'] ?? '—') ?></td>
                    <td><?= $row['payment_date'] ? date("M d, Y g:i A", strtotime($row['payment_date'])) : '—' ?></td>
                    <td>
                        <?php if (!empty($row['receipt_photo'])): ?>
                            <a href="images/payment_receipts/<?= htmlspecialchars($row['receipt_photo']) ?>" target="_blank">View</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

</div>
