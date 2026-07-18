<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_admins');

function fetchRows($conn, $sql) {
    $rows = [];
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    return $rows;
}

$admins = fetchRows($conn, "SELECT id, username, full_name, email, role, is_verified, is_suspended, is_deleted, created_at FROM users WHERE role IN ('admin', 'super_admin', 'super') ORDER BY created_at DESC");
$users = fetchRows($conn, "SELECT id, username, full_name, email, role, is_verified, is_suspended, is_deleted, created_at FROM users WHERE role NOT IN ('admin', 'super_admin', 'super') ORDER BY created_at DESC");
$pets = fetchRows($conn, "SELECT id, name, breed, age, gender, status, is_archived, shelter_id, created_at FROM pets ORDER BY created_at DESC");
$sessions = fetchRows($conn, "SELECT us.id, us.user_id, us.session_id, us.ip_address, us.user_agent, us.created_at, us.last_active_at, us.is_active, u.username, u.email, u.full_name, u.role FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id ORDER BY us.last_active_at DESC LIMIT 100");

$shelters = fetchRows($conn, "SELECT id, name FROM shelters ORDER BY name ASC");
$auditLogs = fetchRows($conn, "SELECT al.id, al.user_id, al.action_type, al.target_type, al.target_id, al.details, al.ip_address, al.created_at, u.full_name AS actor_name, u.username, u.email FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 50");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin Control Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
        body { margin:0; font-family:'Inter',sans-serif; color:var(--text); min-height:100vh; background: radial-gradient(circle at top left, rgba(242,134,126,.14), transparent 25%), radial-gradient(circle at bottom right, rgba(246,201,160,.12), transparent 24%), linear-gradient(135deg, #020617 0%, #07111f 100%); }
        .container{max-width:1440px;margin:0 auto;padding:24px 24px 40px;}
        .header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;padding:18px 20px;background:rgba(15,23,42,.72);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);backdrop-filter:blur(14px);}
        .header h1{margin:0;font-size:clamp(1.4rem,2vw,2rem);}
        .cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:18px;}
        .card{background:var(--panel);border:1px solid var(--border);border-radius:24px;padding:22px;box-shadow:var(--shadow);backdrop-filter:blur(12px);transition:transform .18s ease,border-color .18s ease;}
        .card:hover{transform:translateY(-1px);border-color:rgba(242,134,126,.24);}
        .card h2{margin:0 0 12px;font-size:1.08rem;color:#cbd5e1;}
        .table-wrap{overflow-x:auto;border:1px solid rgba(148,163,184,.12);border-radius:18px;background:rgba(255,255,255,.025);}
        table{width:100%;border-collapse:collapse;color:var(--text);table-layout:fixed;min-width:720px;}
        th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(148,163,184,.12);overflow-wrap:anywhere;word-break:break-word;}
        th{color:var(--muted);font-size:.82rem;text-transform:uppercase;letter-spacing:.05em;}
        tr:hover{background:rgba(242,134,126,.08);}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:14px;border:none;cursor:pointer;font-weight:700;transition:transform .18s ease,background .18s ease,box-shadow .18s ease;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(2,8,23,.22);}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#D9695F);color:#241209;}
        .btn-secondary{background:rgba(255,255,255,.08);color:var(--text);}
        .status {display:inline-flex;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:700;}
        .status.active{background:rgba(34,197,94,.14);color:#22c55e;}
        .status.suspended{background:rgba(248,113,113,.14);color:#fb7185;}
        .status.deleted{background:rgba(248,113,113,.14);color:#fb7185;}
        .status.archived{background:rgba(148,163,184,.16);color:#94a3b8;}
        .section{margin-top:28px;}
        .section h2{margin-bottom:18px;font-size:1.12rem;}
        .grid-two{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
        .action-group{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;}
        .small-note{color:var(--muted);font-size:.94rem;line-height:1.6;}
        @media (max-width: 1000px){ .grid-two{grid-template-columns:1fr;} }
        @media (max-width: 760px){ .container{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Super Admin Control Panel</h1>
            <p class="small-note">Manage admins, users, pets, sessions, and system policies from one premium console.</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
    </div>

    <section class="section">
        <h2>Admins</h2>
        <div class="card table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($admins as $admin): ?>
                    <tr>
                        <td><?php echo $admin['id']; ?></td>
                        <td><?php echo htmlspecialchars($admin['username']); ?></td>
                        <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($admin['email']); ?></td>
                        <td><?php echo htmlspecialchars($admin['role']); ?></td>
                        <td><span class="status <?php echo $admin['is_suspended'] ? 'suspended' : ($admin['is_deleted'] ? 'deleted' : 'active'); ?>"><?php echo $admin['is_suspended'] ? 'Suspended' : ($admin['is_deleted'] ? 'Deleted' : 'Active'); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <h2>Users</h2>
        <div class="card table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>State</th><th>Joined</th></tr></thead>
                <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['role']); ?></td>
                        <td><span class="status <?php echo $user['is_suspended'] ? 'suspended' : ($user['is_deleted'] ? 'deleted' : 'active'); ?>"><?php echo $user['is_suspended'] ? 'Suspended' : ($user['is_deleted'] ? 'Deleted' : 'Active'); ?></span></td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <h2>Pets</h2>
        <div class="card table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Breed</th><th>Age</th><th>Gender</th><th>Status</th><th>Archived</th></tr></thead>
                <tbody>
                <?php foreach ($pets as $pet): ?>
                    <tr>
                        <td><?php echo $pet['id']; ?></td>
                        <td><?php echo htmlspecialchars($pet['name']); ?></td>
                        <td><?php echo htmlspecialchars($pet['breed']); ?></td>
                        <td><?php echo htmlspecialchars($pet['age']); ?></td>
                        <td><?php echo htmlspecialchars($pet['gender']); ?></td>
                        <td><?php echo htmlspecialchars($pet['status']); ?></td>
                        <td><span class="status <?php echo $pet['is_archived'] ? 'archived' : 'active'; ?>"><?php echo $pet['is_archived'] ? 'Archived' : 'Active'; ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <h2>Active Sessions</h2>
        <div class="card table-wrap">
            <table>
                <thead><tr><th>Session ID</th><th>User</th><th>Email</th><th>Role</th><th>IP</th><th>User Agent</th><th>Last Active</th><th>Active</th></tr></thead>
                <tbody>
                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(substr($session['session_id'], 0, 24)) . '...'; ?></td>
                        <td><?php echo htmlspecialchars($session['username']); ?></td>
                        <td><?php echo htmlspecialchars($session['email']); ?></td>
                        <td><?php echo htmlspecialchars($session['role']); ?></td>
                        <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars(substr($session['user_agent'], 0, 50)); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($session['last_active_at'])); ?></td>
                        <td><?php echo $session['is_active'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
</body>
</html>
