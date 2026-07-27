<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_donations');

require_once __DIR__ . '/db_connect.php';

if (!isset($_GET['id']) || !ctype_digit((string) $_GET['id'])) {
    http_response_code(400);
    die("Invalid donation ID.");
}

$id = (int) $_GET['id'];

$stmt = $conn->prepare("
    SELECT
        d.*,
        COALESCE(u.full_name, 'Guest User') AS registered_name,
        COALESCE(u.email, 'Guest Donation') AS email
    FROM donations d
    LEFT JOIN users u
        ON d.user_id = u.id
    WHERE d.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $id);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(404);
    die("Donation not found.");
}

$donation = $result->fetch_assoc();

$stmt->close();
$conn->close();

$paymentStatus = trim($donation['payment_status'] ?? 'Successful');
$statusClass = strtolower($paymentStatus) === 'successful'
    ? 'status-success'
    : 'status-neutral';

$receiptFilename = trim($donation['receipt_image'] ?? '');
$receiptUrl = $receiptFilename !== ''
    ? 'donation_receipts/' . rawurlencode($receiptFilename)
    : '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Donation Details | AniPet</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
:root{
    color-scheme:dark;
    --bg:#020617;
    --panel:rgba(15,23,42,.92);
    --panel-soft:rgba(30,41,59,.55);
    --text:#f8fafc;
    --muted:#94a3b8;
    --accent:#F2867E;
    --accent-dark:#d76f68;
    --border:rgba(148,163,184,.16);
    --success:#22c55e;
    --success-bg:rgba(34,197,94,.14);
    --shadow:0 20px 55px rgba(2,8,23,.42);
}

*{
    box-sizing:border-box;
}

body{
    margin:0;
    min-height:100vh;
    font-family:'Inter',sans-serif;
    color:var(--text);
    background:
        radial-gradient(circle at top left,rgba(242,134,126,.15),transparent 25%),
        radial-gradient(circle at bottom right,rgba(246,201,160,.10),transparent 23%),
        linear-gradient(135deg,#020617 0%,#07111f 100%);
}

.wrapper{
    max-width:980px;
    margin:0 auto;
    padding:32px 20px;
}

.page-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:18px;
    flex-wrap:wrap;
    margin-bottom:22px;
}

.page-header h1{
    margin:0;
    font-size:clamp(1.75rem,4vw,2.35rem);
}

.page-header p{
    margin:8px 0 0;
    color:var(--muted);
}

.back{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:11px 17px;
    border-radius:12px;
    background:rgba(255,255,255,.07);
    border:1px solid var(--border);
    color:white;
    text-decoration:none;
    transition:.2s;
}

.back:hover{
    background:rgba(255,255,255,.13);
}

.card{
    background:var(--panel);
    border:1px solid var(--border);
    border-radius:24px;
    box-shadow:var(--shadow);
    overflow:hidden;
}

.receipt-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:18px;
    flex-wrap:wrap;
    padding:26px;
    border-bottom:1px solid var(--border);
}

.receipt-head h2{
    margin:0 0 7px;
    font-size:1.2rem;
}

.receipt-head p{
    margin:0;
    color:var(--muted);
    font-size:.92rem;
}

.amount-block{
    text-align:right;
}

.amount-label{
    color:var(--muted);
    font-size:.83rem;
    margin-bottom:5px;
}

.amount{
    font-size:2rem;
    font-weight:700;
    letter-spacing:-.02em;
}

.status{
    display:inline-flex;
    align-items:center;
    gap:8px;
    margin-top:10px;
    padding:7px 12px;
    border-radius:999px;
    font-size:.84rem;
    font-weight:600;
}

.status::before{
    content:"";
    width:8px;
    height:8px;
    border-radius:50%;
    background:currentColor;
}

.status-success{
    color:#4ade80;
    background:var(--success-bg);
    border:1px solid rgba(34,197,94,.28);
}

.status-neutral{
    color:#cbd5e1;
    background:rgba(148,163,184,.12);
    border:1px solid rgba(148,163,184,.22);
}

.content{
    padding:26px;
}

.section-title{
    margin:0 0 16px;
    font-size:1rem;
    color:#e2e8f0;
}

.details-grid{
    display:grid;
    grid-template-columns:repeat(2,minmax(0,1fr));
    gap:14px;
}

.detail{
    min-width:0;
    padding:16px;
    border:1px solid var(--border);
    border-radius:16px;
    background:var(--panel-soft);
}

