<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_donations');

include "db_connect.php";

if (!isset($_GET['id'])) {
    die("Invalid donation ID.");
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("
SELECT
    d.*,
    u.full_name,
    u.email
FROM donations d
LEFT JOIN users u
ON d.user_id = u.id
WHERE d.id = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows == 0) {
    die("Donation not found.");
}

$donation = $result->fetch_assoc();

$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Donation Details</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>

body{
    margin:0;
    background:#020617;
    font-family:'Inter',sans-serif;
    color:white;
}

.wrapper{
    max-width:900px;
    margin:40px auto;
    padding:20px;
}

.card{
    background:#0f172a;
    border-radius:20px;
    padding:30px;
    border:1px solid rgba(255,255,255,.08);
}

h1{
    margin-top:0;
}

table{
    width:100%;
    border-collapse:collapse;
}

td{
    padding:14px;
    border-bottom:1px solid rgba(255,255,255,.08);
}

.label{
    width:240px;
    color:#94a3b8;
    font-weight:bold;
}

.back{
    display:inline-block;
    margin-top:25px;
    padding:12px 20px;
    background:#F2867E;
    color:white;
    text-decoration:none;
    border-radius:10px;
}

.back:hover{
    background:#d76f68;
}

.status{
    padding:6px 12px;
    border-radius:20px;
}

.not{
    color:#facc15;
}

.refunded{
    color:#22c55e;
}

.expired{
    color:#fb7185;
}

</style>

</head>

<body>

<div class="wrapper">

<div class="card">

<h1>Donation Details</h1>

<table>

<tr>
<td class="label">Donation ID</td>
<td><?php echo $donation['id']; ?></td>
</tr>

<tr>
<td class="label">Donor Name</td>
<td><?php echo htmlspecialchars($donation['donor_name']); ?></td>
</tr>

<tr>
<td class="label">Registered Name</td>
<td><?php echo htmlspecialchars($donation['full_name'] ?? 'N/A'); ?></td>
</tr>

<tr>
<td class="label">Email</td>
<td><?php echo htmlspecialchars($donation['email'] ?? 'N/A'); ?></td>
</tr>

<tr>
<td class="label">Amount</td>
<td>₱<?php echo number_format($donation['amount'],2); ?></td>
</tr>

<tr>
<td class="label">Reference Number</td>
<td><?php echo htmlspecialchars($donation['reference_number']); ?></td>
</tr>

<tr>
<td class="label">Payment Method</td>
<td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
</tr>

<tr>
<td class="label">Donation Date</td>
<td><?php echo date("F d, Y h:i A", strtotime($donation['donation_date'])); ?></td>
</tr>

<tr>
<td class="label">Refund Deadline</td>
<td>
<?php
if($donation['refund_deadline'])
    echo date("F d, Y h:i A", strtotime($donation['refund_deadline']));
else
    echo "None";
?>
</td>
</tr>

<tr>
<td class="label">Refund Status</td>

<td>

<?php

$class="not";

if($donation['refund_status']=="Refunded")
    $class="refunded";

if($donation['refund_status']=="Expired")
    $class="expired";

?>

<span class="<?php echo $class; ?>">
<?php echo $donation['refund_status']; ?>
</span>

</td>

</tr>

<tr>
<td class="label">Refunded At</td>

<td>

<?php

if($donation['refunded_at'])
    echo date("F d, Y h:i A", strtotime($donation['refunded_at']));
else
    echo "Not refunded";

?>

</td>

</tr>

</table>

<a href="super_admin_donations.php" class="back">
← Back to Donation Monitoring
</a>

</div>

</div>

</body>
</html>