<?php
require_permission($conn, 'manage_applications');

// Kept in sync with APPLICATION_STATUS_PIPELINE in application_status_helper.php,
// which drives QR generation and the user-facing tracking screen.
$validStatuses = ['pending','screening','approved','for_releasing','ready_pickup','completed','rejected'];
$filter = $_GET['status'] ?? '';
if (!in_array($filter, $validStatuses)) $filter = '';

// Count per status
$counts = ['all' => 0];
foreach ($validStatuses as $s) $counts[$s] = 0;
$r = $conn->query("SELECT status, COUNT(*) as cnt FROM adoption_applications GROUP BY status");
if ($r) while ($row = $r->fetch_assoc()) { $counts[$row['status']] = (int)$row['cnt']; $counts['all'] += (int)$row['cnt']; }

// Fetch applications
$sql = "SELECT aa.id, aa.pet_id, aa.user_id, aa.applicant_name, aa.status, aa.created_at,
               aa.interview_datetime, aa.admin_notes,
               p.name AS pet_name, p.breed AS pet_breed
        FROM adoption_applications aa
        LEFT JOIN pets p ON aa.pet_id = p.id
        WHERE 1=1";
if ($filter) $sql .= " AND aa.status = '" . $conn->real_escape_string($filter) . "'";
$sql .= " ORDER BY aa.created_at DESC";

$applications = [];
$result = $conn->query($sql);
if ($result) while ($row = $result->fetch_assoc()) $applications[] = $row;

$pillLabels = [
    'all'            => 'All',
    'pending'        => 'Pending',
    'screening'      => 'Screening',
    'approved'       => 'Approved',
    'for_releasing'  => 'For Release',
    'ready_pickup'   => 'Ready for Pick-up',
    'completed'      => 'Completed',
    'rejected'       => 'Rejected',
];
?>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Adoption Applications</div>
        <div class="card-sub"><?php echo $counts['all']; ?> total applications</div>
    </div>
</div>

<!-- Status filter pills -->
<div class="status-pills">
<?php foreach ($pillLabels as $s => $l):
    $active = ($filter === $s) || ($s === 'all' && !$filter);
    $cnt    = $counts[$s] ?? 0;
    $href   = $s === 'all' ? '?page=applications' : '?page=applications&status='.$s;
?>
<a href="<?php echo $href; ?>" class="s-pill <?php echo $active?'active':''; ?>">
    <?php echo $l; ?> <span class="pill-cnt"><?php echo $cnt; ?></span>
</a>
<?php endforeach; ?>
</div>

<!-- Search -->
<div class="filters-bar">
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="appSearch" placeholder="Search applicant, pet…" oninput="filterTable('appSearch','appTable')">
    </div>
</div>

<!-- Table -->
<?php if (empty($applications)): ?>
<div class="empty-state"><div class="empty-icon">📋</div><p>No applications found</p></div>
<?php else: ?>
<div class="table-wrap">
<table id="appTable">
    <thead>
        <tr>
            <th>#</th><th>Applicant</th><th>Pet</th><th>Status</th>
            <th>Interview Date</th><th>Applied</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($applications as $app): ?>
    <tr>
        <td style="color:var(--muted);font-size:.8rem;"><?php echo $app['id']; ?></td>
        <td style="font-weight:600;"><?php echo htmlspecialchars($app['applicant_name']); ?></td>
        <td>
            <div><?php echo htmlspecialchars($app['pet_name'] ?? '—'); ?></div>
            <?php if ($app['pet_breed']): ?><div style="font-size:.76rem;color:var(--muted);"><?php echo htmlspecialchars($app['pet_breed']); ?></div><?php endif; ?>
        </td>
        <td><span class="badge badge-<?php echo strtolower($app['status']); ?>"><?php echo ucwords(str_replace('_',' ',$app['status'])); ?></span></td>
        <td style="font-size:.82rem;"><?php echo $app['interview_datetime'] ? date('M d, Y H:i', strtotime($app['interview_datetime'])) : '—'; ?></td>
        <td style="font-size:.8rem;color:var(--muted);"><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
        <td>
            <button class="btn btn-info btn-sm" onclick="viewApplication(<?php echo $app['id']; ?>)">View</button>
            <button class="btn btn-primary btn-sm" onclick="changeStatusModal(<?php echo $app['id']; ?>, '<?php echo $app['status']; ?>')">Status</button>
            <?php if (in_array($app['status'], ['pending', 'screening'])): ?>
            <button class="btn btn-warning btn-sm" onclick="scheduleInterviewModal(<?php echo $app['id']; ?>, '<?php echo $app['interview_datetime'] ? str_replace(' ', 'T', substr($app['interview_datetime'], 0, 16)) : ''; ?>')">🎤 Interview</button>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>