.detail.full{
    grid-column:1 / -1;
}

.label{
    display:block;
    margin-bottom:7px;
    color:var(--muted);
    font-size:.78rem;
    font-weight:600;
    letter-spacing:.03em;
    text-transform:uppercase;
}

.value{
    color:var(--text);
    font-size:.96rem;
    overflow-wrap:anywhere;
}

.reference{
    font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;
    font-size:.88rem;
}

.receipt-section{
    margin-top:24px;
    padding-top:24px;
    border-top:1px solid var(--border);
}

.receipt-actions{
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.receipt-link{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    padding:11px 17px;
    border-radius:12px;
    background:var(--accent);
    color:white;
    text-decoration:none;
    font-weight:600;
    transition:.2s;
}

.receipt-link:hover{
    background:var(--accent-dark);
}

.no-receipt{
    color:var(--muted);
    padding:13px 15px;
    border:1px dashed var(--border);
    border-radius:12px;
}

@media(max-width:700px){
    .wrapper{
        padding:20px 14px;
    }

    .receipt-head,
    .content{
        padding:20px;
    }

    .amount-block{
        text-align:left;
    }

    .details-grid{
        grid-template-columns:1fr;
    }

    .detail.full{
        grid-column:auto;
    }
}
</style>
</head>

<body>
<div class="wrapper">

    <div class="page-header">
        <div>
            <h1>Donation Details</h1>
            <p>Review the complete payment and donor information.</p>
        </div>

        <a href="super_admin_donations.php" class="back">
            ← Back to Monitoring
        </a>
    </div>

    <main class="card">

        <div class="receipt-head">
            <div>
                <h2>AniPet Donation Receipt</h2>
                <p>Donation #<?php echo (int) $donation['id']; ?></p>
            </div>

            <div class="amount-block">
                <div class="amount-label">Amount Received</div>
                <div class="amount">
                    ₱<?php echo number_format((float) $donation['amount'], 2); ?>
                </div>

                <span class="status <?php echo $statusClass; ?>">
                    <?php echo htmlspecialchars($paymentStatus); ?>
                </span>
            </div>
        </div>

        <div class="content">
            <h3 class="section-title">Donation Information</h3>

            <div class="details-grid">

                <div class="detail">
                    <span class="label">Donor Name</span>
                    <div class="value">
                        <?php echo htmlspecialchars($donation['donor_name']); ?>
                    </div>
                </div>

                <div class="detail">
                    <span class="label">Registered Name</span>
                    <div class="value">
                        <?php echo htmlspecialchars($donation['registered_name']); ?>
                    </div>
                </div>

                <div class="detail">
                    <span class="label">Email</span>
                    <div class="value">
                        <?php echo htmlspecialchars($donation['email']); ?>
                    </div>
                </div>

                <div class="detail">
                    <span class="label">Pet Name</span>
                    <div class="value">
                        <?php
                        echo htmlspecialchars(
                            trim($donation['pet_name'] ?? '') !== ''
                                ? $donation['pet_name']
                                : 'Not provided'
                        );
                        ?>
                    </div>
                </div>

                <div class="detail">
                    <span class="label">Payment Method</span>
                    <div class="value">
                        <?php echo htmlspecialchars($donation['payment_method']); ?>
                    </div>
                </div>

                <div class="detail">
                    <span class="label">Payment Status</span>
                    <div class="value">
                        <?php echo htmlspecialchars($paymentStatus); ?>
                    </div>
                </div>

                <div class="detail full">
                    <span class="label">Reference Number</span>
                    <div class="value reference">
                        <?php echo htmlspecialchars($donation['reference_number']); ?>
                    </div>
                </div>

                <div class="detail full">
                    <span class="label">Donation Date</span>
                    <div class="value">
                        <?php
                        echo date(
                            "F d, Y h:i A",
                            strtotime($donation['donation_date'])
                        );
                        ?>
                    </div>
                </div>

            </div>

            <section class="receipt-section">
                <h3 class="section-title">Receipt Attachment</h3>

                <div class="receipt-actions">
                    <?php if ($receiptUrl !== ''): ?>
                        <a
                            href="<?php echo htmlspecialchars($receiptUrl); ?>"
                            class="receipt-link"
                            target="_blank"
                            rel="noopener"
                        >
                            View Receipt
                        </a>
                    <?php else: ?>
                        <div class="no-receipt">
                            No receipt was uploaded for this donation.
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>

    </main>
</div>
</body>
</html>
