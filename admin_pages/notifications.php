<?php
require_permission($conn, 'manage_notifications');

// Recent notification history
$history = [];
$result = $conn->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 30");
if ($result) while ($row = $result->fetch_assoc()) $history[] = $row;

// Pending applicants for per-applicant notification
$pendingApplicants = [];
$r = $conn->query(
    "SELECT aa.id as app_id, aa.applicant_name, aa.status, p.name as pet_name
     FROM adoption_applications aa
     LEFT JOIN pets p ON aa.pet_id = p.id
     WHERE aa.status IN('pending','screening','approved','for_releasing','ready_pickup')
     ORDER BY aa.created_at DESC LIMIT 50"
);
if ($r) while ($row = $r->fetch_assoc()) $pendingApplicants[] = $row;

$templates = [
    'approved'    => ['subject'=>'Congratulations! Your Application is Approved','message'=>"Dear [Name],\n\nWe are happy to inform you that your adoption application has been approved!\n\nPlease visit our shelter to complete the adoption process.\n\nBest regards,\nAniPet Team"],
    'interview'   => ['subject'=>'Interview Scheduled','message'=>"Dear [Name],\n\nYour adoption interview is scheduled for [Date] at [Time].\n\nPlease confirm your attendance by replying to this email.\n\nBest regards,\nAniPet Team"],
    'new_pet'     => ['subject'=>'New Pet Available for Adoption!','message'=>"Hello,\n\nWe have exciting news — a new pet is now available for adoption!\n\nVisit our app or website to view the pet and submit your application.\n\nBest regards,\nAniPet Team"],
    'appointment' => ['subject'=>'Appointment Reminder','message'=>"Reminder: You have an appointment scheduled for [Date] at [Time].\n\nIf you need to reschedule, please contact us as soon as possible.\n\nBest regards,\nAniPet Team"],
    'rejected'    => ['subject'=>'Adoption Application Update','message'=>"Dear [Name],\n\nThank you for your interest in adopting from AniPet.\n\nAfter careful consideration, we are unable to approve your application at this time. You are welcome to apply for a different pet in the future.\n\nBest regards,\nAniPet Team"],
];
?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start;">

<!-- ══ LEFT: SEND FORM ════════════════════════════════════════════════ -->
<div>
<div class="card">
    <div class="card-header">
        <div class="card-title">📤 Send Notification</div>
    </div>

    <div class="tabs" data-tg="notif">
        <button class="tab-btn active" data-tab="bulk" onclick="switchTab('notif','bulk')">Bulk / Group</button>
        <button class="tab-btn" data-tab="applicant" onclick="switchTab('notif','applicant')">Per Applicant</button>
    </div>

    <!-- Bulk send -->
    <div class="tab-pane active" data-tg="notif" data-tab="bulk">
    <form id="notifForm">
        <div class="form-group">
            <label class="form-label">Recipient Group *</label>
            <select name="recipient_group" id="notif_group" class="form-control" required>
                <option value="">Select…</option>
                <option value="all">All Users</option>
                <option value="applicants">Adoption Applicants</option>
                <option value="pending_applicants">Pending Applicants</option>
                <option value="approved_applicants">Approved Applicants</option>
                <option value="pet_owners">Pet Owners</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notification Type</label>
            <select name="notification_type" id="notif_type" class="form-control">
                <option value="announcement">📢 Announcement</option>
                <option value="reminder">⏰ Reminder</option>
                <option value="status_update">🔄 Status Update</option>
                <option value="alert">⚠️ Alert</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Subject *</label>
            <input type="text" name="subject" id="notif_subject" class="form-control" required placeholder="Notification subject">
        </div>
        <div class="form-group">
            <label class="form-label">Message *</label>
            <textarea name="message" id="notif_message" class="form-control" style="min-height:120px;" required placeholder="Type your message…"></textarea>
        </div>
        <button type="submit" class="btn btn-success w-full">Send Notification</button>
    </form>
    </div>

    <!-- Per applicant -->
    <div class="tab-pane" data-tg="notif" data-tab="applicant">
    <form id="notifApplicantForm">
        <input type="hidden" name="recipient_group" value="applicant">
        <div class="form-group">
            <label class="form-label">Select Applicant *</label>
            <select name="applicant_id" id="notif_applicant" class="form-control" required>
                <option value="">Choose applicant…</option>
                <?php foreach ($pendingApplicants as $a): ?>
                <option value="<?php echo $a['app_id']; ?>">
                    <?php echo htmlspecialchars($a['applicant_name'].' — '.$a['pet_name'].' ('.ucwords(str_replace('_',' ',$a['status'])).')'); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Notification Type</label>
            <select name="notification_type" class="form-control">
                <option value="status_update">🔄 Status Update</option>
                <option value="reminder">⏰ Reminder</option>
                <option value="announcement">📢 Announcement</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Subject *</label>
            <input type="text" name="subject" id="notif_app_subject" class="form-control" required placeholder="Message subject">
        </div>
        <div class="form-group">
            <label class="form-label">Message *</label>
            <textarea name="message" id="notif_app_message" class="form-control" style="min-height:120px;" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary w-full">Send to Applicant</button>
    </form>
    </div>
