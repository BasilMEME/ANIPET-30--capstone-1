<?php
require_once __DIR__ . "/auth_helper.php";
require_super_or_permission('configure_system');

function fetchSingleValue($conn, $sql) {
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_row()) {
        return (int)$row[0];
    }
    return 0;
}

$stats = [
    'totalUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_deleted = 0"),
    'verifiedUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_verified = 1 AND is_deleted = 0"),
    'adminUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE role IN ('admin', 'super_admin', 'super') AND is_deleted = 0"),
    'availablePets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets WHERE status = 'available'"),
    'inAdoptionPets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets WHERE status = 'in_adoption'"),
    'adoptedPets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets WHERE status = 'adopted'"),
    'totalApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications"),
    'pendingApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'pending'"),
    'approvedApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'approved'"),
    'rejectedApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'rejected'"),
    'completedApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'completed'"),
    'readyPickup' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status IN ('ready_pickup', 'ready_for_pickup')"),
];

$trendMonths = [];
$trendApplications = [];
$startDate = new DateTime('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $trendMonths[] = $startDate->format('M Y');
    $monthParam = $conn->real_escape_string($startDate->format('Y-m'));
    $trendApplications[] = fetchSingleValue(
        $conn,
        "SELECT COUNT(*) FROM adoption_applications WHERE DATE_FORMAT(created_at, '%Y-%m') = '{$monthParam}'"
    );
    $startDate->modify('+1 month');
}

$appStatusData = [];
$statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM adoption_applications GROUP BY status");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $appStatusData[$row['status']] = (int)$row['total'];
    }
}

$recentApps = [];
$recentAppResult = $conn->query(
    "SELECT aa.id, aa.status, aa.created_at, p.name AS pet_name, u.full_name AS applicant
     FROM adoption_applications aa
     JOIN pets p ON aa.pet_id = p.id
     JOIN users u ON aa.user_id = u.id
     ORDER BY aa.created_at DESC
     LIMIT 6"
);
if ($recentAppResult) {
    while ($row = $recentAppResult->fetch_assoc()) {
        $recentApps[] = $row;
    }
}

