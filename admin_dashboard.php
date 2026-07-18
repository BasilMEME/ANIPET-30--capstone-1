<?php
require_once __DIR__ . '/auth_helper.php';
require_admin();

function fetchCount($conn, string $sql): int {
    $result = $conn->query($sql);
    if ($result && ($row = $result->fetch_row())) {
        return intval($row[0]);
    }
    return 0;
}

$totalPets = fetchCount($conn, "SELECT COUNT(*) FROM pets");
$availablePets = fetchCount($conn, "SELECT COUNT(*) FROM pets WHERE status = 'available'");
$adoptedPets = fetchCount($conn, "SELECT COUNT(*) FROM pets WHERE status = 'adopted'");
$totalApplications = fetchCount($conn, "SELECT COUNT(*) FROM adoption_applications");
$pendingApplications = fetchCount($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'pending'");
$approvedApplications = fetchCount($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'approved'");
$totalUsers = fetchCount($conn, "SELECT COUNT(*) FROM users WHERE role = 'user'");
$pendingAppointments = fetchCount($conn, "SELECT COUNT(*) FROM appointments WHERE status = 'pending'");

$recentApps = [];
$appResult = $conn->query("SELECT aa.id, aa.applicant_name, aa.status, aa.created_at, p.name AS pet_name FROM adoption_applications aa LEFT JOIN pets p ON aa.pet_id = p.id ORDER BY aa.created_at DESC LIMIT 6");
if ($appResult) {
    while ($row = $appResult->fetch_assoc()) {
        $recentApps[] = $row;
    }
}

$recentPets = [];
$petsResult = $conn->query("SELECT id, name, breed, age, gender, status FROM pets ORDER BY id DESC LIMIT 6");
if ($petsResult) {
    while ($row = $petsResult->fetch_assoc()) {
        $recentPets[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Admin Dashboard</title>
    <style>
        :root {
            --bg: #eef2ff;
            --surface: #ffffff;
            --surface-alt: #f8fafc;
            --text: #0f172a;
            --muted: #475569;
            --border: rgba(100, 116, 139, 0.15);
            --accent: #2563eb;
            --success: #16a34a;
            --warning: #f59e0b;
            --danger: #dc2626;
            --shadow: 0 18px 45px rgba(15, 23, 42, 0.08);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.14), transparent 28%),
                        linear-gradient(180deg, #eff6ff 0%, #eef2ff 100%);
            color: var(--text);
            min-height: 100vh;
        }
        .page {
            width: min(1200px, calc(100% - 32px));
            margin: 0 auto;
            padding: 24px 0 40px;
        }
        .header {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            margin-bottom: 24px;
        }
        .brand {
            display: grid;
            gap: 6px;
        }
        .brand h1 {
            margin: 0;
            font-size: clamp(2rem, 2.5vw, 2.6rem);
        }
        .brand p {
            margin: 0;
            color: var(--muted);
            max-width: 640px;
        }
        .cta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 18px;
            border-radius: 999px;
            border: 1px solid rgba(37, 99, 235, 0.16);
            background: white;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        .grid {
            display: grid;
            gap: 18px;
        }
        .kpi-grid {
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 22px;
            box-shadow: var(--shadow);
        }
        .card h2 {
            margin: 0 0 8px;
            font-size: 1.2rem;
        }
        .card p {
            margin: 0;
            color: var(--muted);
            line-height: 1.7;
        }
        .kpi {
            display: grid;
            gap: 10px;
        }
        .kpi strong {
            font-size: 2.1rem;
            display: block;
            color: var(--text);
        }
        .kpi small {
            color: var(--muted);
        }
        .actions {
            display: grid;
            gap: 14px;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }
        .action-link {
            display: block;
            padding: 18px;
            border-radius: 20px;
            border: 1px solid var(--border);
            background: var(--surface-alt);
            text-decoration: none;
            color: var(--text);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }
        .action-link:hover {
            transform: translateY(-2px);
            border-color: rgba(37, 99, 235, 0.25);
        }
        .action-link h3 {
            margin: 0 0 6px;
            font-size: 1rem;
        }
        .action-link span {
            color: var(--muted);
            font-size: 0.95rem;
        }
        .table-wrap {
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 620px;
        }
        th, td {
            padding: 14px 12px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            color: var(--text);
        }
        th {
            color: var(--muted);
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        tbody tr:hover {
            background: rgba(37, 99, 235, 0.06);
        }
        .status {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
        }
        /* ===============================
   MODAL DESIGN
=================================*/

.modal-backdrop{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.55);
    display:none;
    justify-content:center;
    align-items:center;
    z-index:9999;
}

.modal{
    width:560px;
    max-width:95%;
    background:#fff;
    border-radius:18px;
    overflow:hidden;
    box-shadow:0 25px 60px rgba(0,0,0,.25);
    animation:popup .25s ease;
}

@keyframes popup{
    from{
        opacity:0;
        transform:translateY(15px) scale(.96);
    }
    to{
        opacity:1;
        transform:translateY(0) scale(1);
    }
}

/* Header */

.modal-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:18px 25px;
    border-bottom:1px solid #ececec;
    background:#fff;
}

.modal-title{
    margin:0;
    font-size:24px;
    font-weight:700;
    color:#23395d;
}

.modal-close{
    background:none;
    border:none;
    font-size:30px;
    cursor:pointer;
    color:#999;
    transition:.2s;
}

.modal-close:hover{
    color:#ef4444;
}

/* Body */

.modal-body{
    padding:25px;
}

/* Footer */

.modal-footer{
    display:flex;
    justify-content:flex-end;
    gap:12px;
    padding:20px 25px;
    background:#fafafa;
    border-top:1px solid #eee;
}

/* ===============================
   FORM
=================================*/

.form-group{
    margin-bottom:18px;
}

.form-label{
    display:block;
    margin-bottom:7px;
    font-weight:600;
    color:#444;
}

.form-control{
    width:100%;
    padding:11px 14px;
    border:1px solid #ddd;
    border-radius:10px;
    font-size:15px;
    transition:.2s;
}

.form-control:focus{
    outline:none;
    border-color:#3b82f6;
    box-shadow:0 0 0 3px rgba(59,130,246,.15);
}

textarea.form-control{
    resize:vertical;
    min-height:90px;
}

/* ===============================
   PET INFORMATION
=================================*/

.info-grid{
    display:grid;
    grid-template-columns:1fr;
    gap:18px;
}

.info-item{
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:12px 0;
    border-bottom:1px solid #eee;
}

.info-item:last-child{
    border-bottom:none;
}

.info-item label{
    font-weight:600;
    color:#666;
}

.divider{
    height:1px;
    background:#eee;
    margin:20px 0;
}

/* ===============================
   PET TABLE
=================================*/

.pet-cell{
    display:flex;
    align-items:center;
    gap:12px;
}

.pet-cell img{
    width:58px;
    height:58px;
    object-fit:cover;
    border-radius:10px;
    border:1px solid #ddd;
    flex-shrink:0;
}

.pet-cell span{
    display:flex;
    align-items:center;
    height:58px;
    font-weight:600;
}

/* ===============================
   BUTTONS
=================================*/

.btn{
    border:none;
    cursor:pointer;
    border-radius:10px;
    padding:10px 18px;
    font-weight:600;
    transition:.2s;
}

.btn:hover{
    transform:translateY(-2px);
}

.btn-primary{
    background:#2563eb;
    color:#fff;
}

.btn-success{
    background:#16a34a;
    color:#fff;
}

.btn-warning{
    background:#f59e0b;
    color:#fff;
}

.btn-info{
    background:#0ea5e9;
    color:#fff;
}

.btn-danger{
    background:#dc2626;
    color:#fff;
}

.btn-ghost{
    background:#ececec;
    color:#333;
}

/* ===============================
   BADGES
=================================*/

.badge{
    display:inline-block;
    padding:6px 12px;
    border-radius:20px;
    font-size:13px;
    font-weight:600;
}

.badge-pending{
    background:#FEF3C7;
    color:#B45309;
}

.badge-approved{
    background:#DCFCE7;
    color:#15803D;
}

.badge-info{
    background:#DBEAFE;
    color:#1D4ED8;
}

.badge-danger{
    background:#FEE2E2;
    color:#DC2626;
}
        .status.pending { background: rgba(249, 115, 22, 0.12); color: #c2410c; }
        .status.approved { background: rgba(16, 185, 129, 0.12); color: #166534; }
        .status.rejected { background: rgba(220, 38, 38, 0.12); color: #991b1b; }
        .status.adopted { background: rgba(16, 185, 129, 0.12); color: #166534; }
        .status.available { background: rgba(34, 197, 94, 0.12); color: #14532d; }
        .status.pending-appointment { background: rgba(59, 130, 246, 0.12); color: #1d4ed8; }
        .footer-note {
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.7;
        }
        @media (max-width: 768px) {
            .header { flex-direction: column; align-items: stretch; }
            .cta { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="page">
        <header class="header">
            <div class="brand">
                <h1>AniPet Admin Dashboard</h1>
                <p>View shelter operations, adoption application flow, and daily pet intake status from a dedicated admin workspace.</p>
            </div>
            <a class="cta" href="logout.php">Logout</a>
        </header>

        <section class="grid kpi-grid">
            <article class="card kpi">
                <strong><?php echo $totalPets; ?></strong>
                <small>Total pets in shelter</small>
            </article>
            <article class="card kpi">
                <strong><?php echo $availablePets; ?></strong>
                <small>Available pets</small>
            </article>
            <article class="card kpi">
                <strong><?php echo $totalApplications; ?></strong>
                <small>Total adoption applications</small>
            </article>
            <article class="card kpi">
                <strong><?php echo $pendingApplications; ?></strong>
                <small>Pending application reviews</small>
            </article>
            <article class="card kpi">
                <strong><?php echo $totalUsers; ?></strong>
                <small>Registered adopters</small>
            </article>
            <article class="card kpi">
                <strong><?php echo $pendingAppointments; ?></strong>
                <small>Pending appointments</small>
            </article>
        </section>

        <section class="grid actions">
            <a class="action-link" href="admin_applications.php">
                <h3>Review Adoption Applications</h3>
                <span>Approve or reject new adoption requests and monitor pending cases.</span>
            </a>
            <a class="action-link" href="pets.php">
                <h3>View Available Pets</h3>
                <span>Open available pet list and verify pet status for intake or adoption workflows.</span>
            </a>
            <a class="action-link" href="get_appointments.php">
                <h3>Appointment Summary</h3>
                <span>Review upcoming client appointments and schedule follow-ups.</span>
            </a>
        </section>

        <section class="grid" style="grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr);">
            <article class="card">
                <header>
                    <h2>Recent Adoption Applications</h2>
                </header>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Applicant</th>
                                <th>Pet</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentApps)): ?>
                                <?php foreach ($recentApps as $app): ?>
                                    <tr>
                                        <td><?php echo $app['id']; ?></td>
                                        <td><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                                        <td><?php echo htmlspecialchars($app['pet_name'] ?? 'N/A'); ?></td>
                                        <td><span class="status <?php echo strtolower($app['status']); ?>"><?php echo ucfirst($app['status']); ?></span></td>
                                        <td><?php echo $app['created_at']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No recent applications found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
            <article class="card">
                <header>
                    <h2>Recent Pets</h2>
                </header>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Breed</th>
                                <th>Age</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recentPets)): ?>
                                <?php foreach ($recentPets as $pet): ?>
                                    <tr>
                                        <td><?php echo $pet['id']; ?></td>
                                        <td><?php echo htmlspecialchars($pet['name']); ?></td>
                                        <td><?php echo htmlspecialchars($pet['breed']); ?></td>
                                        <td><?php echo htmlspecialchars($pet['age']); ?></td>
                                        <td><span class="status <?php echo strtolower($pet['status']); ?>"><?php echo ucfirst($pet['status']); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No pets found.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <p class="footer-note">This admin workspace is restricted to shelter operations and adoption workflows. It does not permit admin creation, system configuration, or audit log access.</p>
    </div>
    <script>

function openPet(id,name,owner,penalty,status){

    document.getElementById("petTitle").textContent = name;
    document.getElementById("petOwner").textContent = owner;
    document.getElementById("petPenalty").textContent = penalty;

    const badge = document.getElementById("petStatus");

    badge.textContent = status;

    badge.className = "status-badge";

    if(status=="Claimed"){
        badge.style.background="#DCFCE7";
        badge.style.color="#15803D";
    }
    else if(status=="Expired"){
        badge.style.background="#FEE2E2";
        badge.style.color="#DC2626";
    }
    else if(status=="Posted"){
        badge.style.background="#DBEAFE";
        badge.style.color="#2563EB";
    }
    else{
        badge.style.background="#FEF3C7";
        badge.style.color="#D97706";
    }

    document.getElementById("petModal").style.display="flex";
}

function closePet(){

    document.getElementById("petModal").style.display="none";

}

</script>

</body>
</html>
