<?php
require_permission($conn, 'manage_appointments');

$view   = $_GET['view']   ?? 'list';   // list | calendar
$filter = $_GET['apt_status'] ?? '';
$validStatuses = ['pending','approved','rejected'];
if (!in_array($filter, $validStatuses)) $filter = '';

// Counts per status
$counts = ['all'=>0,'pending'=>0,'approved'=>0,'rejected'=>0];
$r = $conn->query("SELECT status, COUNT(*) as cnt FROM appointments GROUP BY status");
if ($r) while ($row = $r->fetch_assoc()) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['cnt'];
    $counts['all'] += (int)$row['cnt'];
}

// Fetch appointments
$sql = "SELECT a.id, a.title, a.details, a.scheduled_at, a.status, a.created_at,
               a.application_id, a.appointment_type,
               p.name AS pet_name, u.full_name, u.email
        FROM appointments a
        LEFT JOIN pets p ON a.pet_id = p.id
        LEFT JOIN users u ON a.user_id = u.id
        WHERE 1=1";
if ($filter) $sql .= " AND a.status='" . $conn->real_escape_string($filter) . "'";
$sql .= " ORDER BY a.scheduled_at DESC";

$appointments = [];
$result = $conn->query($sql);
if ($result) while ($row = $result->fetch_assoc()) $appointments[] = $row;

// Calendar: appointments for current month
// Calendar: appointments for current month
$calYear  = (int) ($_GET['cy'] ?? date('Y'));
$calMonth = (int) ($_GET['cm'] ?? date('n'));

if ($calMonth < 1) {
    $calMonth = 12;
    $calYear--;
}

if ($calMonth > 12) {
    $calMonth = 1;
    $calYear++;
}

$calDays = (int) date(
    't',
    mktime(0, 0, 0, $calMonth, 1, $calYear)
);

$calFirst = (int) date(
    'w',
    mktime(0, 0, 0, $calMonth, 1, $calYear)
);

$calEvents = [];
$r2 = $conn->query(
    "SELECT id, title, scheduled_at, status FROM appointments
     WHERE YEAR(scheduled_at)=".intval($calYear)." AND MONTH(scheduled_at)=".intval($calMonth)
);
if ($r2) while ($row = $r2->fetch_assoc()) {
    $d = (int)date('j', strtotime($row['scheduled_at']));
    if (!isset($calEvents[$d])) $calEvents[$d] = [];
    $calEvents[$d][] = $row;
}
?>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Appointment Management</div>
        <div class="card-sub"><?php echo $counts['all']; ?> total appointments</div>
    </div>
    <div style="display:flex;gap:8px;">
        <a href="?page=appointments&view=list<?php echo $filter?"&apt_status=$filter":''; ?>"
           class="btn <?php echo $view!=='calendar'?'btn-primary':'btn-ghost'; ?> btn-sm">📋 List</a>
        <a href="?page=appointments&view=calendar"
           class="btn <?php echo $view==='calendar'?'btn-primary':'btn-ghost'; ?> btn-sm">📅 Calendar</a>
    </div>
</div>

<?php if ($view !== 'calendar'): ?>
<!-- ══ LIST VIEW ════════════════════════════════════════════════════ -->
<div class="status-pills">
    <a href="?page=appointments" class="s-pill <?php echo !$filter?'active':''; ?>">All <span class="pill-cnt"><?php echo $counts['all']; ?></span></a>
    <a href="?page=appointments&apt_status=pending"  class="s-pill <?php echo $filter==='pending'?'active':''; ?>">Pending <span class="pill-cnt"><?php echo $counts['pending']; ?></span></a>
    <a href="?page=appointments&apt_status=approved" class="s-pill <?php echo $filter==='approved'?'active':''; ?>">Approved <span class="pill-cnt"><?php echo $counts['approved']; ?></span></a>
    <a href="?page=appointments&apt_status=rejected" class="s-pill <?php echo $filter==='rejected'?'active':''; ?>">Rejected <span class="pill-cnt"><?php echo $counts['rejected']; ?></span></a>
