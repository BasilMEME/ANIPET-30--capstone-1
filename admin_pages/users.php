<?php
require_permission($conn, 'manage_users');

$users = [];
$result = $conn->query(
    "SELECT u.id, u.username, u.full_name, u.email, u.phone, u.is_suspended, u.is_verified, u.created_at,
            (SELECT COUNT(*) FROM adoption_applications aa WHERE aa.user_id = u.id) AS app_count
     FROM users u
     WHERE u.role = 'user' AND u.is_deleted = 0
     ORDER BY u.created_at DESC"
);
if ($result) while ($row = $result->fetch_assoc()) $users[] = $row;

$totalUsers     = count($users);
$activeUsers    = count(array_filter($users, fn($u) => !$u['is_suspended']));
$suspendedUsers = count(array_filter($users, fn($u) => $u['is_suspended']));
?>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:18px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">👥</div>
        <div class="stat-body"><div class="stat-value"><?php echo $totalUsers; ?></div><div class="stat-label">Total Users</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">✅</div>
        <div class="stat-body"><div class="stat-value"><?php echo $activeUsers; ?></div><div class="stat-label">Active</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">🚫</div>
        <div class="stat-body"><div class="stat-value"><?php echo $suspendedUsers; ?></div><div class="stat-label">Suspended</div></div>
    </div>
</div>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Registered Adopters</div>
        <div class="card-sub">View, suspend, and review adoption histories</div>
    </div>
</div>

<div class="filters-bar">
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="userSearch" placeholder="Search by name, email, username…" oninput="filterTable('userSearch','userTable')">
    </div>
    <select class="form-control" style="width:auto;" onchange="filterByStatus(this.value)">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="suspended">Suspended</option>
    </select>
</div>

<?php if (empty($users)): ?>
<div class="empty-state"><div class="empty-icon">👥</div><p>No users registered yet</p></div>
<?php else: ?>
<div class="table-wrap">
<table id="userTable">
    <thead>
        <tr><th>Avatar</th><th>Name</th><th>Email</th><th>Username</th><th>Status</th><th>Applications</th><th>Joined</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($users as $user):
        $initials = strtoupper(substr($user['full_name'],0,1));
        $isSuspended = (bool)$user['is_suspended'];
    ?>
    <tr data-status="<?php echo $isSuspended?'suspended':'active'; ?>">
        <td>
            <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,#f2867e,#1b2a41);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;">
                <?php echo $initials; ?>
            </div>
        </td>
        <td style="font-weight:600;"><?php echo htmlspecialchars($user['full_name']); ?></td>
        <td style="font-size:.82rem;"><?php echo htmlspecialchars($user['email']); ?></td>
        <td style="color:var(--muted);font-size:.82rem;"><?php echo htmlspecialchars($user['username'] ?? '—'); ?></td>
        <td>
            <span class="badge badge-<?php echo $isSuspended?'suspended':'active'; ?>">
                <?php echo $isSuspended?'Suspended':'Active'; ?>
            </span>
            <?php if ($user['is_verified']): ?><span class="badge" style="background:#dbeafe;color:#1e40af;margin-left:4px;">Verified</span><?php endif; ?>
        </td>
        <td>
            <button class="btn btn-ghost btn-sm" onclick="viewHistory(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['full_name'],ENT_QUOTES); ?>')">
                <?php echo (int)$user['app_count']; ?> application<?php echo $user['app_count']!=1?'s':''; ?>
            </button>
        </td>
        <td style="font-size:.8rem;color:var(--muted);"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
        <td>
            <?php if ($isSuspended): ?>
            <button class="btn btn-success btn-sm" onclick="toggleUser(<?php echo $user['id']; ?>, 0, '<?php echo htmlspecialchars($user['full_name'],ENT_QUOTES); ?>')">Activate</button>
            <?php else: ?>
            <button class="btn btn-danger btn-sm"  onclick="toggleUser(<?php echo $user['id']; ?>, 1, '<?php echo htmlspecialchars($user['full_name'],ENT_QUOTES); ?>')">Suspend</button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>

<!-- ══ ADOPTION HISTORY MODAL ══════════════════════════════════════════ -->
<div class="modal-backdrop" id="historyModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title" id="historyModalTitle">📜 Adoption History</span>
        <button class="modal-close" onclick="closeModal('historyModal')">✕</button>
    </div>
    <div class="modal-body" id="historyBody">
        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('historyModal')">Close</button>
    </div>
</div>
</div>

<script>
async function viewHistory(userId, userName) {
    document.getElementById('historyModalTitle').textContent = '📜 Adoption History — ' + userName;
    document.getElementById('historyBody').innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>';
    openModal('historyModal');

    try {
        const res  = await fetch('admin_api.php?action=get_user_history&id='+userId);
        const data = await res.json();
        if (!data.success) { document.getElementById('historyBody').innerHTML='<p style="color:var(--danger);">'+data.message+'</p>'; return; }

        if (!data.history.length) {
            document.getElementById('historyBody').innerHTML = '<div class="empty-state"><div class="empty-icon">📋</div><p>No adoption applications found for this user.</p></div>';
            return;
        }

        let html = '<div class="table-wrap"><table><thead><tr><th>#</th><th>Pet</th><th>Status</th><th>Applied</th><th>Interview</th><th>Notes</th></tr></thead><tbody>';
        data.history.forEach(h => {
            const petImg = h.pet_image
                ? `<img src="images/${h.pet_image}" style="width:32px;height:32px;border-radius:5px;object-fit:cover;vertical-align:middle;margin-right:6px;">`
                : '🐾 ';
            html += `<tr>
                <td style="color:var(--muted);font-size:.8rem;">${h.id}</td>
                <td>${petImg}${escH(h.pet_name||'—')}<br><span style="font-size:.74rem;color:var(--muted);">${escH(h.pet_breed||'')}</span></td>
                <td><span class="badge badge-${h.status}">${h.status.replace(/_/g,' ')}</span></td>
                <td style="font-size:.8rem;">${fDate(h.created_at)}</td>
                <td style="font-size:.8rem;">${h.interview_datetime?fDate(h.interview_datetime):'—'}</td>
                <td style="font-size:.78rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="${escH(h.admin_notes||'')}">${escH(h.admin_notes||'—')}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        document.getElementById('historyBody').innerHTML = html;
    } catch(e) {
        document.getElementById('historyBody').innerHTML = '<p style="color:var(--danger);">Failed to load history.</p>';
    }
}

function escH(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }
function fDate(s){ try{ return new Date(s).toLocaleDateString(); } catch(e){ return s||'—'; } }

async function toggleUser(id, suspend, name) {
    const action = suspend ? 'suspend' : 'activate';
    if (!confirm(`${suspend?'Suspend':'Activate'} user "${name}"?`)) return;
    const res  = await fetch('admin_api.php', {method:'POST', body: new URLSearchParams({action:'update_user_status',id,is_suspended:suspend})});
    const data = await res.json();
    if (data.success) { showToast('User '+action+'d'); location.reload(); }
    else showToast(data.message,'error');
}

function filterByStatus(val) {
    document.querySelectorAll('#userTable tbody tr').forEach(row => {
        if (!val) { row.style.display=''; return; }
        row.style.display = row.dataset.status===val ? '' : 'none';
    });
}
</script>
