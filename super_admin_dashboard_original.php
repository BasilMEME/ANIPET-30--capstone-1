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
    'totalUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users"),
    'verifiedUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_verified = 1"),
    'adminUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE role IN ('admin', 'super_admin', 'super')"),
    'suspendedUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_suspended = 1"),
    'deletedUsers' => fetchSingleValue($conn, "SELECT COUNT(*) FROM users WHERE is_deleted = 1"),
    'totalPets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets"),
    'availablePets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets WHERE status = 'available'"),
    'inAdoptionPets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets WHERE status = 'in_adoption'"),
    'adoptedPets' => fetchSingleValue($conn, "SELECT COUNT(*) FROM pets WHERE status = 'adopted'"),
    'totalApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications"),
    'pendingApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'pending'"),
    'approvedApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'approved'"),
    'rejectedApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'rejected'"),
    'completedApplications' => fetchSingleValue($conn, "SELECT COUNT(*) FROM adoption_applications WHERE status = 'completed'"),
    'totalShelters' => fetchSingleValue($conn, "SELECT COUNT(*) FROM shelters"),
    'activeShelters' => fetchSingleValue($conn, "SELECT COUNT(*) FROM shelters WHERE status = 'active'"),
];

// Monthly adoption and user growth trends
$trendMonths = [];
$trendApplications = [];
$trendUsers = [];
$startDate = new DateTime('first day of -5 months');
for ($i = 0; $i < 6; $i++) {
    $trendMonths[] = $startDate->format('M Y');
    $monthParam = $startDate->format('Y-m');
    $applicationsSql = "SELECT COUNT(*) FROM adoption_applications WHERE DATE_FORMAT(created_at, '%Y-%m') = '" . $conn->real_escape_string($monthParam) . "'";
    $usersSql = "SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at, '%Y-%m') = '" . $conn->real_escape_string($monthParam) . "'";
    $trendApplications[] = fetchSingleValue($conn, $applicationsSql);
    $trendUsers[] = fetchSingleValue($conn, $usersSql);
    $startDate->modify('+1 month');
}

// Application status distribution
$appStatusData = [];
$statusResult = $conn->query("SELECT status, COUNT(*) AS total FROM adoption_applications GROUP BY status");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $appStatusData[$row['status']] = (int)$row['total'];
    }
}

// Most adopted breeds
$topBreeds = [];
$breedResult = $conn->query("SELECT p.breed, COUNT(*) AS total FROM adoption_applications aa JOIN pets p ON aa.pet_id = p.id WHERE aa.status = 'completed' GROUP BY p.breed ORDER BY total DESC LIMIT 6");
if ($breedResult) {
    while ($row = $breedResult->fetch_assoc()) {
        $topBreeds[] = ['breed' => $row['breed'] ?: 'Unknown', 'count' => (int)$row['total']];
    }
}

if (empty($topBreeds)) {
    $topBreeds[] = ['breed' => 'No completed adoptions yet', 'count' => 0];
}

$recentApps = [];
$recentAppResult = $conn->query("SELECT aa.id, aa.applicant_name, aa.status, aa.created_at, p.name AS pet_name, u.full_name AS applicant FROM adoption_applications aa JOIN pets p ON aa.pet_id = p.id JOIN users u ON aa.user_id = u.id ORDER BY aa.created_at DESC LIMIT 6");
if ($recentAppResult) {
    while ($row = $recentAppResult->fetch_assoc()) {
        $recentApps[] = $row;
    }
}

