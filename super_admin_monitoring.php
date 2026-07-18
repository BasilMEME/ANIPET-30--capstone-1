<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_applications');

function fetchScalar(mysqli $conn, string $sql) {
    $result = $conn->query($sql);
    return $result && ($row = $result->fetch_row()) ? intval($row[0]) : 0;
}

$statusCounts = [];
$statusResult = $conn->query("SELECT status, COUNT(*) AS count FROM adoption_applications GROUP BY status ORDER BY FIELD(status, 'pending', 'screening', 'approved', 'for_releasing', 'ready_pickup', 'completed', 'rejected')");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $statusCounts[$row['status']] = intval($row['count']);
    }
}

$topShelters = [];
$shelterSql = "SELECT s.name, COUNT(p.id) AS pets, SUM(CASE WHEN p.status = 'adopted' THEN 1 ELSE 0 END) AS adopted FROM shelters s LEFT JOIN pets p ON p.shelter_id = s.id GROUP BY s.id ORDER BY adopted DESC LIMIT 8";
$shelterResult = $conn->query($shelterSql);
if ($shelterResult) {
    while ($row = $shelterResult->fetch_assoc()) {
        $topShelters[] = $row;
    }
}

$recentApplications = [];
$appSql = "SELECT aa.id, aa.pet_id, aa.user_id, aa.applicant_name, aa.status, aa.admin_notes, aa.created_at, p.name AS pet_name, u.full_name AS applicant FROM adoption_applications aa LEFT JOIN pets p ON aa.pet_id = p.id LEFT JOIN users u ON aa.user_id = u.id ORDER BY aa.created_at DESC LIMIT 10";
$appResult = $conn->query($appSql);
if ($appResult) {
    while ($row = $appResult->fetch_assoc()) {
        $recentApplications[] = $row;
    }
}