<!-- ══ VIEW APPLICATION MODAL ══════════════════════════════════════════ -->
<div class="modal-backdrop" id="viewAppModal">
<div class="modal modal-xl">
    <div class="modal-header">
        <span class="modal-title">📋 Application Details</span>
        <button class="modal-close" onclick="closeModal('viewAppModal')">✕</button>
    </div>
    <div class="modal-body" id="viewAppBody">
        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('viewAppModal')">Close</button>
        <button class="btn btn-primary" id="viewAppStatusBtn" onclick="">Change Status</button>
    </div>
</div>
</div>

<!-- ══ CHANGE STATUS MODAL ════════════════════════════════════════════ -->
<div class="modal-backdrop" id="statusModal">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title">🔄 Change Application Status</span>
        <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="statusAppId">
        <div class="form-group">
            <label class="form-label">New Status *</label>
            <select id="newStatus" class="form-control">
                <option value="pending">Pending</option>
                <option value="screening">Screening</option>
                <option value="approved">Approved</option>
                <option value="for_releasing">For Release</option>
                <option value="ready_pickup">Ready for Pick-up</option>
                <option value="completed">Completed</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <div id="interviewDateGroup" class="form-group" style="display:none;">
            <label class="form-label">Interview / Screening Date &amp; Time</label>
            <input type="datetime-local" id="interviewDate" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label">Admin Notes</label>
            <textarea id="adminNotes" class="form-control" placeholder="Optional notes for this status change…"></textarea>
        </div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitStatusChange()">Update Status</button>
    </div>
</div>
</div>