</div>
<div class="filters-bar">
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="aptSearch" placeholder="Search by title, client, pet…" oninput="filterTable('aptSearch','aptTable')">
    </div>
</div>

<?php if (empty($appointments)): ?>
<div class="empty-state"><div class="empty-icon">📅</div><p>No appointments found</p></div>
<?php else: ?>
<div class="table-wrap">
<table id="aptTable">
    <thead>
        <tr><th>#</th><th>Title</th><th>Client</th><th>Pet</th><th>Scheduled</th><th>Status</th><th>Actions</th></tr>
    </thead>
    <tbody>
    <?php foreach ($appointments as $apt): ?>
    <tr>
        <td style="color:var(--muted);font-size:.8rem;"><?php echo $apt['id']; ?></td>
        <td style="font-weight:600;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
            <?php echo htmlspecialchars($apt['title']); ?>
            <?php if ($apt['appointment_type'] === 'interview'): ?>
            <div style="font-size:.7rem;font-weight:600;color:var(--accent);">🎤 Interview · App #<?php echo (int)$apt['application_id']; ?></div>
            <?php endif; ?>
        </td>
        <td>
            <div><?php echo htmlspecialchars($apt['full_name'] ?? '—'); ?></div>
            <?php if ($apt['email']): ?><div style="font-size:.76rem;color:var(--muted);"><?php echo htmlspecialchars($apt['email']); ?></div><?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($apt['pet_name'] ?? '—'); ?></td>
        <td style="font-size:.82rem;">
            <?php echo $apt['scheduled_at'] ? date('M d, Y', strtotime($apt['scheduled_at'])).'<br><span style="color:var(--muted);">'.date('H:i', strtotime($apt['scheduled_at'])).'</span>' : '—'; ?>
        </td>
        <td><span class="badge badge-<?php echo strtolower($apt['status']); ?>"><?php echo ucfirst($apt['status']); ?></span></td>
        <td>
            <button class="btn btn-info btn-sm" onclick="viewAppointment(<?php echo $apt['id']; ?>)">View</button>
            <?php if ($apt['status']==='pending'): ?>
            <button class="btn btn-success btn-sm" onclick="updateAptStatus(<?php echo $apt['id']; ?>,'approved')">Approve</button>
            <button class="btn btn-danger btn-sm"  onclick="updateAptStatus(<?php echo $apt['id']; ?>,'rejected')">Reject</button>
            <?php endif; ?>
            <button class="btn btn-warning btn-sm" onclick="rescheduleModal(<?php echo $apt['id']; ?>, '<?php echo $apt['scheduled_at']?str_replace(' ','T',substr($apt['scheduled_at'],0,16)):''; ?>')">Reschedule</button>
            <button class="btn btn-danger btn-sm" onclick="deleteAppointment(<?php echo $apt['id']; ?>)">Delete</button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ══ CALENDAR VIEW ═════════════════════════════════════════════════ -->
<?php
    $prevMonth = $calMonth-1; $prevYear = $calYear;
    if ($prevMonth<1){ $prevMonth=12; $prevYear--; }
    $nextMonth = $calMonth+1; $nextYear = $calYear;
    if ($nextMonth>12){ $nextMonth=1; $nextYear++; }
    $monthName = date('F Y', mktime(0,0,0,$calMonth,1,$calYear));
    $dayNames  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
?>
<div style="max-width:640px;margin:0 auto;">
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
    <a href="?page=appointments&view=calendar&cy=<?php echo $prevYear; ?>&cm=<?php echo $prevMonth; ?>" class="btn btn-ghost btn-sm">← Prev</a>
    <strong style="font-size:1rem;"><?php echo $monthName; ?></strong>
    <a href="?page=appointments&view=calendar&cy=<?php echo $nextYear; ?>&cm=<?php echo $nextMonth; ?>" class="btn btn-ghost btn-sm">Next →</a>
</div>

<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;">
<?php foreach ($dayNames as $dn): ?>
<div style="text-align:center;font-size:.72rem;font-weight:700;color:var(--muted);padding:6px 0;"><?php echo $dn; ?></div>
<?php endforeach; ?>