$auditLogs = [];
$auditResult = $conn->query(
    "SELECT al.action_type, al.target_type, al.created_at, u.full_name AS actor_name
     FROM audit_logs al
     LEFT JOIN users u ON al.user_id = u.id
     ORDER BY al.created_at DESC
     LIMIT 8"
);
if ($auditResult) {
    while ($row = $auditResult->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Owner Dashboard</title>
    <link rel="icon" type="image/jpeg" href="images/anipet_logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f6f8fb;
            --surface: #ffffff;
            --surface-soft: #f8fafc;
            --text: #172033;
            --muted: #667085;
            --accent: #f2867e;
            --accent-dark: #d9695f;
            --border: #e7eaf0;
            --shadow: 0 14px 35px rgba(31, 41, 55, .08);
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        body.dark-mode {
            --bg: #0f172a;
            --surface: #111827;
            --surface-soft: #1f2937;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --border: #273244;
            --shadow: 0 14px 35px rgba(0, 0, 0, .25);
        }
        .layout {
            display: grid;
            grid-template-columns: 260px minmax(0, 1fr);
            min-height: 100vh;
        }
        .sidebar {
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
            padding: 22px 18px;
            background: var(--surface);
            border-right: 1px solid var(--border);
        }
        .brand { display: flex; align-items: center; gap: 11px; margin-bottom: 8px; }
        .brand img { width: 42px; height: 42px; border-radius: 10px; object-fit: cover; }
        .brand strong { font-size: 1.1rem; }
        .owner-note { margin: 0 0 22px; color: var(--muted); font-size: .85rem; line-height: 1.5; }
        .nav-title { margin: 20px 10px 8px; color: var(--muted); font-size: .72rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 12px;
            margin-bottom: 6px;
            border-radius: 12px;
            color: var(--text);
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
        }
        .nav-link:hover, .nav-link.active { background: rgba(242, 134, 126, .14); color: var(--accent-dark); }
        .sidebar-footer { margin-top: 24px; padding-top: 16px; border-top: 1px solid var(--border); display: grid; gap: 8px; }
        .small-btn {
            display: flex;
            justify-content: center;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--surface-soft);
            color: var(--text);
            text-decoration: none;
            cursor: pointer;
            font-weight: 700;
        }
        .logout { color: #dc2626; background: rgba(220, 38, 38, .07); }
        main { padding: 28px; min-width: 0; }
        .welcome {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            margin-bottom: 20px;
        }
        .welcome h1 { margin: 0 0 7px; font-size: clamp(1.6rem, 3vw, 2.2rem); }
        .welcome p { margin: 0; color: var(--muted); line-height: 1.55; }
        .role-badge { white-space: nowrap; background: rgba(242, 134, 126, .14); color: var(--accent-dark); padding: 9px 12px; border-radius: 999px; font-weight: 800; font-size: .8rem; }
        .section { margin-top: 22px; }
        .section-head { display: flex; justify-content: space-between; align-items: end; gap: 14px; margin-bottom: 12px; }
        .section-head h2 { margin: 0; font-size: 1.15rem; }
        .section-head p { margin: 4px 0 0; color: var(--muted); font-size: .88rem; }
        .grid { display: grid; gap: 14px; }
        .stats-grid { grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .two-col { grid-template-columns: minmax(0, 1.25fr) minmax(300px, .75fr); }
        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 18px;
            box-shadow: var(--shadow);
        }
        .stat strong { display: block; font-size: 1.8rem; margin-bottom: 7px; }
        .stat span { color: var(--muted); font-size: .86rem; }
        .quick-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .quick-card { display: flex; flex-direction: column; min-height: 155px; }
        .quick-card h3 { margin: 0 0 7px; font-size: 1rem; }
        .quick-card p { margin: 0 0 16px; color: var(--muted); line-height: 1.5; font-size: .86rem; flex: 1; }
        .quick-card a { color: var(--accent-dark); text-decoration: none; font-weight: 800; font-size: .87rem; }
        .chart-box { height: 280px; }
        .list { display: grid; gap: 10px; }
        .list-item { display: flex; justify-content: space-between; gap: 12px; padding: 12px; border-radius: 12px; background: var(--surface-soft); border: 1px solid var(--border); }
        .list-item span:first-child { color: var(--muted); }
        .list-item strong { text-align: right; font-size: .88rem; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 650px; }
        th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); font-size: .86rem; }
        th { color: var(--muted); text-transform: uppercase; letter-spacing: .04em; font-size: .72rem; }
        .status { display: inline-flex; padding: 6px 9px; border-radius: 999px; background: var(--surface-soft); border: 1px solid var(--border); font-weight: 700; font-size: .75rem; }
        details.card summary { cursor: pointer; font-weight: 800; }
        details.card > p { color: var(--muted); line-height: 1.55; }
        .tool-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 14px; }
        .btn { border: 0; border-radius: 12px; padding: 10px 13px; font-weight: 800; cursor: pointer; }
        .btn-primary { background: var(--accent); color: #291512; }
        .btn-secondary { background: var(--surface-soft); color: var(--text); border: 1px solid var(--border); }
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .two-col { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            .layout { grid-template-columns: 1fr; }
            .sidebar { position: relative; height: auto; }
            main { padding: 18px; }
            .welcome { flex-direction: column; }
            .stats-grid, .quick-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    <aside class="sidebar">
        <div class="brand">
            <img src="images/anipet_logo.jpg" alt="AniPet">
            <strong>AniPet Owner Panel</strong>
        </div>
        <p class="owner-note">Monitor the pound, manage staff, and access all daily operations from one place.</p>

        <div class="nav-title">Overview</div>
        <a class="nav-link active" href="#dashboard">Dashboard</a>
        <a class="nav-link" href="#statistics">Pound Statistics</a>

        <div class="nav-title">Pound Operations</div>
        <a class="nav-link" href="admin_workspace.php?page=pets">Pet Management</a>
        <a class="nav-link" href="admin_workspace.php?page=applications">Applications</a>
        <a class="nav-link" href="admin_workspace.php?page=appointments">Appointments</a>
        <a class="nav-link" href="admin_workspace.php?page=users">Users</a>
        <a class="nav-link" href="admin_workspace.php?page=pet_pound">Pet Pound</a>
        <a class="nav-link" href="admin_workspace.php?page=returns">Returns & Penalties</a>
        <a class="nav-link" href="admin_workspace.php?page=notifications">Notifications</a>
        <a class="nav-link" href="admin_workspace.php?page=reports">Reports</a>

        <div class="nav-title">Owner Management</div>
        <a class="nav-link" href="super_admin_actions.php">Staff Management</a>
        <a class="nav-link" href="super_admin_donations.php">Donation Management</a>
        <a class="nav-link" href="super_admin_activity.php">Activity History</a>
        <a class="nav-link" href="super_admin_settings.php">System Settings</a>

        <div class="nav-title">Protected Tools</div>
        <a class="nav-link" href="super_admin_security.php">Account Safety</a>
        <a class="nav-link" href="super_admin_database.php">Backup & Recovery</a>

        <div class="sidebar-footer">
            <button class="small-btn" id="themeToggle" type="button">Dark Mode</button>
            <a class="small-btn logout" href="logout.php" onclick="return confirm('Log out of Super Admin?')">Logout</a>
        </div>
    </aside>

    <main>
        <section id="dashboard">
            <div class="welcome">
                <div>
                    <h1>Pet Pound Overview</h1>
                    <p>See what is happening in AniPet and open any staff operation when you need to assist.</p>
                </div>
                <span class="role-badge">Pound Owner</span>
            </div>

            <div class="grid stats-grid">
                <div class="card stat"><strong><?= number_format($stats['availablePets']) ?></strong><span>Pets available for adoption</span></div>
                <div class="card stat"><strong><?= number_format($stats['inAdoptionPets']) ?></strong><span>Pets currently reserved</span></div>
                <div class="card stat"><strong><?= number_format($stats['pendingApplications']) ?></strong><span>Applications waiting for review</span></div>
                <div class="card stat"><strong><?= number_format($stats['readyPickup']) ?></strong><span>Pets ready for pickup</span></div>
                <div class="card stat"><strong><?= number_format($stats['completedApplications']) ?></strong><span>Completed adoptions</span></div>
                <div class="card stat"><strong><?= number_format($stats['adoptedPets']) ?></strong><span>Pets marked as adopted</span></div>
                <div class="card stat"><strong><?= number_format($stats['totalUsers']) ?></strong><span>Registered users</span></div>
                <div class="card stat"><strong><?= number_format($stats['adminUsers']) ?></strong><span>Staff and owner accounts</span></div>
            </div>
        </section>

        <section class="section" id="operations">
            <div class="section-head">
                <div><h2>Quick Actions</h2><p>The owner can use the same daily functions available to counter staff.</p></div>
            </div>
            <div class="grid quick-grid">
                <div class="card quick-card"><h3>Manage Pets</h3><p>Add new pets, edit details, update availability, and manage pet records.</p><a href="admin_workspace.php?page=pets">Open Pet Management →</a></div>
                <div class="card quick-card"><h3>Review Applications</h3><p>Check applications, update their progress, and assist with adoption processing.</p><a href="admin_workspace.php?page=applications">Open Applications →</a></div>
                <div class="card quick-card"><h3>Appointments</h3><p>View and manage scheduled visits and adoption appointments.</p><a href="admin_workspace.php?page=appointments">Open Appointments →</a></div>
                <div class="card quick-card"><h3>Pet Pound</h3><p>View impounded pets, claims, returns, penalties, and related records.</p><a href="admin_workspace.php?page=pet_pound">Open Pet Pound →</a></div>
                <div class="card quick-card"><h3>Manage Staff</h3><p>Create accounts, update permissions, reset passwords, and suspend staff accounts.</p><a href="super_admin_actions.php">Open Staff Management →</a></div>
                <div class="card quick-card"><h3>Donations</h3><p>Review donation records, verify submissions, and monitor incoming support.</p><a href="super_admin_donations.php">Open Donation Management →</a></div>
            </div>
        </section>

        <section class="section" id="statistics">
            <div class="section-head">
                <div><h2>Pound Statistics</h2><p>Simple figures that help the owner understand adoption activity.</p></div>
            </div>
            <div class="grid two-col">
                <div class="card">
                    <h3>Applications in the Last 6 Months</h3>
                    <div class="chart-box"><canvas id="adoptionTrendChart"></canvas></div>
                </div>
                <div class="card">
                    <h3>Current Application Status</h3>
                    <div class="chart-box"><canvas id="statusPieChart"></canvas></div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="grid two-col">
                <div class="card">
                    <div class="section-head"><div><h2>Recent Applications</h2><p>Latest adoption requests received by the pound.</p></div></div>
                    <div class="table-wrap">
                        <table>
                            <thead><tr><th>ID</th><th>Applicant</th><th>Pet</th><th>Status</th><th>Date</th></tr></thead>
                            <tbody>
                            <?php if ($recentApps): ?>
                                <?php foreach ($recentApps as $app): ?>
                                    <tr>
                                        <td><?= (int)$app['id'] ?></td>
                                        <td><?= htmlspecialchars($app['applicant']) ?></td>
                                        <td><?= htmlspecialchars($app['pet_name']) ?></td>
                                        <td><span class="status"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $app['status']))) ?></span></td>
                                        <td><?= date('M d, Y', strtotime($app['created_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5">No recent applications found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card">
                    <div class="section-head"><div><h2>Recent Staff Activity</h2><p>Important actions recorded in the system.</p></div></div>
                    <div class="list">
                        <?php if ($auditLogs): ?>
                            <?php foreach ($auditLogs as $event): ?>
                                <div class="list-item">
                                    <span><?= htmlspecialchars($event['actor_name'] ?: 'System') ?></span>
                                    <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $event['action_type']))) ?><br><small><?= date('M d, g:i A', strtotime($event['created_at'])) ?></small></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="list-item"><span>No recent activity found.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="section-head"><div><h2>Owner Tools</h2><p>Existing Super Admin functions remain available using clearer labels.</p></div></div>
            <div class="grid quick-grid">
                <div class="card quick-card"><h3>Activity History</h3><p>See who logged in and what changes were made in the system.</p><a href="super_admin_activity.php">View Activity History →</a></div>
                <div class="card quick-card"><h3>System Settings</h3><p>Manage pound information, email, notifications, and other system options.</p><a href="super_admin_settings.php">Open System Settings →</a></div>
                <div class="card quick-card"><h3>Account Safety</h3><p>Review staff permissions, password rules, and account protection options.</p><a href="super_admin_security.php">Open Account Safety →</a></div>
                <div class="card quick-card"><h3>Backup & Recovery</h3><p>Create or restore a system backup when maintenance is required.</p><a href="super_admin_database.php">Open Backup & Recovery →</a></div>
                <div class="card quick-card"><h3>Adoption Monitoring</h3><p>Review the overall movement and progress of adoption applications.</p><a href="super_admin_monitoring.php">Open Adoption Monitoring →</a></div>
                <div class="card quick-card"><h3>User Accounts</h3><p>Review adopter accounts and perform owner-level account actions.</p><a href="super_admin_control_panel.php">Open User Accounts →</a></div>
            </div>
        </section>

        <section class="section">
            <details class="card">
                <summary>Reports and system alerts</summary>
                <p>This keeps the existing alert and scheduled-report tools available without placing technical controls on the main dashboard.</p>
                <div id="alertSummary" class="list"><div class="list-item"><span>Loading current alerts...</span></div></div>
                <div class="tool-actions">
                    <button class="btn btn-primary" id="refreshAlertsBtn" type="button">Refresh Alerts</button>
                    <button class="btn btn-secondary" id="sendAlertNotificationBtn" type="button">Send Alert Notification</button>
                    <button class="btn btn-secondary" id="runDueReportsBtn" type="button">Run Due Reports</button>
                </div>
            </details>
        </section>
    </main>
