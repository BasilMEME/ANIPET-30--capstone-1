<?php
require_once __DIR__ . '/auth_helper.php';
require_once __DIR__ . '/role_permissions_helper.php';
require_super_or_permission('update_security_policy');

function fetchRows($conn, $sql) {
    $rows = [];
    try {
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
    } catch (Throwable $e) {
        error_log('DB fetchRows error: ' . $e->getMessage());
        // return empty array on failure to avoid fatal errors
    }
    return $rows;
}

function fetchSetting($conn, $key, $default = null) {
    $stmt = $conn->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $key);
        $stmt->execute();
        $stmt->bind_result($value);
        if ($stmt->fetch()) {
            $stmt->close();
            return $value;
        }
        $stmt->close();
    }
    return $default;
}


$settings = fetchRows($conn, "SELECT id, setting_key, setting_value, description, updated_at FROM system_settings ORDER BY setting_key ASC");
$passwordMinLength = intval(fetchSetting($conn, 'password_min_length', 8));
$passwordRequireLetters = intval(fetchSetting($conn, 'password_require_letters', 1));
$passwordRequireNumbers = intval(fetchSetting($conn, 'password_require_numbers', 1));
$passwordRequireSpecial = intval(fetchSetting($conn, 'password_require_special_chars', 0));
ensureRolePermissionsTable($conn);
$rolePermissions = fetchRows($conn, "SELECT id, role, permission_key, is_allowed FROM role_permissions ORDER BY role ASC, permission_key ASC");
$permissionGroups = [];
foreach ($rolePermissions as $perm) {
    $permissionGroups[$perm['permission_key']][$perm['role']] = $perm;
}
$alertSummary = fetchRows($conn, "SELECT action_type, COUNT(*) AS total FROM audit_logs GROUP BY action_type ORDER BY total DESC LIMIT 8");
$sessions = fetchRows($conn, "SELECT us.id, us.user_id, us.session_id, us.ip_address, us.user_agent, us.created_at, us.last_active_at, us.is_active, u.username, u.email, u.full_name, u.role FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id ORDER BY us.last_active_at DESC LIMIT 100");
$auditLogs = fetchRows($conn, "SELECT al.id, al.user_id, al.action_type, al.target_type, al.target_id, al.details, al.before_data, al.after_data, al.ip_address, al.created_at, u.full_name AS actor_name, u.username, u.email FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 100");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin Security</title>
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
        .grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;}
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
        .status-pill{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;font-size:.82rem;font-weight:700;background:rgba(148,163,184,.14);}
        .status-pill.active{color:#22c55e;}
        .status-pill.inactive{color:#fbbf24;}
        .status-pill.suspended{color:#fb7185;}
        .section{margin-top:28px;}
        .section h2{margin-bottom:18px;font-size:1.12rem;}
        .label-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
        .metric-item{display:flex;justify-content:space-between;padding:14px 16px;border-radius:18px;background:rgba(255,255,255,.04);border:1px solid rgba(148,163,184,.14);margin-bottom:12px;}
        .note{color:var(--muted);font-size:.94rem;line-height:1.6;}
        @media (max-width: 1000px){ .grid{grid-template-columns:1fr;} }
        @media (max-width: 760px){ .container{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Security & Permissions</h1>
            <p class="note">Review password policy, session activity, and recent security audit events.</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
    </div>

    <section class="section grid">
        <div class="card">
            <h2>Password Policy</h2>
            <form id="passwordPolicyForm">
                <div class="metric-item"><span>Minimum Length</span><input type="number" id="passwordMinLength" name="min_length" value="<?php echo $passwordMinLength; ?>" min="6" max="32" style="width:70px;border-radius:12px;padding:8px 10px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.05);color:#e2e8f0;text-align:right;"></div>
                <div class="metric-item"><span>Require letters</span><input type="checkbox" id="passwordRequireLetters" name="require_letters" value="1" <?php echo $passwordRequireLetters ? 'checked' : ''; ?>></div>
                <div class="metric-item"><span>Require numbers</span><input type="checkbox" id="passwordRequireNumbers" name="require_numbers" value="1" <?php echo $passwordRequireNumbers ? 'checked' : ''; ?>></div>
                <div class="metric-item"><span>Require special chars</span><input type="checkbox" id="passwordRequireSpecial" name="require_special_chars" value="1" <?php echo $passwordRequireSpecial ? 'checked' : ''; ?>></div>
                <div style="margin-top:16px;"><button class="btn btn-primary" type="submit">Save Password Policy</button></div>
            </form>
        </div>
        <div class="card">
            <h2>Current System Settings</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Key</th><th>Value</th></tr></thead>
                    <tbody>
                        <?php foreach ($settings as $setting): ?>
                            <tr><td><?php echo htmlspecialchars($setting['setting_key']); ?></td><td><?php echo htmlspecialchars($setting['setting_value']); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="card table-wrap">
            <h2>Active Sessions</h2>
            <table>
                <thead><tr><th>ID</th><th>User</th><th>Role</th><th>IP</th><th>Last Active</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach ($sessions as $session): ?>
                        <tr>
                            <td><?php echo $session['id']; ?></td>
                            <td><?php echo htmlspecialchars($session['username'] ?: $session['email']); ?></td>
                            <td><?php echo htmlspecialchars($session['role']); ?></td>
                            <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($session['last_active_at'])); ?></td>
                            <td><span class="status-pill <?php echo $session['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $session['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                            <td><button class="btn btn-secondary" onclick="terminateSession(<?php echo $session['id']; ?>)">Terminate</button></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <h2>Role Permissions Matrix</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Permission</th><th>Super Admin</th><th>Admin</th><th>User</th></tr></thead>
                    <tbody>
                        <?php foreach ($permissionGroups as $permissionKey => $entries): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($permissionKey); ?></td>
                                <td><input type="checkbox" class="perm-toggle" data-role="super_admin" data-permission="<?php echo htmlspecialchars($permissionKey); ?>" <?php echo isset($entries['super_admin']) && $entries['super_admin']['is_allowed'] ? 'checked' : ''; ?>></td>
                                <td><input type="checkbox" class="perm-toggle" data-role="admin" data-permission="<?php echo htmlspecialchars($permissionKey); ?>" <?php echo isset($entries['admin']) && $entries['admin']['is_allowed'] ? 'checked' : ''; ?>></td>
                                <td><input type="checkbox" class="perm-toggle" data-role="user" data-permission="<?php echo htmlspecialchars($permissionKey); ?>" <?php echo isset($entries['user']) && $entries['user']['is_allowed'] ? 'checked' : ''; ?>></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="margin-top:16px;"><button class="btn btn-primary" id="savePermissionsBtn">Save Permissions</button></div>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <h2>Security Alert Summary</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Event Type</th><th>Count</th></tr></thead>
                    <tbody>
                        <?php foreach ($alertSummary as $alert): ?>
                            <tr><td><?php echo htmlspecialchars($alert['action_type']); ?></td><td><?php echo intval($alert['total']); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section">
        <div class="card table-wrap">
            <h2>Recent Security Events</h2>
            <table>
                <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th><th>IP</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td><?php echo $log['id']; ?></td>
                            <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                            <td><?php echo htmlspecialchars($log['target_type']); ?></td>
                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<script>
    const apiEndpoint = 'super_admin_api.php';

    document.getElementById('passwordPolicyForm').addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        formData.append('action', 'save_password_policy');
        formData.set('require_letters', document.getElementById('passwordRequireLetters').checked ? '1' : '0');
        formData.set('require_numbers', document.getElementById('passwordRequireNumbers').checked ? '1' : '0');
        formData.set('require_special_chars', document.getElementById('passwordRequireSpecial').checked ? '1' : '0');
        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => alert(data.message || 'Password policy saved'))
            .catch(() => alert('Failed to save password policy'));
    });

    document.getElementById('savePermissionsBtn').addEventListener('click', () => {
        const toggles = document.querySelectorAll('.perm-toggle');
        const promises = [];
        toggles.forEach((toggle) => {
            const role = toggle.dataset.role;
            const permissionKey = toggle.dataset.permission;
            const isAllowed = toggle.checked ? '1' : '0';
            const formData = new FormData();
            formData.append('action', 'save_role_permission');
            formData.append('role', role);
            formData.append('permission_key', permissionKey);
            formData.append('is_allowed', isAllowed);
            promises.push(fetch(apiEndpoint, { method: 'POST', body: formData }).then(res => res.json()));
        });
        Promise.all(promises)
            .then(results => {
                if (results.every(r => r.success)) {
                    alert('Role permissions saved successfully');
                } else {
                    alert('Some permission updates failed');
                }
            })
            .catch(() => alert('Failed to save permissions'));
    });

    function terminateSession(id) {
        if (!confirm('Terminate this session?')) return;
        const formData = new FormData();
        formData.append('action', 'terminate_session');
        formData.append('id', id);
        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => alert(data.message || 'Session terminated'))
            .catch(() => alert('Terminate failed'));
    }
</script>
</body>
</html>
