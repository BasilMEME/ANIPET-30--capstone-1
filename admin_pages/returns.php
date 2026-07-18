<?php
require_permission($conn, 'manage_returns');

$validStatuses = ['pending', 'approved', 'rejected', 'completed'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $validStatuses)) $filter = '';

$counts = ['all' => 0];
foreach ($validStatuses as $s) $counts[$s] = 0;
$r = $conn->query("SELECT status, COUNT(*) as cnt FROM return_requests GROUP BY status");
if ($r) while ($row = $r->fetch_assoc()) { $counts[$row['status']] = (int)$row['cnt']; $counts['all'] += (int)$row['cnt']; }

$sql = "SELECT rr.id, rr.application_id, rr.pet_id, rr.user_id, rr.reason, rr.penalty_amount,
               rr.penalty_paid, rr.status, rr.admin_notes, rr.created_at,
               p.name AS pet_name, p.breed AS pet_breed,
               u.full_name, u.email
        FROM return_requests rr
        LEFT JOIN pets  p ON rr.pet_id  = p.id
        LEFT JOIN users u ON rr.user_id = u.id
        WHERE 1=1";
if ($filter) $sql .= " AND rr.status = '" . $conn->real_escape_string($filter) . "'";
$sql .= " ORDER BY rr.created_at DESC";

$returns = [];
$result = $conn->query($sql);
if ($result) while ($row = $result->fetch_assoc()) $returns[] = $row;

$pillLabels = [
    'all'       => 'All',
    'pending'   => 'Pending',
    'approved'  => 'Approved',
    'completed' => 'Completed',
    'rejected'  => 'Rejected',
];
?>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Return Requests & Penalties</div>
        <div class="card-sub"><?php echo $counts['all']; ?> total return requests</div>
    </div>
</div>

<div class="status-pills">
<?php foreach ($pillLabels as $s => $l):
    $active = ($filter === $s) || ($s === 'all' && !$filter);
    $cnt    = $counts[$s] ?? 0;
    $href   = $s === 'all' ? '?page=returns' : '?page=returns&status='.$s;
?>
<a href="<?php echo $href; ?>" class="s-pill <?php echo $active?'active':''; ?>">
    <?php echo $l; ?> <span class="pill-cnt"><?php echo $cnt; ?></span>
</a>
<?php endforeach; ?>
</div>

<div class="filters-bar">
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="returnSearch" placeholder="Search applicant, pet…" oninput="filterTable('returnSearch','returnTable')">
    </div>
</div>

<?php if (empty($returns)): ?>
<div class="empty-state"><div class="empty-icon">↩️</div><p>No return requests found</p></div>
<?php else: ?>
<div class="table-wrap">
<table id="returnTable">
    <thead>
        <tr>
            <th>#</th><th>Requested By</th><th>Pet</th><th>Reason</th>
            <th>Penalty</th><th>Paid</th><th>Status</th><th>Requested</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($returns as $rr): ?>
    <tr>
        <td style="color:var(--muted);font-size:.8rem;"><?php echo $rr['id']; ?></td>
        <td>
            <div style="font-weight:600;"><?php echo htmlspecialchars($rr['full_name'] ?? '—'); ?></div>
            <div style="font-size:.76rem;color:var(--muted);"><?php echo htmlspecialchars($rr['email'] ?? ''); ?></div>
        </td>
        <td>
            <div><?php echo htmlspecialchars($rr['pet_name'] ?? '—'); ?></div>
            <?php if ($rr['pet_breed']): ?><div style="font-size:.76rem;color:var(--muted);"><?php echo htmlspecialchars($rr['pet_breed']); ?></div><?php endif; ?>
        </td>
        <td style="max-width:220px;font-size:.82rem;"><?php echo htmlspecialchars(mb_strimwidth($rr['reason'], 0, 80, '…')); ?></td>
        <td style="font-weight:600;">₱<?php echo number_format((float)$rr['penalty_amount'], 2); ?></td>
        <td><span class="badge badge-<?php echo $rr['penalty_paid'] ? 'active' : 'pending'; ?>"><?php echo $rr['penalty_paid'] ? 'Paid' : 'Unpaid'; ?></span></td>
        <td><span class="badge badge-<?php echo $rr['status']==='completed'?'completed':($rr['status']==='rejected'?'rejected':($rr['status']==='approved'?'approved':'pending')); ?>"><?php echo ucwords($rr['status']); ?></span></td>
        <td style="font-size:.8rem;color:var(--muted);"><?php echo date('M d, Y', strtotime($rr['created_at'])); ?></td>
        <td>
            <button class="btn btn-info btn-sm" onclick="viewReturn(<?php echo $rr['id']; ?>)">View</button>
            <button class="btn btn-primary btn-sm" onclick="manageReturnModal(<?php echo htmlspecialchars(json_encode($rr), ENT_QUOTES); ?>)">Manage</button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>

<!-- ══ VIEW RETURN MODAL ══════════════════════════════════════════════ -->
<div class="modal-backdrop" id="viewReturnModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title">↩️ Return Request Details</span>
        <button class="modal-close" onclick="closeModal('viewReturnModal')">✕</button>
    </div>
    <div class="modal-body" id="viewReturnBody">
        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('viewReturnModal')">Close</button>
    </div>