<!-- ══ SCHEDULE INTERVIEW MODAL ═══════════════════════════════════════ -->
<div class="modal-backdrop" id="scheduleInterviewModal">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title">🎤 Schedule Interview</span>
        <button class="modal-close" onclick="closeModal('scheduleInterviewModal')">✕</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="interviewAppId">
        <div class="form-group">
            <label class="form-label">Interview Date &amp; Time *</label>
            <input type="datetime-local" id="interviewScheduleDate" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label">Note (optional)</label>
            <textarea id="interviewNote" class="form-control" placeholder="e.g. Confirmed via Zoom, or any reminder for this interview…"></textarea>
        </div>
        <p style="font-size:.8rem;color:var(--muted);">This moves the application to <strong>Screening</strong> and creates/updates the linked interview appointment (visible in Appointment Management).</p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('scheduleInterviewModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitScheduleInterview()">Schedule Interview</button>
    </div>
</div>
</div>

<script>
async function viewApplication(id) {
    document.getElementById('viewAppBody').innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>';
    openModal('viewAppModal');
    document.getElementById('viewAppStatusBtn').onclick = () => { closeModal('viewAppModal'); changeStatusModal(id, ''); };

    try {
        const res  = await fetch('admin_api.php?action=get_application&id='+id);
        const data = await res.json();
        if (!data.success) { document.getElementById('viewAppBody').innerHTML='<p style="color:var(--danger);">'+data.message+'</p>'; return; }
        const a = data.application;

        // Parse JSON docs
        let idDocs = [], housePhotos = [];
        try { idDocs = JSON.parse(a.id_documents || '[]'); } catch(e){}
        try { housePhotos = JSON.parse(a.house_photos || '[]'); } catch(e){}
        let formDataHtml = '';
        try {
            const fd = JSON.parse(a.form_data || '{}');
            const fdKeys = Object.keys(fd);
            if (fdKeys.length) {
                formDataHtml = '<div class="divider"></div><h4 style="margin-bottom:10px;font-size:.9rem;">Application Form Data</h4><div class="info-grid">';
                fdKeys.forEach(k => { formDataHtml += `<div class="info-item"><label>${k.replace(/_/g,' ')}</label><span>${String(fd[k])}</span></div>`; });
                formDataHtml += '</div>';
            }
        } catch(e){}

        document.getElementById('viewAppBody').innerHTML = `
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
            <div>
                <h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:10px;">Applicant Info</h4>
                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-item"><label>Name</label><span>${e(a.applicant_name)}</span></div>
                    <div class="info-item"><label>Full Name (User)</label><span>${e(a.full_name||'—')}</span></div>
                    <div class="info-item"><label>Email</label><span>${e(a.email||'—')}</span></div>
                    <div class="info-item"><label>Phone</label><span>${e(a.phone||'—')}</span></div>
                    <div class="info-item"><label>Address</label><span>${e(a.address||'—')}</span></div>
                </div>
            </div>
            <div>
                <h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:10px;">Pet & Application</h4>
                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-item"><label>Pet</label><span>${e(a.pet_name||'—')} ${a.pet_breed?'('+e(a.pet_breed)+')':''}</span></div>
                    <div class="info-item"><label>Status</label><span class="badge badge-${a.status}">${a.status.replace(/_/g,' ')}</span></div>
                    <div class="info-item"><label>Applied On</label><span>${fmtDate(a.created_at)}</span></div>
                    <div class="info-item"><label>Interview Date</label><span>${a.interview_datetime?fmtDate(a.interview_datetime):'Not scheduled'}</span></div>
                    <div class="info-item"><label>Terms Accepted</label><span>${a.terms_accepted=='1'?'✅ Yes':'❌ No'}</span></div>
                </div>
            </div>
        </div>

        ${a.message ? `<div class="divider"></div><h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">Message / Reason</h4><p style="font-size:.875rem;line-height:1.6;">${e(a.message)}</p>` : ''}

        ${a.admin_notes ? `<div class="divider"></div><h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">Admin Notes</h4><p style="font-size:.875rem;line-height:1.6;background:var(--surface-alt);padding:10px;border-radius:6px;">${e(a.admin_notes)}</p>` : ''}

        ${idDocs.length ? `<div class="divider"></div><h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">Uploaded Documents</h4><div style="display:flex;gap:8px;flex-wrap:wrap;">${idDocs.map(d=>`<a href="${e(d)}" target="_blank" class="btn btn-ghost btn-sm">📄 ID Document</a>`).join('')}</div>` : ''}

        ${housePhotos.length ? `<div class="divider"></div><h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">House Photos</h4><div style="display:flex;gap:8px;flex-wrap:wrap;">${housePhotos.map(p=>`<a href="${e(p)}" target="_blank"><img src="${e(p)}" style="width:80px;height:60px;object-fit:cover;border-radius:6px;border:1px solid var(--border);"></a>`).join('')}</div>` : ''}

        ${formDataHtml}
        `;
    } catch(err) {
        document.getElementById('viewAppBody').innerHTML = '<p style="color:var(--danger);">Failed to load application details.</p>';
    }
}

function e(str) { const d=document.createElement('div'); d.textContent=str||''; return d.innerHTML; }
function fmtDate(s) { try { return new Date(s).toLocaleString(); } catch(e){ return s||'—'; } }

function changeStatusModal(id, currentStatus) {
    document.getElementById('statusAppId').value = id;
    setSelectVal('newStatus', currentStatus||'pending');
    document.getElementById('adminNotes').value = '';
    document.getElementById('interviewDate').value = '';
    toggleInterviewField();
    openModal('statusModal');
}

function toggleInterviewField() {
    const v   = document.getElementById('newStatus').value;
    const grp = document.getElementById('interviewDateGroup');
    grp.style.display = v==='screening' ? 'block' : 'none';
}
document.getElementById('newStatus').addEventListener('change', toggleInterviewField);

async function submitStatusChange() {
    const id     = document.getElementById('statusAppId').value;
    const status = document.getElementById('newStatus').value;
    const notes  = document.getElementById('adminNotes').value;
    const intDt  = document.getElementById('interviewDate').value;

    const body = new URLSearchParams({
        action: 'update_application_status',
        id, status,
        admin_notes: notes,
        interview_datetime: intDt,
    });
    const res  = await fetch('admin_api.php', {method:'POST', body});
    const data = await res.json();
    if (data.success) { showToast('Application updated!'); closeModal('statusModal'); location.reload(); }
    else showToast(data.message,'error');
}

function scheduleInterviewModal(id, currentDatetime) {
    document.getElementById('interviewAppId').value = id;
    document.getElementById('interviewScheduleDate').value = currentDatetime || '';
    document.getElementById('interviewNote').value = '';
    openModal('scheduleInterviewModal');
}

async function submitScheduleInterview() {
    const id    = document.getElementById('interviewAppId').value;
    const intDt = document.getElementById('interviewScheduleDate').value;
    const note  = document.getElementById('interviewNote').value;
    if (!intDt) { showToast('Please pick a date and time','warning'); return; }

    const body = new URLSearchParams({
        action: 'update_application_status',
        id,
        status: 'screening',
        admin_notes: note,
        interview_datetime: intDt,
    });
    const res  = await fetch('admin_api.php', {method:'POST', body});
    const data = await res.json();
    if (data.success) { showToast('Interview scheduled!'); closeModal('scheduleInterviewModal'); location.reload(); }
    else showToast(data.message,'error');
}

function setSelectVal(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    for (let i=0; i<el.options.length; i++) if (el.options[i].value===val){ el.selectedIndex=i; break; }
}
</script>