</div>

<script>
    const labels = <?= json_encode($trendMonths) ?>;
    const applicationData = <?= json_encode($trendApplications) ?>;
    const statusLabels = <?= json_encode(array_keys($appStatusData)) ?>;
    const statusData = <?= json_encode(array_values($appStatusData)) ?>;

    new Chart(document.getElementById('adoptionTrendChart'), {
        type: 'line',
        data: { labels, datasets: [{ data: applicationData, borderColor: '#f2867e', backgroundColor: 'rgba(242,134,126,.18)', fill: true, tension: .35 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
    });

    new Chart(document.getElementById('statusPieChart'), {
        type: 'doughnut',
        data: { labels: statusLabels.map(v => v.replaceAll('_', ' ')), datasets: [{ data: statusData, backgroundColor: ['#f2867e','#60a5fa','#34d399','#fbbf24','#a78bfa','#fb7185','#94a3b8'], borderWidth: 0 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
    });

    const themeToggle = document.getElementById('themeToggle');
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        themeToggle.textContent = document.body.classList.contains('dark-mode') ? 'Light Mode' : 'Dark Mode';
    });

    const apiEndpoint = 'super_admin_api.php';
    const alertSummary = document.getElementById('alertSummary');

    async function loadAlertItems() {
        try {
            const response = await fetch(apiEndpoint + '?action=get_alert_items');
            const data = await response.json();
            if (!data.success || !Array.isArray(data.alerts) || !data.alerts.length) {
                alertSummary.innerHTML = '<div class="list-item"><span>No active alerts.</span></div>';
                return;
            }
            alertSummary.innerHTML = data.alerts.map(item => `
                <div class="list-item">
                    <span>${item.label}</span>
                    <strong>${item.value}${item.active ? ' — Needs attention' : ''}</strong>
                </div>
            `).join('');
        } catch (error) {
            alertSummary.innerHTML = '<div class="list-item"><span>Unable to load alerts.</span></div>';
        }
    }

    async function postAction(action) {
        try {
            const response = await fetch(apiEndpoint, { method: 'POST', body: new URLSearchParams({ action }) });
            const data = await response.json();
            alert(data.message || 'Action completed.');
            loadAlertItems();
        } catch (error) {
            alert('The action could not be completed.');
        }
    }

    document.getElementById('refreshAlertsBtn').addEventListener('click', loadAlertItems);
    document.getElementById('sendAlertNotificationBtn').addEventListener('click', () => postAction('send_alert_notification'));
    document.getElementById('runDueReportsBtn').addEventListener('click', () => {
        if (confirm('Run all reports that are currently due?')) postAction('run_due_reports');
    });

    loadAlertItems();
</script>
</body>
</html>