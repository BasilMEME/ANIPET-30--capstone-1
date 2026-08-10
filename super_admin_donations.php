<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_donations');

require_once __DIR__ . '/db_connect.php';

function fetchScalar(mysqli $conn, string $sql)
{
    $result = $conn->query($sql);
    return ($result && ($row = $result->fetch_row()))
        ? (float)$row[0]
        : 0;
}

/* ===============================
   Donation Statistics
================================ */

$totalDonations = fetchScalar($conn,
    "SELECT COUNT(*)
     FROM donations
     WHERE payment_status = 'Successful'
       AND donation_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND donation_date < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')");

$totalAmount = fetchScalar($conn,
    "SELECT IFNULL(SUM(amount), 0)
     FROM donations
     WHERE payment_status = 'Successful'
       AND donation_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
       AND donation_date < DATE_FORMAT(CURDATE() + INTERVAL 1 MONTH, '%Y-%m-01')");

/* ===============================
   Monthly Donations
================================ */

$monthlyLabels = [];
$monthlyAmounts = [];

for($i=5;$i>=0;$i--)
{
    $month = date("Y-m", strtotime("-$i month"));

    $monthlyLabels[] = date("M Y", strtotime($month));

    $amount = fetchScalar($conn,"
        SELECT IFNULL(SUM(amount),0)
        FROM donations
        WHERE payment_status = 'Successful'
          AND DATE_FORMAT(donation_date,'%Y-%m')='$month'
    ");

    $monthlyAmounts[] = $amount;
}

/* ===============================
   Recent Donations
================================ */

$recentDonations = [];

$sql = "
SELECT
    d.*,
    COALESCE(u.email, 'Guest Donation') AS email
FROM donations d
LEFT JOIN users u
    ON d.user_id = u.id
WHERE d.payment_status = 'Successful'
ORDER BY donation_date DESC
LIMIT 20
";

$result = $conn->query($sql);

if($result)
{
    while($row=$result->fetch_assoc())
    {
        $recentDonations[]=$row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>AniPet Donation Monitoring</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>

:root{

color-scheme:dark;

--bg:#020617;
--panel:rgba(15,23,42,.92);
--text:#f8fafc;
--muted:#94a3b8;
--accent:#F2867E;
--border:rgba(148,163,184,.16);
--shadow:0 18px 45px rgba(2,8,23,.35);

}

*{
box-sizing:border-box;
}

body{

margin:0;
font-family:'Inter',sans-serif;
background:
radial-gradient(circle at top left,rgba(242,134,126,.15),transparent 25%),
radial-gradient(circle at bottom right,rgba(246,201,160,.12),transparent 22%),
linear-gradient(135deg,#020617 0%,#07111f 100%);

color:var(--text);
min-height:100vh;

}

.wrapper{

max-width:1450px;
margin:auto;
padding:24px;

}

.header{

display:flex;
justify-content:space-between;
align-items:center;
flex-wrap:wrap;

gap:15px;

background:rgba(15,23,42,.75);

padding:20px;

border-radius:24px;

border:1px solid var(--border);

box-shadow:var(--shadow);

margin-bottom:25px;

}

.header h1{

margin:0;
font-size:2rem;

}

.header p{

margin-top:8px;
color:var(--muted);

}

.actions{

display:flex;
gap:12px;
flex-wrap:wrap;

}

.button{

padding:12px 20px;

background:rgba(255,255,255,.08);

border:1px solid rgba(148,163,184,.16);

border-radius:14px;

text-decoration:none;

color:white;

transition:.2s;

}

.button:hover{

background:rgba(255,255,255,.14);

}

.grid{
    display:grid;
    grid-template-columns:repeat(3, minmax(240px, 320px));
    justify-content:center;
    gap:18px;
    margin-bottom:25px;
}

.card{

background:var(--panel);

padding:22px;

border-radius:22px;

border:1px solid var(--border);

box-shadow:var(--shadow);

}

.card h2{

margin:0;

font-size:1rem;

color:#cbd5e1;

}

.card-header{

display:flex;

align-items:center;

justify-content:space-between;

gap:12px;

margin-bottom:15px;

}

.period-badge{

flex-shrink:0;

padding:5px 10px;

border:1px solid rgba(148,163,184,.2);

border-radius:999px;

background:rgba(148,163,184,.08);

color:#94a3b8;

font-size:.72rem;

font-weight:600;

letter-spacing:.02em;

}

.metric{

font-size:2.3rem;

font-weight:bold;

margin-bottom:8px;

}

.metric-label{

color:var(--muted);

}

.chart-card{

background:var(--panel);

padding:22px;

border-radius:22px;

border:1px solid var(--border);

box-shadow:var(--shadow);

margin-bottom:25px;

}

.table-card{

background:var(--panel);

padding:22px;

border-radius:22px;

border:1px solid var(--border);

box-shadow:var(--shadow);

}

.table-wrap{

overflow:auto;

}

table{

width:100%;

border-collapse:collapse;

min-width:900px;

}

th{

padding:14px;

text-align:left;

color:#94a3b8;

border-bottom:1px solid rgba(148,163,184,.15);

}

td{

padding:14px;

border-bottom:1px solid rgba(148,163,184,.12);

}

tr:hover{

background:rgba(242,134,126,.08);

}

.details{

background:#F2867E;

padding:8px 14px;

border-radius:10px;

text-decoration:none;

color:white;

font-size:.85rem;

}

.details:hover{

background:#d76f68;

}


.payment-status{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 11px;
    border-radius:999px;
    font-size:.82rem;
    font-weight:600;
    white-space:nowrap;
}

.payment-status.successful{
    color:#86efac;
    background:rgba(34,197,94,.14);
    border:1px solid rgba(34,197,94,.28);
}

.payment-status.successful::before{
    content:"";
    width:7px;
    height:7px;
    border-radius:50%;
    background:#22c55e;
    box-shadow:0 0 0 3px rgba(34,197,94,.12);
}

@media(max-width:1100px){

.grid{

grid-template-columns:repeat(2,1fr);

}

}

@media(max-width:700px){

.grid{

grid-template-columns:1fr;

}

}

</style>

</head>

<body>

<div class="wrapper">

<div class="header">

<div>

<h1>Donation Monitoring</h1>

<p>Monitor successful donations, payment methods, and donor activities.</p>

</div>

<div class="actions">
<a href="super_admin_dashboard.php" class="button">Dashboard</a>
</div>

</div>

<!-- ==========================
     DASHBOARD CARDS
========================== -->

<div class="grid">

    <div class="card">
        <div class="card-header">
            <h2>Successful Donations</h2>
            <span class="period-badge">This month</span>
        </div>

        <div class="metric">
            <?php echo number_format($totalDonations); ?>
        </div>

        <div class="metric-label">
            Completed payments received
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2>Total Amount Received</h2>
            <span class="period-badge">This month</span>
        </div>

        <div class="metric">
            ₱<?php echo number_format($totalAmount,2); ?>
        </div>

        <div class="metric-label">
            Successful donation amount
        </div>
    </div>

</div>

<!-- ==========================
     MONTHLY CHART
========================== -->

<div class="chart-card">

    <h2 style="margin-bottom:20px;">
        Monthly Donation Collection
    </h2>

    <canvas id="donationChart" height="120"></canvas>

</div>

<!-- ==========================
     RECENT DONATIONS TABLE
========================== -->

<div class="table-card">

    <h2 style="margin-bottom:20px;">
        Recent Donations
    </h2>

    <div class="table-wrap">

        <table>

<thead>

<tr>
    <th>ID</th>
    <th>Donor</th>
    <th>Email</th>
    <th>Amount</th>
    <th>Reference No.</th>
    <th>Payment</th>
    <th>Status</th>
    <th>Receipt</th>
    <th>Date</th>
    <th>Action</th>
</tr>

</thead>

            <tbody>

<?php if(count($recentDonations)>0): ?>

<?php foreach($recentDonations as $donation): ?>

<tr>

    <td>
        <?php echo $donation['id']; ?>
    </td>

    <td>
        <?php echo htmlspecialchars($donation['donor_name']); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($donation['email']); ?>
    </td>

    <td>
        ₱<?php echo number_format($donation['amount'],2); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($donation['reference_number']); ?>
    </td>

    <td>
        <?php echo htmlspecialchars($donation['payment_method']); ?>
    </td>

    <td>
        <span class="payment-status successful">
            <?php echo htmlspecialchars($donation['payment_status']); ?>
        </span>
    </td>

    <td>

        <?php if (!empty($donation['receipt_image'])): ?>

<a href="donation_receipts/<?php echo urlencode($donation['receipt_image']); ?>"
   class="receipt-link"
   target="_blank">
    View Receipt
</a>

<?php else: ?>

<span style="color:#94a3b8;">No Receipt</span>

<?php endif; ?>

    </td>

    <td>
        <?php echo date("M d, Y h:i A", strtotime($donation['donation_date'])); ?>
    </td>

    <td>

        <a class="details"
           href="super_admin_donation_detail.php?id=<?php echo $donation['id']; ?>">
            View
        </a>

    </td>

</tr>

<?php endforeach; ?>

<?php else: ?>

<tr>

    <td colspan="10" style="text-align:center;">
        No donations found.
    </td>

</tr>

<?php endif; ?>

</tbody>

        </table>

    </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>

const labels = <?php echo json_encode($monthlyLabels); ?>;

const values = <?php echo json_encode($monthlyAmounts); ?>;

new Chart(document.getElementById("donationChart"),{

    type:'bar',

    data:{

        labels:labels,

        datasets:[{

            label:'Monthly Donations',

            data:values,

            backgroundColor:'#F2867E',

            borderRadius:8

        }]

    },

    options:{

        responsive:true,

        plugins:{
            legend:{
                display:false
            }
        },

        scales:{

            y:{
                beginAtZero:true
            }

        }

    }

});

</script>

<div id="receiptModal" style="display:none;
position:fixed;
left:0;
top:0;
width:100%;
height:100%;
background:rgba(0,0,0,.8);
justify-content:center;
align-items:center;
z-index:9999;">

    <div style="position:relative;">

        <button
            onclick="closeReceipt()"
            style="
            position:absolute;
            right:-10px;
            top:-10px;
            background:#F2867E;
            border:none;
            color:white;
            width:35px;
            height:35px;
            border-radius:50%;
            cursor:pointer;">
            ✕
        </button>

        <img
            id="receiptImage"
            src=""
            style="
            max-width:90vw;
            max-height:90vh;
            border-radius:10px;
            background:white;">
    </div>

</div>

<script>

function showReceipt(filename)
{
    document.getElementById("receiptImage").src =
        "donation_receipts/" + filename;

    document.getElementById("receiptModal").style.display = "flex";
}

function closeReceipt()
{
    document.getElementById("receiptModal").style.display = "none";
}

</script>

</body>
</html>