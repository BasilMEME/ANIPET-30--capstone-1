<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('view_audit_logs');

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
    }
    return $rows;
}

$auditLogs = fetchRows($conn, "SELECT al.id, al.user_id, al.action_type, al.target_type, al.target_id, al.details, al.before_data, al.after_data, al.ip_address, al.created_at, u.full_name AS actor_name, u.username, u.email FROM audit_logs al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 200");
$sessions = fetchRows($conn, "SELECT us.id, us.user_id, us.session_id, us.ip_address, us.user_agent, us.created_at, us.last_active_at, us.is_active, u.username, u.email, u.full_name, u.role FROM user_sessions us LEFT JOIN users u ON us.user_id = u.id ORDER BY us.last_active_at DESC LIMIT 200");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin Activity</title>
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
        .card{background:var(--panel);border:1px solid var(--border);border-radius:24px;padding:22px;box-shadow:var(--shadow);backdrop-filter:blur(12px);transition:transform .18s ease,border-color .18s ease;}
        .card:hover{transform:translateY(-1px);border-color:rgba(242,134,126,.24);}
        .table-wrap{overflow-x:auto;border:1px solid rgba(148,163,184,.12);border-radius:18px;background:rgba(255,255,255,.025);}
        table{width:100%;border-collapse:collapse;color:var(--text);table-layout:fixed;min-width:720px;}
        th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(148,163,184,.12);overflow-wrap:anywhere;word-break:break-word;}
        th{color:var(--muted);font-size:.82rem;text-transform:uppercase;letter-spacing:.05em;}
        tr:hover{background:rgba(242,134,126,.08);}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:14px;border:none;cursor:pointer;font-weight:700;transition:transform .18s ease,background .18s ease,box-shadow .18s ease;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(2,8,23,.22);}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#D9695F);color:#241209;}
        .btn-secondary{background:rgba(255,255,255,.08);color:var(--text);}
        .section{margin-top:28px;}
        .section h2{margin-bottom:18px;font-size:1.12rem;}
        .note{color:var(--muted);font-size:.94rem;line-height:1.6;}
        @media (max-width: 760px){ .container{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Activity & Audit Logs</h1>
            <p class="note">View system actions, inspect audit history, and filter events by actor, target, or keyword.</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
    </div>

    <section class="section card">
        <h2>Audit Search & Filters</h2>
        <form id="auditSearchForm" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Action Type</label><input type="text" name="action_type" placeholder="e.g. update_user" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Target Type</label><input type="text" name="target_type" placeholder="e.g. user, pet" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Actor</label><input type="text" name="actor" placeholder="Admin username or email" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Keyword</label><input type="text" name="keyword" placeholder="Search details or changes" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Date From</label><input type="date" name="date_from" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Date To</label><input type="date" name="date_to" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;flex-direction:column;"><label style="margin-bottom:6px;color:#94a3b8;font-size:.95rem;">Limit</label><input type="number" name="limit" value="100" min="10" max="500" style="padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"></div>
            <div style="display:flex;align-items:flex-end;gap:12px;">
                <button class="btn btn-primary" type="submit">Search</button>
                <button class="btn btn-secondary" type="button" id="resetAuditSearch">Reset</button>
            </div>
        </form>
    </section>

    <section class="section card table-wrap">
        <h2>Audit Events</h2>
        <table>
            <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Target</th><th>Details</th><th>Diff</th><th>IP</th><th>Date</th></tr></thead>
            <tbody>
                <?php foreach ($auditLogs as $log): ?>
                    <tr data-before="<?php echo htmlspecialchars($log['before_data']); ?>" data-after="<?php echo htmlspecialchars($log['after_data']); ?>">
                        <td><?php echo $log['id']; ?></td>
                        <td><?php echo htmlspecialchars($log['actor_name'] ?? 'System'); ?></td>
                        <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['target_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['details']); ?></td>
                        <td><button class="btn btn-secondary" onclick="showDiff(<?php echo $log['id']; ?>)">View</button>
                            <div id="diff-<?php echo $log['id']; ?>" class="diff-popup" style="display:none;margin-top:8px;padding:12px;border-radius:14px;background:rgba(15,23,42,.96);border:1px solid rgba(148,163,184,.18);font-size:.9rem;white-space:pre-wrap;max-height:260px;overflow:auto;"></div>
                        </td>
                        <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($log['created_at'])); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="section card table-wrap">
        <h2>Session History</h2>
        <table>
            <thead><tr><th>ID</th><th>User</th><th>Role</th><th>IP</th><th>Agent</th><th>Last Active</th><th>Active</th></tr></thead>
            <tbody>
                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?php echo $session['id']; ?></td>
                        <td><?php echo htmlspecialchars($session['username'] ?: $session['email']); ?></td>
                        <td><?php echo htmlspecialchars($session['role']); ?></td>
                        <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                        <td><?php echo htmlspecialchars(substr($session['user_agent'], 0, 60)); ?></td>
                        <td><?php echo date('M d, Y H:i', strtotime($session['last_active_at'])); ?></td>
                        <td><?php echo $session['is_active'] ? 'Yes' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<script>
    function showDiff(id) {
        const popup = document.getElementById('diff-' + id);
        if (!popup) return;
        if (popup.style.display === 'block') {
            popup.style.display = 'none';
            return;
        }
        const row = popup.closest('tr');
        const before = row?.dataset?.before || 'No before-data available';
        const after = row?.dataset?.after || 'No after-data available';
        popup.textContent = 'Before: ' + before + '\n\nAfter: ' + after;
        popup.style.display = 'block';
    }
</script>
<script>
    const apiEndpoint = 'super_admin_api.php';
    const auditBody = document.querySelector('section.card.table-wrap table tbody');
    const auditForm = document.getElementById('auditSearchForm');
    const resetAuditBtn = document.getElementById('resetAuditSearch');

    function buildAuditRows(logs) {
        return logs.map(log => {
            const beforeData = log.before_data || 'No before-data available';
            const afterData = log.after_data || 'No after-data available';
            return `
                <tr data-before="${escapeHtml(beforeData)}" data-after="${escapeHtml(afterData)}">
                    <td>${log.id}</td>
                    <td>${escapeHtml(log.actor_name || 'System')}</td>
                    <td>${escapeHtml(log.action_type)}</td>
                    <td>${escapeHtml(log.target_type)}</td>
                    <td>${escapeHtml(log.details)}</td>
                    <td><button class="btn btn-secondary" type="button" onclick="showDiff(${log.id})">View</button>
                        <div id="diff-${log.id}" class="diff-popup" style="display:none;margin-top:8px;padding:12px;border-radius:14px;background:rgba(15,23,42,.96);border:1px solid rgba(148,163,184,.18);font-size:.9rem;white-space:pre-wrap;max-height:260px;overflow:auto;"></div>
                    </td>
                    <td>${escapeHtml(log.ip_address)}</td>
                    <td>${escapeHtml(log.created_at)}</td>
                </tr>
            `;
        }).join('');
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    async function performAuditSearch(params = {}) {
        const url = new URL(apiEndpoint, window.location.origin);
        url.searchParams.set('action', 'get_audit_search');
        Object.entries(params).forEach(([key, value]) => {
            if (value !== null && value !== undefined && value !== '') {
                url.searchParams.set(key, value);
            }
        });
        const response = await fetch(url.toString());
        const data = await response.json();
        if (data.success) {
            auditBody.innerHTML = buildAuditRows(data.logs);
        } else {
            alert(data.message || 'Audit search failed.');
        }
    }

    auditForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        const formData = new FormData(auditForm);
        const params = Object.fromEntries(formData.entries());
        await performAuditSearch(params);
    });

    resetAuditBtn.addEventListener('click', async () => {
        ['action_type', 'target_type', 'actor', 'keyword', 'date_from', 'date_to'].forEach(name => {
            const input = auditForm.querySelector(`[name="${name}"]`);
            if (input) input.value = '';
        });
        auditForm.querySelector('[name="limit"]').value = 100;
        await performAuditSearch({ limit: 100 });
    });
</script>
</body>
</html>
