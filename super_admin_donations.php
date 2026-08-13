<?php

date_default_timezone_set('Asia/Manila');

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

$averageDonation = $totalDonations > 0
    ? $totalAmount / $totalDonations
    : 0;

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
    --bg:#f4ead9;
    --panel:rgba(255,250,241,.94);
    --panel-soft:#f5e7d4;
    --surface:rgba(255,252,246,.96);
    --text:#3b2417;
    --muted:#916b50;
    --accent:#986038;
    --accent-dark:#754325;
    --border:#d9b996;
    --shadow:0 10px 28px rgba(82,48,27,.10);
    --success:#2f855a;
}

*{
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    color:var(--text);
    min-height:100vh;
    background:
        linear-gradient(rgba(244,234,217,.90),rgba(244,234,217,.90)),
        url('/anipet_admin_wallpaper.png') center/cover fixed no-repeat;
}

.wrapper{
    max-width:1480px;
    margin:0 auto;
    padding:24px 26px 44px;
}

/* HEADER */
.header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    flex-wrap:wrap;
    gap:18px;
    background:var(--panel);
    padding:22px 24px;
    border-radius:22px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    margin-bottom:22px;
}

.header h1{
    margin:0;
    font-size:clamp(1.6rem,2.4vw,2.25rem);
    color:var(--text);
}

.header p{
    margin:7px 0 0;
    color:var(--muted);
    line-height:1.5;
}

.actions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.button{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 16px;
    background:#fffaf3;
    border:1px solid var(--border);
    border-radius:12px;
    text-decoration:none;
    color:var(--text);
    font-weight:750;
    transition:.18s ease;
}

.button:hover{
    background:#efddc5;
    transform:translateY(-1px);
}

/* METRICS */
.grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(240px,1fr));
    gap:18px;
    margin-bottom:22px;
}

.card{
    background:var(--panel);
    padding:20px 22px;
    border-radius:18px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
}

.card-header{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
    margin-bottom:14px;
}

.card h2{
    margin:0;
    font-size:1rem;
    color:var(--text);
}

.period-badge{
    flex-shrink:0;
    padding:6px 10px;
    border:1px solid var(--border);
    border-radius:999px;
    background:#f0dfca;
    color:#754325;
    font-size:.72rem;
    font-weight:750;
}

.metric{
    font-size:2.2rem;
    font-weight:800;
    color:var(--text);
    margin-bottom:7px;
}

.metric-label{
    color:var(--muted);
    font-size:.88rem;
    line-height:1.45;
}

/* CHART */
.chart-card{
    background:var(--panel);
    padding:22px;
    border-radius:18px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
    margin-bottom:22px;
}

.chart-card h2{
    color:var(--text);
}

/* TABLE */
.table-card{
    background:var(--panel);
    padding:22px;
    border-radius:18px;
    border:1px solid var(--border);
    box-shadow:var(--shadow);
}

.table-card h2{
    color:var(--text);
}

.table-wrap{
    overflow:auto;
    border:1px solid var(--border);
    border-radius:15px;
    background:var(--surface);
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:960px;
}

th,
td{
    padding:12px 14px;
    text-align:left;
    border-bottom:1px solid rgba(139,91,54,.16);
    vertical-align:middle;
}

th{
    color:#6f472c;
    font-size:.74rem;
    text-transform:uppercase;
    letter-spacing:.05em;
    background:#efddc5;
    position:sticky;
    top:0;
    z-index:2;
}

td{
    color:var(--text);
    font-size:.88rem;
    background:rgba(255,252,246,.76);
}

tbody tr:hover td{
    background:#fbefdf;
}

tbody tr:last-child td{
    border-bottom:0;
}

.details{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:36px;
    padding:8px 13px;
    border-radius:10px;
    text-decoration:none;
    background:var(--accent);
    border:1px solid var(--accent);
    color:#fffaf3;
    font-size:.82rem;
    font-weight:750;
    transition:.18s ease;
}

.details:hover{
    background:var(--accent-dark);
}

.receipt-link{
    color:var(--accent-dark);
    font-weight:750;
    text-decoration:none;
}

.receipt-link:hover{
    text-decoration:underline;
}

.payment-status{
    display:inline-flex;
    align-items:center;
    gap:7px;
    padding:7px 11px;
    border-radius:999px;
    font-size:.8rem;
    font-weight:700;
    white-space:nowrap;
}

.payment-status.successful{
    color:#17623d;
    background:#dff3e8;
    border:1px solid #a9d9bd;
}

.payment-status.successful::before{
    content:"";
    width:7px;
    height:7px;
    border-radius:50%;
    background:#2f855a;
    box-shadow:0 0 0 3px rgba(47,133,90,.10);
}

/* RECEIPT MODAL */
#receiptModal{
    backdrop-filter:blur(4px);
}

#receiptModal img{
    border:8px solid rgba(255,250,241,.94);
    box-shadow:0 18px 60px rgba(31,18,10,.30);
}

/* RESPONSIVE */
@media(max-width:1100px){
    .grid{
        grid-template-columns:repeat(2,minmax(0,1fr));
    }
}

@media(max-width:700px){
    .wrapper{
        padding:14px;
    }

    .header{
        align-items:flex-start;
        flex-direction:column;
        padding:18px;
    }

    .grid{
        grid-template-columns:1fr;
    }

    .card,
    .chart-card,
    .table-card{
        padding:16px;
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

    <div class="card">
        <div class="card-header">
            <h2>Average Donation</h2>
            <span class="period-badge">This month</span>
        </div>

        <div class="metric">
            ₱<?php echo number_format($averageDonation, 2); ?>
        </div>

        <div class="metric-label">
            Average amount per successful donation
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

<span style="color:#916b50;">No Receipt</span>

<?php endif; ?>

    </td>

    <td>
        <?php
        $utcTime = new DateTime(
            $donation['donation_date'],
            new DateTimeZone('UTC')
        );

        $utcTime->setTimezone(
            new DateTimeZone('Asia/Manila')
        );

        echo $utcTime->format('M d, Y h:i A');
        ?>
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

            backgroundColor:'#986038',

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
            background:#986038;
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