<?php
require_once __DIR__ . "/../auth_helper.php";
require_permission($conn, 'manage_pet_pound');

$id = intval($_GET['id'] ?? 0);

$stmt = $conn->prepare("
SELECT
pp.*,
p.pet_name,
p.penalty_amount
FROM pet_penalty_payments pp
JOIN pet_pound p
ON p.id = pp.pet_pound_id
WHERE pp.pet_pound_id=?
ORDER BY payment_date DESC
");

$stmt->bind_param("i",$id);
$stmt->execute();

$result = $stmt->get_result();

$pet = $result->fetch_assoc();

if(!$pet){
    die("No payment records found.");
}
?>

<!DOCTYPE html>

<html>

<head>

<title>Payment History</title>

<style>

body{
    font-family:Arial;
    background:#f5f7fb;
}

.card{

    max-width:900px;

    margin:40px auto;

    background:#fff;

    border-radius:12px;

    padding:25px;

    box-shadow:0 8px 20px rgba(0,0,0,.08);

}

table{

    width:100%;

    border-collapse:collapse;

}

th{

    background:#4CAF50;

    color:white;

    padding:12px;

}

td{

    padding:12px;

    border-bottom:1px solid #eee;

}

.info{

    background:#f7f7f7;

    padding:15px;

    margin-bottom:20px;

    border-radius:10px;

}

</style>

</head>

<body>

<div class="card">

<h2>Penalty Payment History</h2>

<div class="info">

<strong>Pet:</strong>

<?= htmlspecialchars($pet['pet_name']) ?>

<br><br>

<strong>Penalty Amount:</strong>

₱<?= number_format($pet['penalty_amount'],2) ?>

</div>

<table>

<tr>

<th>Name</th>

<th>Amount</th>

<th>Reference Number</th>

<th>Date</th>

</tr>

<?php

$result->data_seek(0);

while($row=$result->fetch_assoc()):

?>

<tr>

<td><?= htmlspecialchars($row['payer_name']) ?></td>

<td>₱<?= number_format($row['amount'],2) ?></td>

<td><?= htmlspecialchars($row['reference_number'] ?? '—') ?></td>

<td><?= $row['payment_date'] ? date("M d, Y h:i A",strtotime($row['payment_date'])) : '—' ?></td>

</tr>

<?php endwhile; ?>

</table>

</div>

</body>

</html>
