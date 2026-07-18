<?php
require_permission($conn, 'manage_settings');
require_once __DIR__ . '/../return_policy_helper.php';

$settings = [];
foreach (RETURN_POLICY_KEYS as $key) $settings[$key] = get_return_policy_setting($conn, $key, '');
$penaltyType = $settings['return_penalty_type'] ?: 'fixed';
$qrFilename  = $settings['donation_qr_filename'] ?: 'donation_qr.jpg';
$qrExists    = is_file(__DIR__ . '/../images/' . $qrFilename);
$isSuperAdmin = current_user_role() === 'super_admin';
?>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Return Penalty Settings</div>
        <div class="card-sub">Configure the penalty applied when an adopted pet is returned</div>
    </div>
</div>

<form id="penaltyForm">
    <div class="form-row cols-2">
        <div class="form-group">
            <label class="form-label">Penalty Type</label>
            <select id="return_penalty_type" name="return_penalty_type" class="form-control" onchange="togglePenaltyFields()">
                <option value="fixed" <?php echo $penaltyType==='fixed'?'selected':''; ?>>Fixed Amount</option>
                <option value="percentage" <?php echo $penaltyType==='percentage'?'selected':''; ?>>Percentage of Base Amount</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label" id="penaltyAmountLabel"><?php echo $penaltyType==='percentage'?'Penalty (%)':'Penalty Amount (₱)'; ?></label>
            <input type="number" id="return_penalty_amount" name="return_penalty_amount" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($settings['return_penalty_amount'] ?: '1000'); ?>">
        </div>
    </div>
    <div class="form-group" id="baseAmountGroup" style="<?php echo $penaltyType==='percentage'?'':'display:none;'; ?>">
        <label class="form-label">Base Amount (₱) — used when penalty type is Percentage</label>
        <input type="number" id="return_penalty_base_amount" name="return_penalty_base_amount" class="form-control" min="0" step="0.01" value="<?php echo htmlspecialchars($settings['return_penalty_base_amount'] ?: '1000'); ?>">
    </div>
    <div class="action-row" style="margin-top:6px;">
        <button class="btn btn-primary" type="submit">Save Penalty Settings</button>
    </div>
</form>
</div>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Dog Pound Beneficiary Info</div>
        <div class="card-sub">Where returned-pet penalty payments are recorded as going to</div>
    </div>
</div>
<form id="poundForm">
    <div class="form-row cols-2">
        <div class="form-group">
            <label class="form-label">Beneficiary Name</label>
            <input type="text" name="dog_pound_name" class="form-control" placeholder="e.g. City Animal Pound" value="<?php echo htmlspecialchars($settings['dog_pound_name']); ?>">
        </div>
        <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input type="text" name="dog_pound_contact" class="form-control" value="<?php echo htmlspecialchars($settings['dog_pound_contact']); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Address</label>
        <input type="text" name="dog_pound_address" class="form-control" value="<?php echo htmlspecialchars($settings['dog_pound_address']); ?>">
    </div>
    <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="dog_pound_notes" class="form-control" placeholder="Optional notes shown to admins about this beneficiary…"><?php echo htmlspecialchars($settings['dog_pound_notes']); ?></textarea>
    </div>
    <div class="action-row" style="margin-top:6px;">
        <button class="btn btn-primary" type="submit">Save Beneficiary Info</button>
    </div>
</form>
</div>

<div class="card">
<div class="card-header">
    <div>
        <div class="card-title">Donations (GCash)</div>
        <div class="card-sub">QR code and account info shown to users who want to donate</div>
    </div>
</div>
<?php if (!$isSuperAdmin): ?>
<p style="font-size:.82rem;color:var(--warning);margin-bottom:14px;">🔒 Only a Super Admin can edit donation/payment settings. Shown below as read-only.</p>
<?php endif; ?>
<form id="donationForm" enctype="multipart/form-data">
    <div class="form-row cols-2">
        <div>
            <div class="form-group">
                <label class="form-label">GCash Account Name</label>
                <input type="text" name="donation_gcash_name" class="form-control" value="<?php echo htmlspecialchars($settings['donation_gcash_name']); ?>" <?php echo $isSuperAdmin?'':'disabled'; ?>>
            </div>
            <div class="form-group">
                <label class="form-label">GCash Number</label>
                <input type="text" name="donation_gcash_number" class="form-control" value="<?php echo htmlspecialchars($settings['donation_gcash_number']); ?>" <?php echo $isSuperAdmin?'':'disabled'; ?>>
            </div>
            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="donation_notes" class="form-control" placeholder="Optional message shown alongside the QR code…" <?php echo $isSuperAdmin?'':'disabled'; ?>><?php echo htmlspecialchars($settings['donation_notes']); ?></textarea>
            </div>
        </div>
        <div>
            <div class="form-group">
                <label class="form-label">Donation QR Code</label>
                <?php if ($qrExists): ?>
                <div style="margin-bottom:10px;">
                    <img src="images/<?php echo htmlspecialchars($qrFilename); ?>" alt="Donation QR" style="width:160px;height:160px;object-fit:contain;border:1px solid var(--border);border-radius:8px;background:#fff;">
                </div>
                <?php else: ?>
                <div class="empty-state" style="padding:16px;"><p style="font-size:.8rem;">No QR code uploaded yet</p></div>
                <?php endif; ?>
                <input type="file" name="donation_qr" accept="image/*" class="form-control" <?php echo $isSuperAdmin?'':'disabled'; ?>>
            </div>
        </div>
    </div>
    <?php if ($isSuperAdmin): ?>
    <div class="action-row" style="margin-top:6px;">
        <button class="btn btn-primary" type="submit">Save Donation Settings</button>
    </div>
    <?php endif; ?>
</form>
</div>

<script>
function togglePenaltyFields() {
    const type = document.getElementById('return_penalty_type').value;
    document.getElementById('penaltyAmountLabel').textContent = type === 'percentage' ? 'Penalty (%)' : 'Penalty Amount (₱)';
    document.getElementById('baseAmountGroup').style.display = type === 'percentage' ? 'block' : 'none';
}

async function saveSettingsForm(formEl, extraFormData) {
    const body = extraFormData || new FormData(formEl);
    body.append('action', 'update_return_policy_settings');
    const res  = await fetch('admin_api.php', {method:'POST', body});
    const data = await res.json();
    if (data.success) showToast('Settings saved!');
    else showToast(data.message, 'error');
    return data.success;
}

document.getElementById('penaltyForm').addEventListener('submit', async e => {
    e.preventDefault();
    await saveSettingsForm(e.target);
});
document.getElementById('poundForm').addEventListener('submit', async e => {
    e.preventDefault();
    await saveSettingsForm(e.target);
});
document.getElementById('donationForm').addEventListener('submit', async e => {
    e.preventDefault();
    const ok = await saveSettingsForm(e.target);
    if (ok) setTimeout(() => location.reload(), 600);
});
</script>