<?php
// Empty cells before first day
for ($i=0; $i<$calFirst; $i++): ?>
<div></div>
<?php endfor;

$today = (int)date('j');
$todayM = (int)date('n');
$todayY = (int)date('Y');

for ($day=1; $day<=$calDays; $day++):
    $isToday = ($day===$today && $calMonth===$todayM && $calYear===$todayY);
    $hasEvents = isset($calEvents[$day]);
    $evts = $calEvents[$day] ?? [];
?>
<div style="border:1px solid var(--border);border-radius:6px;padding:6px;min-height:60px;background:<?php echo $isToday?'rgba(242,134,126,.08)':($hasEvents?'var(--surface-alt)':'var(--surface)'); ?>;<?php echo $isToday?'border-color:var(--accent);':'' ?>">
    <div style="font-size:.78rem;font-weight:<?php echo $isToday?700:500; ?>;color:<?php echo $isToday?'var(--accent)':'var(--text)'; ?>;"><?php echo $day; ?></div>
    <?php foreach ($evts as $ev): ?>
    <div onclick="viewAppointment(<?php echo $ev['id']; ?>)"
         style="margin-top:2px;font-size:.65rem;background:<?php echo $ev['status']==='approved'?'var(--success)':($ev['status']==='rejected'?'var(--danger)':'var(--warning)'); ?>;color:#fff;border-radius:3px;padding:1px 4px;cursor:pointer;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"
         title="<?php echo htmlspecialchars($ev['title']); ?>">
        <?php echo htmlspecialchars(substr($ev['title'],0,14)); ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endfor; ?>
</div>
</div>
<?php endif; ?>
</div>

<!-- ══ VIEW APPOINTMENT MODAL ═════════════════════════════════════════ -->
<div class="modal-backdrop" id="viewAptModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title">📅 Appointment Details</span>
        <button class="modal-close" onclick="closeModal('viewAptModal')">✕</button>
    </div>
    <div class="modal-body" id="viewAptBody">
        <div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('viewAptModal')">Close</button>
        <button class="btn btn-success" id="aptApproveBtn">Approve</button>
        <button class="btn btn-danger"  id="aptRejectBtn">Reject</button>
        <button class="btn btn-danger" id="aptDeleteBtn">Delete Appointment</button>
    </div>
</div>
</div>