</div>

<!-- Templates -->
<div class="card">
    <div class="card-header">
        <div class="card-title">📝 Quick Templates</div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;">
    <?php foreach ($templates as $key => $tpl): ?>
    <button class="btn btn-ghost" style="justify-content:flex-start;text-align:left;"
            onclick='useTemplate(<?php echo json_encode($tpl); ?>)'>
        <?php echo htmlspecialchars($tpl['subject']); ?>
    </button>
    <?php endforeach; ?>
    </div>
</div>
</div>

<!-- ══ RIGHT: HISTORY ════════════════════════════════════════════════ -->
<div>
<div class="card">
    <div class="card-header">
        <div class="card-title">📜 Recent Sent Notifications</div>
        <span style="font-size:.8rem;color:var(--muted);">Last 30</span>
    </div>

    <?php if (empty($history)): ?>
    <div class="empty-state"><div class="empty-icon">📭</div><p>No notifications sent yet</p></div>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:0;">
    <?php foreach ($history as $h):
        $typeIcon = ['announcement'=>'📢','reminder'=>'⏰','status_update'=>'🔄','alert'=>'⚠️'][$h['notification_type']] ?? '📬';
        $groupLabel = ['all'=>'All Users','applicants'=>'Applicants','pending_applicants'=>'Pending','approved_applicants'=>'Approved','pet_owners'=>'Pet Owners','applicant'=>'Applicant'][$h['recipient_group']] ?? $h['recipient_group'];
    ?>
    <div style="padding:12px 0;border-bottom:1px solid var(--border);">
        <div style="display:flex;align-items:flex-start;gap:10px;">
            <span style="font-size:1.1rem;margin-top:2px;"><?php echo $typeIcon; ?></span>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.875rem;font-weight:600;"><?php echo htmlspecialchars($h['subject']); ?></div>
                <div style="font-size:.78rem;color:var(--muted);margin-top:2px;">
                    To: <?php echo $groupLabel; ?> &bull;
                    <?php echo date('M d, Y g:i a', strtotime($h['created_at'])); ?>
                </div>
                <div style="font-size:.8rem;color:var(--text-light);margin-top:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    <?php echo htmlspecialchars(substr($h['message'],0,80)); ?>…
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</div>

</div><!-- end grid -->

<script>
document.getElementById('notifForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const res  = await fetch('admin_api.php?action=send_notification', {method:'POST', body: new URLSearchParams(Object.fromEntries(fd))});
    const data = await res.json();
    if (data.success) { showToast('Notification sent!'); e.target.reset(); location.reload(); }
    else showToast(data.message,'error');
});

document.getElementById('notifApplicantForm').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target);
    const params = new URLSearchParams(Object.fromEntries(fd));
    params.append('action','send_notification');
    const res  = await fetch('admin_api.php', {method:'POST', body: params});
    const data = await res.json();
    if (data.success) { showToast('Notification sent to applicant!'); e.target.reset(); location.reload(); }
    else showToast(data.message,'error');
});

function useTemplate(tpl) {
    // Fill whichever tab is active
    const subjectEl = document.querySelector('.tab-pane.active input[name="subject"]') || document.getElementById('notif_subject');
    const messageEl = document.querySelector('.tab-pane.active textarea[name="message"]') || document.getElementById('notif_message');
    if (subjectEl) subjectEl.value = tpl.subject;
    if (messageEl) messageEl.value = tpl.message;
}
</script>