$recentUsers = [];
$recentUserResult = $conn->query("SELECT id, full_name, email, role, is_verified, created_at FROM users ORDER BY created_at DESC LIMIT 6");
if ($recentUserResult) {
    while ($row = $recentUserResult->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

$roleCounts = [];
$roleResult = $conn->query("SELECT role, COUNT(*) AS total FROM users GROUP BY role");
if ($roleResult) {
    while ($row = $roleResult->fetch_assoc()) {
        $roleCounts[] = $row;
    }
}

$auditLogs = [];
$auditResult = $conn->query("SELECT al.id, al.action_type, al.target_type, al.target_id, al.details, al.ip_address, al.created_at, u.full_name AS actor_name FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 20");
if ($auditResult) {
    while ($row = $auditResult->fetch_assoc()) {
        $auditLogs[] = $row;
    }
}

$shelterPerformance = [];
$shelterResult = $conn->query("SELECT s.id, s.name, COUNT(p.id) AS total_pets, SUM(CASE WHEN p.status = 'adopted' THEN 1 ELSE 0 END) AS adopted_pets, SUM(CASE WHEN aa.status = 'completed' THEN 1 ELSE 0 END) AS completed_adoptions FROM shelters s LEFT JOIN pets p ON p.shelter_id = s.id LEFT JOIN adoption_applications aa ON aa.pet_id = p.id AND aa.status = 'completed' GROUP BY s.id ORDER BY completed_adoptions DESC LIMIT 10");
if ($shelterResult) {
    while ($row = $shelterResult->fetch_assoc()) {
        $shelterPerformance[] = $row;
    }
}

$settings = [];
$settingsResult = $conn->query("SELECT setting_key, setting_value, description, updated_at FROM system_settings ORDER BY setting_key ASC");
if ($settingsResult) {
    while ($row = $settingsResult->fetch_assoc()) {
        $settings[] = $row;
    }
}

// Database monitoring summary
$dbHealth = [];
$tableStatus = $conn->query("SHOW TABLE STATUS WHERE `Name` IN ('users','pets','adoption_applications','return_requests','adoption_records','shelters','system_settings','audit_logs')");
if ($tableStatus) {
    while ($row = $tableStatus->fetch_assoc()) {
        $dbHealth[] = [
            'table' => $row['Name'],
            'rows' => (int)$row['Rows'],
            'sizeMB' => round(((int)$row['Data_length'] + (int)$row['Index_length']) / 1024 / 1024, 2),
        ];
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin</title>
    <link rel="icon" type="image/jpeg" href="images/anipet_logo.jpg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-alt: #f1f5f9;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --accent: #F2867E;
            --accent-soft: rgba(242, 134, 126, 0.12);
            --border: rgba(15, 23, 42, 0.1);
            --shadow: 0 20px 50px rgba(15, 23, 42, 0.15);
        }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            min-height: 100%;
            height: 100%;
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            color: var(--text);
            overflow: hidden;
        }
        body.dark-mode {
            --bg: #0f172a;
            --surface: #111827;
            --surface-alt: #1f2937;
            --card: #111827;
            --text: #e2e8f0;
            --muted: #94a3b8;
            --border: rgba(148, 163, 184, 0.18);
            --shadow: 0 20px 50px rgba(15, 23, 42, 0.35);
            background: radial-gradient(circle at top left, rgba(30, 58, 138, 0.2), transparent 28%),
                        radial-gradient(circle at bottom right, rgba(20, 184, 166, 0.14), transparent 24%),
                        linear-gradient(180deg, #020617 0%, #090e24 100%);
        }
        .page {
            display: grid;
            grid-template-columns: minmax(220px, 270px) minmax(0, 1fr);
            gap: 16px;
            padding: 16px;
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }
        .sidebar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 20px;
            box-shadow: var(--shadow);
            position: relative;
            top: 0;
            align-self: stretch;
            max-height: calc(100vh - 32px);
            overflow-y: auto;
        }
        .sidebar h2 { margin: 0 0 10px; font-size: 1.35rem; color: var(--text); }
        .sidebar p { margin: 0 0 16px; color: var(--muted); line-height: 1.65; font-size: 0.95rem; }
        .nav-link { display: block; padding: 12px 14px; border-radius: 14px; margin-bottom: 10px; text-decoration: none; color: var(--text); background: rgba(242, 134, 126, 0.08); transition: transform .18s ease, background .18s ease; font-size: 0.95rem; }
        .nav-link:hover { transform: translateX(4px); background: rgba(242, 134, 126, 0.14); }
        .nav-link.active { background: rgba(242, 134, 126, 0.18); border-left: 3px solid var(--accent); color: var(--text); }
        .main {
            display: grid;
            gap: 14px;
            min-width: 0;
            overflow-y: auto;
            overflow-x: hidden;
            padding-right: 4px;
            scrollbar-gutter: stable;
        }
        .panel { background: var(--surface); border: 1px solid var(--border); border-radius: 24px; padding: 20px; box-shadow: var(--shadow); }
        .panel header { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 16px; }
        .panel header h3 { margin: 0; font-size: 1.06rem; letter-spacing: -.02em; }
        .panel header span { color: var(--muted); font-size: .9rem; }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
        .kpi-card { background: var(--surface-alt); border: 1px solid var(--border); border-radius: 18px; padding: 16px; color: var(--text); }
        .kpi-card strong { display: block; font-size: 1.7rem; margin-bottom: 6px; }
        .kpi-card small { color: var(--muted); }
        .actions-grid { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .action-card { background: var(--surface-alt); border: 1px solid var(--border); border-radius: 18px; padding: 16px; transition: transform .18s ease, border-color .18s ease; }
        .action-card:hover { transform: translateY(-1px); border-color: rgba(242, 134, 126, 0.22); }
        .action-card h4 { margin: 0 0 8px; font-size: 1rem; color: var(--text); }
        .action-card p { margin: 0 0 12px; color: var(--muted); line-height: 1.6; font-size: 0.92rem; }
        .action-card a { display: inline-flex; align-items: center; gap: 8px; color: #38bdf8; text-decoration: none; font-weight: 600; }
        .chart-grid { display: grid; grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr); gap: 16px; align-items: start; }
        .chart-grid .panel { padding: 18px; }
        .chart-grid canvas { width: 100% !important; height: clamp(220px, 24vh, 280px) !important; }
        .table-grid { display: grid; gap: 16px; }
        .table-wrap { overflow-x: auto; }
        .data-table { width: 100%; min-width: 540px; border-collapse: collapse; color: var(--text); }
        .data-table th, .data-table td { padding: 12px 10px; text-align: left; border-bottom: 1px solid var(--border); overflow-wrap: anywhere; word-break: break-word; }
        .data-table th { color: var(--muted); font-size: 0.9rem; text-transform: uppercase; letter-spacing: .04em; }
        .tag { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: .8rem; font-weight: 600; background: rgba(242, 134, 126, 0.12); color: var(--text); }
        .tag.pending { color: #facc15; }
        .tag.approved { color: #22c55e; }
        .tag.rejected { color: #fb7185; }
        .tag.completed { color: #38bdf8; }
        .tag.user { background: rgba(59, 130, 246, 0.12); }
        .tag.admin { background: rgba(16, 185, 129, 0.12); }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 14px; border: none; cursor: pointer; font-weight: 700; transition: transform .18s ease, background .18s ease, box-shadow .18s ease; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 8px 20px rgba(2, 8, 23, .22); }
        .btn-primary { background: linear-gradient(135deg, var(--accent), #D9695F); color: #241209; }
        .btn-secondary { background: var(--surface-alt); color: var(--text); border: 1px solid var(--border); }
        .theme-toggle { border: 1px solid var(--border); background: var(--surface-alt); color: var(--text); padding: 10px 14px; border-radius: 999px; cursor: pointer; transition: background .2s ease; }
        .theme-toggle:hover { background: rgba(242, 134, 126, 0.14); }
        .status-pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: 0.8rem; font-weight: 700; background: rgba(242, 134, 126, 0.12); color: var(--text); }
        .status-pill.pending { color: #facc15; }
        .status-pill.approved { color: #22c55e; }
        .status-pill.rejected { color: #fb7185; }
        .status-pill.completed { color: #38bdf8; }
        .status-pill.screening, .status-pill.for_releasing, .status-pill.ready_pickup { color: #60a5fa; }
        .metric-list { display: grid; gap: 10px; }
        .metric-item { display: flex; justify-content: space-between; padding: 12px 14px; border-radius: 16px; background: var(--surface-alt); border: 1px solid var(--border); gap: 10px; color: var(--text); }
        .metric-item span:first-child { color: var(--muted); }
        .metric-item span:last-child { font-weight: 700; }
        .overview { display: grid; gap: 12px; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); }
        .brand { display: grid; gap: 8px; }
        .brand h2 { margin: 0; font-size: 1.4rem; color: var(--text); }
        .brand p { color: var(--muted); max-width: 520px; line-height: 1.6; font-size: 0.95rem; }
        .data-table tbody tr:hover { background: rgba(242, 134, 126, 0.08); }
        @media (max-width: 1200px) {
            .page { grid-template-columns: 1fr; height: auto; max-height: none; overflow: auto; }
            .sidebar { max-height: none; }
            .main { overflow: visible; padding-right: 0; }
            .chart-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            html, body { overflow: auto; }
            .page { padding: 12px; gap: 12px; }
            .sidebar { padding: 16px; }
            .panel { padding: 16px; }
            .kpi-grid { grid-template-columns: 1fr; }
            .overview, .actions-grid { grid-template-columns: 1fr; }
            .data-table { min-width: 480px; }
        }
    </style>
</head>
<body>
<div class="page">
    <aside class="sidebar">
        <div class="brand">
            <div style="display:flex;align-items:center;gap:10px;">
                <img src="images/anipet_logo.jpg" alt="AniPet" style="width:40px;height:40px;object-fit:contain;border-radius:8px;flex-shrink:0;">
                <h2>AniPet Super Admin</h2>
            </div>
            <p>Executive control center for system health, adoption performance, user growth, and audit monitoring.</p>
        </div>
        <a class="nav-link active" data-target="overview" href="#overview">Dashboard Overview</a>
        <a class="nav-link" data-target="analytics" href="#analytics">Analytics</a>
        <a class="nav-link" data-target="management" href="#management">Management</a>
        <a class="nav-link" data-target="audit" href="#audit">Audit Logs</a>
        <a class="nav-link" data-target="security" href="#security">Security</a>
        <a class="nav-link" data-target="database" href="#database">Database</a>
        <a class="nav-link" href="super_admin_database.php">DB Monitor</a>

        <div style="padding-top:16px;border-top:1px solid var(--border);margin-top:16px;">
            <div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;background:rgba(242,134,126,.08);margin-bottom:12px;">
                <div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,#F2867E,#1B2A41);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0;">S</div>
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.83rem;font-weight:600;color:var(--text);">Super Admin</div>
                    <div style="font-size:.68rem;color:var(--muted);">System Administrator</div>
                </div>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="theme-toggle" id="themeToggle" style="flex:1;font-size:.82rem;padding:8px 10px;">Theme</button>
                <a href="logout.php"
                   onclick="return confirm('Log out of Super Admin?')"
                   style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:5px;padding:8px 10px;border-radius:999px;background:rgba(239,68,68,.12);color:#ef4444;text-decoration:none;font-weight:600;font-size:.82rem;border:1px solid rgba(239,68,68,.2);transition:background .2s;"
                   onmouseover="this.style.background='rgba(239,68,68,.22)'"
                   onmouseout="this.style.background='rgba(239,68,68,.12)'">
                    Logout
                </a>
            </div>
        </div>
    </aside>

    <main class="main">
        <section class="panel" id="overview">
            <header>
                <div>
                    <h3>Executive Dashboard</h3>
                    <span>Premium control panel for Super Admin operations and system monitoring.</span>
                </div>
                <span class="tag admin">Role: Super Admin</span>
            </header>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <strong><?php echo number_format($stats['totalUsers']); ?></strong>
                    <small>Total Users</small>
                </div>
                <div class="kpi-card">
                    <strong><?php echo number_format($stats['adminUsers']); ?></strong>
                    <small>Admin & Super Admin Accounts</small>
                </div>
                <div class="kpi-card">
                    <strong><?php echo number_format($stats['totalPets']); ?></strong>
                    <small>Pet Records</small>
                </div>
                <div class="kpi-card">
                    <strong><?php echo number_format($stats['totalApplications']); ?></strong>
                    <small>Adoption Submissions</small>
                </div>
            </div>
        </section>

        <section class="panel overview">
            <div class="metric-item">
                <span>Verified users</span>
                <span><?php echo number_format($stats['verifiedUsers']); ?></span>
            </div>
            <div class="metric-item">
                <span>Available pets</span>
                <span><?php echo number_format($stats['availablePets']); ?></span>
            </div>
            <div class="metric-item">
                <span>In adoption</span>
                <span><?php echo number_format($stats['inAdoptionPets']); ?></span>
            </div>
            <div class="metric-item">
                <span>Adopted pets</span>
                <span><?php echo number_format($stats['adoptedPets']); ?></span>
            </div>
            <div class="metric-item">
                <span>Pending applications</span>
                <span><?php echo number_format($stats['pendingApplications']); ?></span>
            </div>
            <div class="metric-item">
                <span>Completed adoptions</span>
                <span><?php echo number_format($stats['completedApplications']); ?></span>
            </div>
            <div class="metric-item">
                <span>Suspended users</span>
                <span><?php echo number_format($stats['suspendedUsers']); ?></span>
            </div>
            <div class="metric-item">
                <span>Deleted users</span>
                <span><?php echo number_format($stats['deletedUsers']); ?></span>
            </div>
        </section>

        <section class="panel" id="analytics">
            <header>
                <div>
                    <h3>Analytics & Trends</h3>
                    <span>Monthly adoption momentum, user growth, and top-performing breed insights.</span>
                </div>
            </header>
            <div class="chart-grid">
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Adoption Trend (6 months)</h4>
                    <canvas id="adoptionTrendChart" height="250"></canvas>
                </div>
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Application Status</h4>
                    <canvas id="statusPieChart" height="250"></canvas>
                </div>
            </div>
            <div class="chart-grid" style="margin-top:24px;">
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">User Growth</h4>
                    <canvas id="userGrowthChart" height="220"></canvas>
                </div>
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Most Adopted Breeds</h4>
                    <div class="metric-list">
                        <?php foreach ($topBreeds as $breed): ?>
                            <div class="metric-item">
                                <span><?php echo htmlspecialchars($breed['breed']); ?></span>
                                <span><?php echo number_format($breed['count']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel" id="shelter-performance">
            <header>
                <div>
                    <h3>Shelter Performance</h3>
                    <span>Top shelters by completed adoptions and pet impact.</span>
                </div>
            </header>
            <div class="table-grid">
                <div class="panel" style="padding:22px;">
                    <div class="table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr><th>Shelter</th><th>Pets</th><th>Adopted</th><th>Completed Adoptions</th></tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($shelterPerformance)): ?>
                                    <?php foreach ($shelterPerformance as $shelter): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($shelter['name']); ?></td>
                                            <td><?php echo number_format($shelter['total_pets']); ?></td>
                                            <td><?php echo number_format($shelter['adopted_pets']); ?></td>
                                            <td><?php echo number_format($shelter['completed_adoptions']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4">No shelter performance data available.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel" id="management">
            <header>
                <div>
                    <h3>Management Hub</h3>
                    <span>Active Super Admin controls for accounts, pets, and system configuration.</span>
                </div>
            </header>
            <div class="actions-grid">
                <div class="action-card">
                    <h4>Admin Management</h4>
                    <p>Create, update, suspend, reset passwords, and assign permissions to admin accounts.</p>
                    <a href="super_admin_actions.php">Open Admin Console</a>
                </div>
                <div class="action-card">
                    <h4>User Governance</h4>
                    <p>Review all users, suspend or restore accounts, and monitor activity with audit logs.</p>
                    <a href="super_admin_control_panel.php">Open User Management</a>
                </div>
                <div class="action-card">
                    <h4>Pet Master Records</h4>
                    <p>Transfer pets between shelters, archive records, and maintain adoption inventory.</p>
                    <a href="super_admin_actions.php">Open Pet Manager</a>
                </div>
                <div class="action-card">
                    <h4>System Configuration</h4>
                    <p>Update site settings, shelter contact details, email options, and notifications.</p>
                    <a href="super_admin_settings.php">Open System Settings</a>
                </div>
                <div class="action-card">
                    <h4>Security & Permissions</h4>
                    <p>Review role permissions, password policy, session controls, and security events.</p>
                    <a href="super_admin_security.php">Open Security Console</a>
                </div>
                <div class="action-card">
                    <h4>Database Monitoring</h4>
                    <p>Inspect database table health, backups, and restore history from a dedicated console.</p>
                    <a href="super_admin_database.php">Open DB Monitor</a>
                </div>
                <div class="action-card">
                    <h4>Activity Logs</h4>
                    <p>Inspect login history, audit trails, and recorded system events.</p>
                    <a href="super_admin_activity.php">Open Activity Logs</a>
                </div>
                <div class="action-card">
                    <h4>Adoption Monitoring</h4>
                    <p>Track application flow, adoption progress, and pending reviews in one place.</p>
                    <a href="super_admin_monitoring.php">Open Monitoring</a>
                </div>
                <div class="action-card">
                    <h4>Donation Management</h4>
                    <p>View donations, verify payments, approve or reject submissions, and monitor donation statistics.</p>
                    <a href="super_admin_donations.php">Open Donation Manager</a>
                </div>
                <div class="action-card">
                    <h4>Pet Pound</h4>
                    <p>Manage pets impounded due to a penalty (48-hour claim grace period) and view deceased pet records.</p>
                    <a href="super_admin_pet_pound.php">Open Pet Pound</a>
                </div>
            </div>
        </section>

        <section class="panel" id="audit">
            <header>
                <div>
                    <h3>Audit & Activity Log</h3>
                    <span>Recent application events and system activity insights.</span>
                </div>
            </header>
            <div class="table-grid">
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Recent Applications</h4>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Applicant</th><th>Pet</th><th>Status</th><th>Date</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($recentApps)): ?>
                            <?php foreach ($recentApps as $app): ?>
                                <tr>
                                    <td><?php echo $app['id']; ?></td>
                                    <td><?php echo htmlspecialchars($app['applicant']); ?></td>
                                    <td><?php echo htmlspecialchars($app['pet_name']); ?></td>
                                    <td><span class="status-pill <?php echo htmlspecialchars($app['status']); ?>"><?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No application activity available.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Recent User Signups</h4>
                    <table class="data-table">
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Role</th><th>Verified</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($recentUsers)): ?>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['role']); ?></td>
                                    <td><span class="tag <?php echo $user['is_verified'] ? 'approved' : 'pending'; ?>"><?php echo $user['is_verified'] ? 'Yes' : 'No'; ?></span></td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5">No recent users found.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="panel" id="security">
            <header>
                <div>
                    <h3>Security & Role Governance</h3>
                    <span>Monitor account status, role distribution, and recent audit events.</span>
                </div>
            </header>
            <div class="chart-grid">
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Role Distribution</h4>
                    <table class="data-table">
                        <thead>
                            <tr><th>Role</th><th>Count</th></tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($roleCounts)): ?>
                                <?php foreach ($roleCounts as $role): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($role['role']); ?></td>
                                        <td><?php echo number_format($role['total']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2">No role data available.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Recent Audit Events</h4>
                    <div class="metric-list">
                        <?php if (!empty($auditLogs)): ?>
                            <?php foreach (array_slice($auditLogs, 0, 6) as $event): ?>
                                <div class="metric-item">
                                    <span><?php echo htmlspecialchars($event['action_type']); ?> / <?php echo htmlspecialchars($event['target_type']); ?></span>
                                    <span><?php echo date('M d', strtotime($event['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="metric-item"><span>No audit logs yet.</span></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <section class="panel" id="alerts">
            <header>
                <div>
                    <h3>Alerts & Scheduled Reports</h3>
                    <span>Live alert status and scheduled report automation for Super Admin review.</span>
                </div>
            </header>
            <div class="chart-grid">
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Active Alerts</h4>
                    <div id="alertSummary" class="metric-list">
                        <div class="metric-item"><span>Loading current alert status...</span></div>
                    </div>
                    <div style="margin-top:18px;display:flex;gap:12px;flex-wrap:wrap;">
                        <button class="btn btn-primary" id="refreshAlertsBtn" type="button">Refresh Alerts</button>
                        <button class="btn btn-secondary" id="sendAlertNotificationBtn" type="button">Send Alert Notification</button>
                        <button class="btn btn-secondary" id="runDueReportsBtn" type="button">Run Due Reports</button>
                    </div>
                </div>
                <div class="panel" style="padding:22px;">
                    <h4 style="margin-bottom:18px;">Report Schedules</h4>
                    <div class="table-wrap" style="margin-bottom:18px;">
                        <table class="data-table">
                            <thead><tr><th>Name</th><th>Type</th><th>Frequency</th><th>Hour</th><th>Recipient</th><th>Status</th><th>Next Run</th><th>Actions</th></tr></thead>
                            <tbody id="reportScheduleBody"><tr><td colspan="8">Loading scheduled reports...</td></tr></tbody>
                        </table>
                    </div>
                    <form id="reportScheduleForm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
                        <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Schedule Name</label><input type="text" name="name" required placeholder="Morning Summary" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
                        <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Report Type</label><select name="report_type" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.08);color:#f8fafc;min-height:46px;appearance:auto;-webkit-appearance:menulist;-moz-appearance:menulist;"><option value="daily_summary">Daily Summary</option><option value="weekly_summary">Weekly Summary</option><option value="audit_activity">Audit Activity Summary</option></select></div>
                        <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Frequency</label><select name="frequency" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.08);color:#f8fafc;min-height:46px;appearance:auto;-webkit-appearance:menulist;-moz-appearance:menulist;"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
                        <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Hour (0-23)</label><input type="number" name="schedule_hour" value="8" min="0" max="23" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.08);color:#f8fafc;min-height:46px;"></div>
                        <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Recipient Email</label><input type="email" name="recipient_email" placeholder="report@example.com" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.08);color:#f8fafc;min-height:46px;"></div>
                        <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Enabled</label><select name="enabled" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.08);color:#f8fafc;min-height:46px;appearance:auto;-webkit-appearance:menulist;-moz-appearance:menulist;"><option value="1" selected>Yes</option><option value="0">No</option></select></div>
                        <div style="display:flex;gap:12px;"><button class="btn btn-primary" type="submit">Save Schedule</button><button class="btn btn-secondary" type="button" id="reloadSchedulesBtn">Reload</button></div>
                    </form>
                </div>
            </div>
        </section>

        <section class="panel" id="database">
            <header>
                <div>
                    <h3>Database Monitoring</h3>
                    <span>Table health, row volume, and storage metrics for core system data.</span>
                </div>
            </header>
            <div class="metric-list">
                <?php if (!empty($dbHealth)): ?>
                    <?php foreach ($dbHealth as $meta): ?>
                        <div class="metric-item">
                            <span><?php echo htmlspecialchars($meta['table']); ?> rows</span>
                            <span><?php echo number_format($meta['rows']); ?> rows · <?php echo number_format($meta['sizeMB'], 2); ?> MB</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="metric-item"><span>No database metadata available</span></div>
                <?php endif; ?>
            </div>
            <div class="actions-grid" style="margin-top: 20px;">
                <div class="action-card">
                    <h4>Database Backup</h4>
                    <p>Create backups of the database from the system console.</p>
                    <a href="super_admin_database.php">Open DB Monitor</a>
                </div>
                <div class="action-card">
                    <h4>Database Export</h4>
                    <p>Export schema and data for offline storage or migration.</p>
                    <a href="super_admin_database.php">Open DB Monitor</a>
                </div>
                <div class="action-card">
                    <h4>Restore & Recovery</h4>
                    <p>Restore from available backup snapshots on demand.</p>
                    <a href="super_admin_database.php">Open DB Monitor</a>
                </div>
            </div>
        </section>
    </main>
</div>

<script>
    const adoptionTrendLabels = <?php echo json_encode($trendMonths); ?>;
    const adoptionTrendData = <?php echo json_encode($trendApplications); ?>;
    const userGrowthData = <?php echo json_encode($trendUsers); ?>;
    const appStatusData = <?php echo json_encode(array_values($appStatusData)); ?>;
    const appStatusLabels = <?php echo json_encode(array_keys($appStatusData)); ?>;

    const gradient = (ctx, color) => {
        const gradient = ctx.createLinearGradient(0, 0, 0, 220);
        gradient.addColorStop(0, color);
        gradient.addColorStop(1, 'rgba(15, 23, 42, 0.08)');
        return gradient;
    };

    new Chart(document.getElementById('adoptionTrendChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: adoptionTrendLabels,
            datasets: [{
                label: 'Adoption submissions',
                data: adoptionTrendData,
                borderColor: '#F2867E',
                backgroundColor: gradient(document.getElementById('adoptionTrendChart').getContext('2d'), 'rgba(242, 134, 126, 0.26)'),
                fill: true,
                tension: 0.4,
                pointRadius: 4,
                pointBackgroundColor: '#F2867E',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 0 },
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#cbd5e1' } },
                y: { grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { color: '#cbd5e1', stepSize: 1 } }
            }
        }
    });

    new Chart(document.getElementById('userGrowthChart').getContext('2d'), {
        type: 'bar',
        data: {
            labels: adoptionTrendLabels,
            datasets: [{
                label: 'New users',
                data: userGrowthData,
                backgroundColor: '#F2867E',
                borderRadius: 10,
                maxBarThickness: 28,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 0 },
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#cbd5e1' } },
                y: { grid: { color: 'rgba(148, 163, 184, 0.16)' }, ticks: { color: '#cbd5e1', stepSize: 1 } }
            }
        }
    });

    new Chart(document.getElementById('statusPieChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: appStatusLabels,
            datasets: [{
                data: appStatusData,
                backgroundColor: ['#22c55e', '#38bdf8', '#fde68a', '#fb7185', '#a78bfa', '#f97316'],
                borderWidth: 0,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 0 },
            plugins: { legend: { position: 'bottom', labels: { color: '#cbd5e1' } } }
        }
    });

    const sidebarLinks = document.querySelectorAll('.nav-link[data-target]');
    const sectionIds = Array.from(document.querySelectorAll('section[id]')).map(section => section.id);

    function setActiveLink(targetId) {
        sidebarLinks.forEach(link => {
            link.classList.toggle('active', link.getAttribute('data-target') === targetId);
        });
    }

    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            const targetId = link.getAttribute('data-target');
            if (targetId) {
                setActiveLink(targetId);
            }
        });
    });

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                setActiveLink(entry.target.id);
            }
        });
    }, { root: document.querySelector('.main'), threshold: 0.3 });

    document.querySelectorAll('section[id]').forEach(section => observer.observe(section));

    const themeToggle = document.getElementById('themeToggle');
    const setThemeButtonText = () => {
        themeToggle.textContent = document.body.classList.contains('dark-mode') ? 'Light Mode' : 'Dark Mode';
    };
    setThemeButtonText();
    
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        setThemeButtonText();
    });

    const apiEndpoint = 'super_admin_api.php';
    const alertSummary = document.getElementById('alertSummary');
    const reportScheduleBody = document.getElementById('reportScheduleBody');
    const reportScheduleForm = document.getElementById('reportScheduleForm');
    const refreshAlertsBtn = document.getElementById('refreshAlertsBtn');
    const sendAlertNotificationBtn = document.getElementById('sendAlertNotificationBtn');
    const reloadSchedulesBtn = document.getElementById('reloadSchedulesBtn');

    async function loadAlertItems() {
        try {
            const response = await fetch(apiEndpoint + '?action=get_alert_items');
            const data = await response.json();
            if (!data.success) {
                alertSummary.innerHTML = '<div class="metric-item"><span>Unable to fetch alerts.</span></div>';
                return;
            }
            if (!data.alerts.length) {
                alertSummary.innerHTML = '<div class="metric-item"><span>No alert metrics available.</span></div>';
                return;
            }
            alertSummary.innerHTML = data.alerts.map(item => `
                <div class="metric-item">
                    <span>${item.label}</span>
                    <span>${item.value} ${item.active ? '⚠️' : ''}</span>
                </div>
            `).join('');
        } catch (error) {
            alertSummary.innerHTML = '<div class="metric-item"><span>Error loading alert metrics.</span></div>';
        }
    }

    async function loadReportSchedules() {
        try {
            const response = await fetch(apiEndpoint + '?action=get_report_schedules');
            const data = await response.json();
            if (!data.success) {
                reportScheduleBody.innerHTML = '<tr><td colspan="8">Unable to load schedules.</td></tr>';
                return;
            }
            if (!data.schedules.length) {
                reportScheduleBody.innerHTML = '<tr><td colspan="8">No scheduled reports configured.</td></tr>';
                return;
            }
            reportScheduleBody.innerHTML = data.schedules.map(schedule => `
                <tr>
                    <td>${schedule.name}</td>
                    <td>${schedule.report_type.replace('_', ' ')}</td>
                    <td>${schedule.frequency}</td>
                    <td>${schedule.schedule_hour}</td>
                    <td>${schedule.recipient_email || '-'}</td>
                    <td>${schedule.enabled ? 'Enabled' : 'Disabled'}</td>
                    <td>${schedule.next_run_at || '-'}</td>
                    <td style="display:flex;gap:6px;"><button class="btn btn-secondary" type="button" onclick="runSchedule(${schedule.id})">Run</button><button class="btn btn-secondary" type="button" onclick="deleteSchedule(${schedule.id})">Delete</button></td>
                </tr>
            `).join('');
        } catch (error) {
            reportScheduleBody.innerHTML = '<tr><td colspan="8">Error loading schedules.</td></tr>';
        }
    }

    async function sendAlertNotification() {
        try {
            const response = await fetch(apiEndpoint, { method: 'POST', body: new URLSearchParams({ action: 'send_alert_notification' }) });
            const data = await response.json();
            alert(data.message || (data.success ? 'Alert notification sent.' : 'Failed to send alert notification.'));
            loadAlertItems();
        } catch (error) {
            alert('Failed to send alert notification.');
        }
    }

    async function runDueReports() {
        if (!confirm('Run all due scheduled reports now?')) return;
        try {
            const response = await fetch(apiEndpoint, { method: 'POST', body: new URLSearchParams({ action: 'run_due_reports' }) });
            const data = await response.json();
            alert(data.results ? 'Due reports executed. ' + (data.results.length ? data.results.map(r => r.message).join(' | ') : 'No due reports found.') : (data.message || 'Failed to execute due reports.'));
            loadReportSchedules();
        } catch (error) {
            alert('Error running due reports.');
        }
    }

    async function runSchedule(id) {
        if (!confirm('Run this report immediately?')) return;
        try {
            const response = await fetch(apiEndpoint, { method: 'POST', body: new URLSearchParams({ action: 'run_report_immediately', id }) });
            const data = await response.json();
            alert(data.message || (data.success ? 'Report executed.' : 'Failed to run report.'));
            loadReportSchedules();
        } catch (error) {
            alert('Error running report.');
        }
    }

    async function deleteSchedule(id) {
        if (!confirm('Delete this scheduled report?')) return;
        try {
            const response = await fetch(apiEndpoint, { method: 'POST', body: new URLSearchParams({ action: 'delete_report_schedule', id }) });
            const data = await response.json();
            alert(data.message || (data.success ? 'Schedule deleted.' : 'Failed to delete schedule.'));
            loadReportSchedules();
        } catch (error) {
            alert('Error deleting schedule.');
        }
    }

    reportScheduleForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(reportScheduleForm);
        const body = new URLSearchParams();
        body.append('action', 'save_report_schedule');
        for (const [key, value] of formData.entries()) {
            body.append(key, value);
        }
        try {
            const response = await fetch(apiEndpoint, { method: 'POST', body });
            const data = await response.json();
            alert(data.message || (data.success ? 'Schedule saved.' : 'Failed to save schedule.'));
            if (data.success) {
                reportScheduleForm.reset();
                loadReportSchedules();
            }
        } catch (error) {
            alert('Error saving schedule.');
        }
    });

    refreshAlertsBtn.addEventListener('click', loadAlertItems);
    sendAlertNotificationBtn.addEventListener('click', sendAlertNotification);
    reloadSchedulesBtn.addEventListener('click', loadReportSchedules);
    document.getElementById('runDueReportsBtn').addEventListener('click', runDueReports);

    loadAlertItems();
    loadReportSchedules();
    setInterval(loadAlertItems, 60000);
    setInterval(loadReportSchedules, 300000);
</script>
</body>
</html>