<!-- ══ RESCHEDULE MODAL ════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="rescheduleModal">
<div class="modal">
    <div class="modal-header">
        <span class="modal-title">🗓️ Reschedule Appointment</span>
        <button class="modal-close" onclick="closeModal('rescheduleModal')">✕</button>
    </div>
    <div class="modal-body">
        <input type="hidden" id="rescheduleId">
        <div class="form-group">
            <label class="form-label">New Date &amp; Time *</label>
            <input type="datetime-local" id="rescheduleDate" class="form-control">
        </div>
        <p style="font-size:.82rem;color:var(--muted);">Rescheduling will reset the appointment status back to Pending.</p>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('rescheduleModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitReschedule()">Confirm Reschedule</button>
    </div>
</div>
</div>

<script>
async function viewAppointment(id) {
    document.getElementById('viewAptBody').innerHTML = '<div class="empty-state"><div class="empty-icon">⏳</div><p>Loading…</p></div>';
    openModal('viewAptModal');

    const approveBtn = document.getElementById('aptApproveBtn');
    const rejectBtn  = document.getElementById('aptRejectBtn');
    const deleteBtn  = document.getElementById('aptDeleteBtn');

    approveBtn.onclick = ()=>{ updateAptStatus(id,'approved'); closeModal('viewAptModal'); };
    rejectBtn.onclick  = ()=>{ updateAptStatus(id,'rejected'); closeModal('viewAptModal'); };
    deleteBtn.onclick  = ()=>{ closeModal('viewAptModal'); deleteAppointment(id); };

    try {
        const res  = await fetch('admin_api.php?action=get_appointment&id='+id);
        const data = await res.json();
        if (!data.success) { document.getElementById('viewAptBody').innerHTML='<p style="color:var(--danger);">'+data.message+'</p>'; return; }
        const a = data.appointment;

        approveBtn.style.display = a.status==='pending' ? 'inline-flex' : 'none';
        rejectBtn.style.display  = a.status==='pending' ? 'inline-flex' : 'none';

        document.getElementById('viewAptBody').innerHTML = `
        <h3 style="font-size:1rem;margin-bottom:14px;">${escHtml(a.title)}</h3>
        ${a.appointment_type==='interview' ? `<p style="font-size:.8rem;color:var(--accent);font-weight:600;margin-bottom:10px;">🎤 Adoption interview for ${escHtml(a.pet_name||'—')}</p>` : ''}
        <div class="info-grid">
            <div class="info-item"><label>Client</label><span>${escHtml(a.full_name||'—')}</span></div>
            <div class="info-item"><label>Email</label><span>${escHtml(a.email||'—')}</span></div>
            <div class="info-item"><label>Phone</label><span>${escHtml(a.phone||'—')}</span></div>
            <div class="info-item"><label>Pet</label><span>${escHtml(a.pet_name||'—')} ${a.pet_breed?'('+escHtml(a.pet_breed)+')':''}</span></div>
            <div class="info-item"><label>Scheduled</label><span>${a.scheduled_at ? new Date(a.scheduled_at).toLocaleString() : '—'}</span></div>
            <div class="info-item"><label>Status</label><span class="badge badge-${a.status}">${a.status}</span></div>
            <div class="info-item"><label>Requested</label><span>${new Date(a.created_at).toLocaleDateString()}</span></div>
        </div>
        ${a.details ? `<div class="divider"></div><h4 style="font-size:.85rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px;">Details</h4><p style="font-size:.875rem;line-height:1.6;">${escHtml(a.details)}</p>` : ''}
        `;
    } catch(err) {
        document.getElementById('viewAptBody').innerHTML = '<p style="color:var(--danger);">Failed to load details.</p>';
    }
}

function escHtml(s){ const d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

async function updateAptStatus(id, status) {
    if (!confirm('Mark appointment as '+status+'?')) return;
    const res  = await fetch('admin_api.php', {method:'POST', body: new URLSearchParams({action:'update_appointment_status',id,status})});
    const data = await res.json();
    if (data.success) { showToast('Appointment '+status); location.reload(); }
    else showToast(data.message,'error');
}


async function deleteAppointment(id) {
    const confirmed = confirm(
        'Delete this appointment permanently?\n\n' +
        'This will remove it from the appointment list. ' +
        'The related adoption application will NOT be deleted.'
    );

    if (!confirmed) return;

    try {
        const res = await fetch('admin_api.php', {
            method: 'POST',
            body: new URLSearchParams({
                action: 'delete_appointment',
                id: String(id)
            })
        });

        const text = await res.text();
        let data;

        try {
            data = JSON.parse(text);
        } catch (error) {
            console.error('Delete appointment response:', text);
            showToast('The server returned an invalid response.', 'error');
            return;
        }

        if (data.success) {
            showToast('Appointment deleted.');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast(data.message || 'Unable to delete appointment.', 'error');
        }
    } catch (error) {
        console.error(error);
        showToast('Unable to connect to the server.', 'error');
    }
}

function rescheduleModal(id, currentDt) {
    document.getElementById('rescheduleId').value = id;
    document.getElementById('rescheduleDate').value = currentDt || '';
    openModal('rescheduleModal');
}

async function submitReschedule() {
    const id          = document.getElementById('rescheduleId').value;
    const scheduled_at = document.getElementById('rescheduleDate').value;
    if (!scheduled_at) { showToast('Please select a date','warning'); return; }

    const res  = await fetch('admin_api.php', {method:'POST', body: new URLSearchParams({action:'reschedule_appointment',id,scheduled_at})});
    const data = await res.json();
    if (data.success) { showToast('Appointment rescheduled!'); closeModal('rescheduleModal'); location.reload(); }
    else showToast(data.message,'error');
}
</script>