$stalledCount = fetchScalar($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status IN ('pending','screening') AND DATEDIFF(NOW(), created_at) >= 14");
$unassignedCount = fetchScalar($conn, "SELECT COUNT(*) FROM adoption_applications WHERE screened_by IS NULL AND status IN ('pending','screening')");
$completedCount = fetchScalar($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'completed'");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Adoption Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            color-scheme: dark;
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.92);
            --text: #f8fafc;
            --muted: #94a3b8;
            --accent: #F2867E;
            --border: rgba(148, 163, 184, 0.16);
            --shadow: 0 18px 45px rgba(2, 8, 23, 0.35);
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; color: var(--text); min-height: 100vh; background: radial-gradient(circle at top left, rgba(242,134,126,.14), transparent 25%), radial-gradient(circle at bottom right, rgba(246,201,160,.12), transparent 24%), linear-gradient(135deg, #020617 0%, #07111f 100%); }
        .wrapper { max-width: 1400px; margin: 0 auto; padding: 24px 24px 40px; }
        .header { display:flex; flex-wrap:wrap; justify-content:space-between; gap:16px; align-items:center; margin-bottom:24px; padding:18px 20px; background:rgba(15,23,42,.72); border:1px solid var(--border); border-radius:24px; box-shadow:var(--shadow); backdrop-filter:blur(14px); }
        .header h1 { margin:0; font-size:clamp(1.4rem,2vw,2rem); }
        .header p { margin:0; color:var(--muted); line-height:1.6; }
        .grid { display:grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap:18px; margin-bottom:24px; }
        .grid.kpi { grid-template-columns: repeat(3,minmax(0,1fr)); }
        .card { background: var(--panel); border:1px solid var(--border); border-radius:22px; padding:22px; box-shadow:var(--shadow); backdrop-filter:blur(12px); transition:transform .18s ease,border-color .18s ease; }
        .card:hover { transform:translateY(-1px); border-color:rgba(242,134,126,.24); }
        .card h2 { margin:0 0 14px; font-size:1.05rem; color:#cbd5e1; }
        .card canvas { max-height: 280px; }
        .metric { font-size:2.2rem; font-weight:700; margin-bottom:8px; }
        .metric-label { color:var(--muted); line-height:1.6; }
        .table-wrap { overflow-x:auto; border:1px solid rgba(148,163,184,.12); border-radius:18px; background:rgba(255,255,255,.025); }
        table { width:100%; border-collapse:collapse; color:var(--text); table-layout:fixed; min-width:720px; }
        th, td { padding:12px 14px; border-bottom:1px solid rgba(148,163,184,.12); text-align:left; overflow-wrap:anywhere; word-break:break-word; }
        th { color:var(--muted); font-size:.82rem; text-transform:uppercase; letter-spacing:.04em; }
        tr:hover { background:rgba(242,134,126,.08); }
        .pill { display:inline-flex; align-items:center; padding:6px 10px; border-radius:999px; font-size:.82rem; font-weight:700; background:rgba(255,255,255,.06); }
        .pill.pending { color:#facc15; }
        .pill.screening { color:#38bdf8; }
        .pill.approved, .pill.ready_pickup, .pill.for_releasing { color:#22c55e; }
        .pill.completed { color:#34d399; }
        .pill.rejected { color:#fb7185; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:10px; }
        .button { color:var(--text); background:rgba(255,255,255,.08); border:1px solid rgba(148,163,184,.16); border-radius:14px; text-decoration:none; padding:12px 18px; transition:transform .18s ease,background .18s ease,box-shadow .18s ease; }
        .button:hover { transform:translateY(-1px); background:rgba(255,255,255,.12); box-shadow:0 8px 20px rgba(2,8,23,.22); }
        @media (max-width: 1100px) { .grid { grid-template-columns: 1fr; } .grid.kpi { grid-template-columns: repeat(2,minmax(0,1fr)); } }
        @media (max-width: 760px) { .wrapper{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} .grid{grid-template-columns:1fr;} .grid.kpi{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div>
            <h1>Adoption Monitoring</h1>
            <p>Super Admin monitoring of adoption pipeline, approvals, and application risk signals.</p>
        </div>
        <div class="actions">
            <a class="button" href="super_admin_dashboard.php">Dashboard</a>
            <a class="button" href="super_admin_actions.php">Actions</a>
        </div>
    </div>

    <div class="grid kpi">
        <div class="card">
            <h2>Pending Applications</h2>
            <div class="metric"><?php echo $statusCounts['pending'] ?? 0; ?></div>
            <div class="metric-label">Applications waiting for review</div>
        </div>
        <div class="card">
            <h2>Stalled Applications (14+ days)</h2>
            <div class="metric"><?php echo $stalledCount; ?></div>
            <div class="metric-label">Potential backlog requiring follow-up</div>
        </div>
        <div class="card">
            <h2>Unassigned Screening</h2>
            <div class="metric"><?php echo $unassignedCount; ?></div>
            <div class="metric-label">Applications with no assigned reviewer</div>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Application Status Distribution</h2>
            <canvas id="statusChart" height="180"></canvas>
        </div>
        <div class="card">
            <h2>Completed Adoptions</h2>
            <div class="metric"><?php echo $completedCount; ?></div>
            <div class="metric-label">Applications marked completed</div>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Top Performing Shelters</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Shelter</th><th>Pets</th><th>Adopted</th></tr></thead>
                    <tbody>
                    <?php foreach ($topShelters as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo intval($row['pets']); ?></td>
                            <td><?php echo intval($row['adopted']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <h2>Recent Applications</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Pet</th><th>Applicant</th><th>Status</th><th>Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentApplications as $app): ?>
                        <tr>
                            <td><?php echo intval($app['id']); ?></td>
                            <td><?php echo htmlspecialchars($app['pet_name'] ?? 'Unknown'); ?></td>
                            <td><?php echo htmlspecialchars($app['applicant']); ?></td>
                            <td><span class="pill <?php echo htmlspecialchars($app['status']); ?>"><?php echo ucfirst($app['status']); ?></span></td>
                            <td><?php echo htmlspecialchars($app['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
    const statusData = <?php echo json_encode(array_values($statusCounts)); ?>;
    const statusLabels = <?php echo json_encode(array_keys($statusCounts)); ?>;
    const colors = statusLabels.map((status) => {
        switch (status) {
            case 'pending': return '#facc15';
            case 'screening': return '#38bdf8';
            case 'approved': return '#22c55e';
            case 'for_releasing': return '#60a5fa';
            case 'ready_pickup': return '#7c3aed';
            case 'completed': return '#34d399';
            case 'rejected': return '#fb7185';
            default: return '#94a3b8';
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusData,
                backgroundColor: colors,
                borderColor: '#0f172a',
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#cbd5e1' } },
            }
        }
    });
</script>
</body>
</html>