</div>
</div>

<!-- ══ MANAGE RETURN MODAL ════════════════════════════════════════════ -->
<div class="modal-backdrop" id="manageReturnModal">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title">🛠️ Manage Return Request</span>
        <button class="modal-close" onclick="closeModal('manageReturnModal')">✕</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="mrId">
        <div class="form-group">
            <label class="form-label">Status</label>
            <select id="mrStatus" class="form-control">
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="completed">Completed (pet returned to shelter)</option>
            </select>
        </div>
        <div class="form-row cols-2">
            <div class="form-group">
                <label class="form-label">Penalty Amount (₱)</label>
                <input type="number" id="mrPenaltyAmount" class="form-control" min="0" step="0.01">
            </div>
            <div class="form-group">
                <label class="form-label">Penalty Paid</label>
                <select id="mrPenaltyPaid" class="form-control">
                    <option value="0">Unpaid</option>
                    <option value="1">Paid</option>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Admin Notes</label>
            <textarea id="mrAdminNotes" class="form-control" placeholder="Optional notes about this return…"></textarea>
        </div>
        <p style="font-size:.8rem;color:var(--muted);">Marking a request <strong>Completed</strong> returns the pet to the adoptable pool automatically.</p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('manageReturnModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitManageReturn()">Save Changes</button>
    </div>
</div>
</div>

<script>
async function viewReturn(id) {
    document.getElementById('viewReturnBody').innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>';
    openModal('viewReturnModal');

    try {
        const res  = await fetch('admin_api.php?action=get_return_request&id='+id);
        const data = await res.json();
        if (!data.success) { document.getElementById('viewReturnBody').innerHTML='<p style="color:var(--danger);">'+data.message+'</p>'; return; }
        const a = data.return_request;

        document.getElementById('viewReturnBody').innerHTML = `
        <div class="info-grid">
            <div class="info-item"><label>Requested By</label><span>${e(a.full_name||'—')}</span></div>
            <div class="info-item"><label>Email</label><span>${e(a.email||'—')}</span></div>
            <div class="info-item"><label>Phone</label><span>${e(a.phone||'—')}</span></div>
            <div class="info-item"><label>Pet</label><span>${e(a.pet_name||'—')} ${a.pet_breed?'('+e(a.pet_breed)+')':''}</span></div>
            <div class="info-item"><label>Status</label><span class="badge badge-${a.status}">${a.status}</span></div>
            <div class="info-item"><label>Requested On</label><span>${fmtDate(a.created_at)}</span></div>
            <div class="info-item"><label>Penalty Amount</label><span>₱${Number(a.penalty_amount).toFixed(2)}</span></div>
            <div class="info-item"><label>Penalty Paid</label><span>${a.penalty_paid=='1'?'✅ Yes':'❌ No'}</span></div>
        </div>
        <div class="divider"></div>
        <h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">Reason</h4>
        <p style="font-size:.875rem;line-height:1.6;">${e(a.reason)}</p>
        ${a.admin_notes ? `<div class="divider"></div><h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">Admin Notes</h4><p style="font-size:.875rem;line-height:1.6;background:var(--surface-alt);padding:10px;border-radius:6px;">${e(a.admin_notes)}</p>` : ''}
        `;
    } catch (err) {
        document.getElementById('viewReturnBody').innerHTML = '<p style="color:var(--danger);">Failed to load return request details.</p>';
    }
}

function e(str) { const d=document.createElement('div'); d.textContent=str||''; return d.innerHTML; }
function fmtDate(s) { try { return new Date(s).toLocaleString(); } catch(e){ return s||'—'; } }

function manageReturnModal(rr) {
    document.getElementById('mrId').value = rr.id;
    setSelectVal('mrStatus', rr.status || 'pending');
    document.getElementById('mrPenaltyAmount').value = rr.penalty_amount;
    setSelectVal('mrPenaltyPaid', String(rr.penalty_paid));
    document.getElementById('mrAdminNotes').value = rr.admin_notes || '';
    openModal('manageReturnModal');
}

async function submitManageReturn() {
    const id            = document.getElementById('mrId').value;
    const status        = document.getElementById('mrStatus').value;
    const penaltyAmount = document.getElementById('mrPenaltyAmount').value;
    const penaltyPaid   = document.getElementById('mrPenaltyPaid').value;
    const adminNotes    = document.getElementById('mrAdminNotes').value;

    const body = new URLSearchParams({
        action: 'update_return_request',
        id, status,
        penalty_amount: penaltyAmount,
        penalty_paid: penaltyPaid,
        admin_notes: adminNotes,
    });
    const res  = await fetch('admin_api.php', {method:'POST', body});
    const data = await res.json();
    if (data.success) { showToast('Return request updated!'); closeModal('manageReturnModal'); location.reload(); }
    else showToast(data.message,'error');
}

function setSelectVal(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    for (let i=0; i<el.options.length; i++) if (el.options[i].value===val){ el.selectedIndex=i; break; }
}
</script>
