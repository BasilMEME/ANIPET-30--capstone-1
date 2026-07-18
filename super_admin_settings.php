<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('configure_system');

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

$settings = fetchRows($conn, "SELECT id, setting_key, setting_value, description, updated_at FROM system_settings ORDER BY setting_key ASC");
$settingsByKey = [];
foreach ($settings as $setting) {
    $settingsByKey[$setting['setting_key']] = $setting['setting_value'];
}
$shelters = fetchRows($conn, "SELECT id, name, address, phone, email, status, created_at FROM shelters ORDER BY created_at DESC");
$backups = [];
$backupDir = __DIR__ . '/backups';
if (is_dir($backupDir)) {
    $files = array_values(array_filter(scandir($backupDir), function ($file) use ($backupDir) {
        return is_file($backupDir . '/' . $file) && preg_match('/\.sql$/i', $file);
    }));
    rsort($files);
    foreach ($files as $file) {
        $backups[] = ['file' => $file, 'path' => 'php-backend/backups/' . $file];
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin Settings</title>
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
        .input-group{display:grid;gap:10px;margin-bottom:16px;}
        .input-group label{font-size:.95rem;color:var(--muted);}
        .input-group input, .input-group select, .input-group textarea{width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.06);color:var(--text);font-size:0.95rem;min-height:46px;}
        .input-group input:focus, .input-group select:focus, .input-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(242,134,126,.18);}
        .section{margin-top:28px;}
        .section h2{margin-bottom:18px;font-size:1.12rem;}
        .note{color:var(--muted);font-size:.94rem;line-height:1.6;}
        .action-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;}
        @media (max-width: 1000px){ .grid{grid-template-columns:1fr;} }
        @media (max-width: 760px){ .container{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>System Settings & Database Tools</h1>
            <p class="note">Configure site, shelter, contact, email, notification settings, and manage backups.</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
    </div>

    <section class="section">
        <div class="card">
            <h2>Settings Overview</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Key</th><th>Value</th><th>Updated</th></tr></thead>
                    <tbody>
                    <?php foreach ($settings as $setting): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($setting['setting_key']); ?></td>
                            <td><?php echo htmlspecialchars($setting['setting_value']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($setting['updated_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section grid">
        <div class="card">
            <h2>Site & Contact Configuration</h2>
            <form id="siteConfigForm">
                <div class="input-group"><label>Site Name</label><input type="text" name="site_name" value="<?php echo htmlspecialchars($settingsByKey['site_name'] ?? 'AniPet'); ?>"></div>
                <div class="input-group"><label>Site URL</label><input type="text" name="site_url" value="<?php echo htmlspecialchars($settingsByKey['site_url'] ?? ''); ?>"></div>
                <div class="input-group"><label>Support Phone</label><input type="text" name="support_phone" value="<?php echo htmlspecialchars($settingsByKey['support_phone'] ?? ''); ?>"></div>
                <div class="input-group"><label>Contact Email</label><input type="email" name="contact_email" value="<?php echo htmlspecialchars($settingsByKey['contact_email'] ?? ''); ?>"></div>
                <div class="action-row"><button class="btn btn-primary" type="submit">Save Contact Settings</button></div>
            </form>
        </div>
        <div class="card">
            <h2>Email & Notification Settings</h2>
            <form id="notificationForm">
                <div class="input-group"><label>SMTP Host</label><input type="text" name="smtp_host" value="<?php echo htmlspecialchars($settingsByKey['smtp_host'] ?? 'smtp.gmail.com'); ?>"></div>
                <div class="input-group"><label>SMTP Port</label><input type="text" name="smtp_port" value="<?php echo htmlspecialchars($settingsByKey['smtp_port'] ?? '587'); ?>"></div>
                <div class="input-group"><label>Use SMTP</label><select name="use_smtp"><option value="1" <?php echo (($settingsByKey['use_smtp'] ?? '1') === '1') ? 'selected' : ''; ?>>Yes</option><option value="0" <?php echo (($settingsByKey['use_smtp'] ?? '1') === '0') ? 'selected' : ''; ?>>No</option></select></div>
                <div class="input-group"><label>SMTP Username</label><input type="text" name="smtp_user" value="<?php echo htmlspecialchars($settingsByKey['smtp_user'] ?? ''); ?>"></div>
                <div class="input-group"><label>SMTP Password</label><input type="password" name="smtp_pass" value="<?php echo htmlspecialchars($settingsByKey['smtp_pass'] ?? ''); ?>"></div>
                <div class="input-group"><label>Mail From Address</label><input type="email" name="smtp_from_email" value="<?php echo htmlspecialchars($settingsByKey['smtp_from_email'] ?? ''); ?>"></div>
                <div class="input-group"><label>Mail From Name</label><input type="text" name="smtp_from_name" value="<?php echo htmlspecialchars($settingsByKey['smtp_from_name'] ?? 'AniPet'); ?>"></div>
                <div class="input-group"><label>Notification Enabled</label><select name="notification_enabled"><option value="1" <?php echo (($settingsByKey['notification_enabled'] ?? '1') === '1') ? 'selected' : ''; ?>>Enabled</option><option value="0" <?php echo (($settingsByKey['notification_enabled'] ?? '1') === '0') ? 'selected' : ''; ?>>Disabled</option></select></div>
                <div class="input-group"><label>Notification Channel</label><input type="text" name="notification_channel" placeholder="e.g. email,sms" value="<?php echo htmlspecialchars($settingsByKey['notification_channel'] ?? 'email'); ?>"></div>
                <div class="input-group"><label>Alert Recipients</label><input type="text" name="alert_recipient_emails" placeholder="admin@example.com,ops@example.com" value="<?php echo htmlspecialchars($settingsByKey['alert_recipient_emails'] ?? ''); ?>"></div>
                <div class="input-group"><label>Pending Application Threshold</label><input type="number" name="alert_pending_applications" min="1" value="<?php echo htmlspecialchars($settingsByKey['alert_pending_applications'] ?? '10'); ?>"></div>
                <div class="input-group"><label>Stalled Application Threshold</label><input type="number" name="alert_stalled_applications" min="1" value="<?php echo htmlspecialchars($settingsByKey['alert_stalled_applications'] ?? '5'); ?>"></div>
                <div class="input-group"><label>Unassigned Application Threshold</label><input type="number" name="alert_unassigned_applications" min="1" value="<?php echo htmlspecialchars($settingsByKey['alert_unassigned_applications'] ?? '5'); ?>"></div>
                <div class="input-group"><label>DB Threads Threshold</label><input type="number" name="alert_threads_running" min="1" value="<?php echo htmlspecialchars($settingsByKey['alert_threads_running'] ?? '25'); ?>"></div>
                <div class="input-group"><label>Aborted Connects Threshold</label><input type="number" name="alert_aborted_connects" min="1" value="<?php echo htmlspecialchars($settingsByKey['alert_aborted_connects'] ?? '10'); ?>"></div>
                <div class="input-group"><label>Test Recipient Email</label><input type="email" id="testRecipientEmail" placeholder="admin@example.com"></div>
                <div class="input-group"><label>Test Subject</label><input type="text" id="testEmailSubject" value="AniPet SMTP Test Email"></div>
                <div class="input-group"><label>Message Body</label><textarea id="testEmailBody" rows="4" style="width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;"><?php echo htmlspecialchars($settingsByKey['test_email_body'] ?? '<p>This is a test email from AniPet Super Admin.</p>'); ?></textarea></div>
                <div class="action-row"><button class="btn btn-primary" type="submit">Save Email Settings</button><button class="btn btn-secondary" id="sendTestEmailBtn" type="button">Send Test Email</button></div>
            </form>
        </div>
        <div class="card">
            <h2>Database Backup / Export</h2>
            <p class="note">Use these actions to create a backup or export of the current database state.</p>
            <div class="action-row">
                <button class="btn btn-primary" id="backupBtn">Backup Database</button>
                <button class="btn btn-secondary" id="exportBtn">Export Database</button>
            </div>
            <div class="note" style="margin-top:16px;">Available backup files are listed below.</div>
            <div class="table-wrap" style="margin-top:12px;">
                <table>
                    <thead><tr><th>File</th><th>Download</th></tr></thead>
                    <tbody id="backupList">
                    <?php if (!empty($backups)): ?>
                        <?php foreach ($backups as $backup): ?>
                            <tr><td><?php echo htmlspecialchars($backup['file']); ?></td><td><a href="backups/<?php echo htmlspecialchars($backup['file']); ?>" class="btn btn-secondary" target="_blank">Download</a></td></tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="2">No backup files found.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="input-group" style="margin-top:18px;"><label>Restore Backup</label>
                <select id="restoreFile" class="input-group" style="width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.14);background:rgba(255,255,255,.04);color:#e2e8f0;">
                    <option value="">Select backup file to restore</option>
                    <?php foreach ($backups as $backup): ?>
                        <option value="<?php echo htmlspecialchars($backup['file']); ?>"><?php echo htmlspecialchars($backup['file']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="action-row"><button class="btn btn-primary" id="restoreBtn">Restore Selected Backup</button></div>
        </div>
    </section>

    <section class="section">
        <div class="card">
            <h2>Shelter Management</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Status</th><th>Created</th></tr></thead>
                    <tbody>
                    <?php foreach ($shelters as $shelter): ?>
                        <tr>
                            <td><?php echo $shelter['id']; ?></td>
                            <td><?php echo htmlspecialchars($shelter['name']); ?></td>
                            <td><?php echo htmlspecialchars($shelter['phone']); ?></td>
                            <td><?php echo htmlspecialchars($shelter['email']); ?></td>
                            <td><?php echo htmlspecialchars($shelter['status']); ?></td>
                            <td><?php echo date('M d, Y', strtotime($shelter['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>
<script>
    const apiEndpoint = 'super_admin_api.php';
    const saveSetting = async (key, value) => {
        const setForm = new FormData();
        setForm.append('action', 'save_setting');
        setForm.append('key', key);
        setForm.append('value', value);
        return fetch(apiEndpoint, { method: 'POST', body: setForm }).then(res => res.json());
    };

    document.getElementById('siteConfigForm').addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        const payload = {
            site_name: formData.get('site_name'),
            site_url: formData.get('site_url'),
            support_phone: formData.get('support_phone'),
            contact_email: formData.get('contact_email')
        };
        Promise.all(Object.entries(payload).map(([key, value]) => saveSetting(key, value)))
            .then(results => alert('Contact settings saved.'))
            .catch(() => alert('Failed to save contact settings.'));
    });

    document.getElementById('notificationForm').addEventListener('submit', (event) => {
        event.preventDefault();
        const formData = new FormData(event.target);
        const payload = {
            smtp_host: formData.get('smtp_host'),
            smtp_port: formData.get('smtp_port'),
            use_smtp: formData.get('use_smtp'),
            smtp_user: formData.get('smtp_user'),
            smtp_pass: formData.get('smtp_pass'),
            smtp_from_email: formData.get('smtp_from_email'),
            smtp_from_name: formData.get('smtp_from_name'),
            notification_enabled: formData.get('notification_enabled'),
            notification_channel: formData.get('notification_channel'),
            alert_recipient_emails: formData.get('alert_recipient_emails'),
            alert_pending_applications: formData.get('alert_pending_applications'),
            alert_stalled_applications: formData.get('alert_stalled_applications'),
            alert_unassigned_applications: formData.get('alert_unassigned_applications'),
            alert_threads_running: formData.get('alert_threads_running'),
            alert_aborted_connects: formData.get('alert_aborted_connects')
        };
        Promise.all(Object.entries(payload).map(([key, value]) => saveSetting(key, value)))
            .then(results => alert('Email and notification settings saved.'))
            .catch(() => alert('Failed to save email settings.'));
    });

    document.getElementById('sendTestEmailBtn').addEventListener('click', () => {
        const recipient = document.getElementById('testRecipientEmail').value.trim();
        const subject = document.getElementById('testEmailSubject').value.trim();
        const body = document.getElementById('testEmailBody').value.trim();
        if (!recipient) { alert('Enter a recipient email for the test message.'); return; }
        const formData = new FormData();
        formData.append('action', 'send_test_email');
        formData.append('recipient_email', recipient);
        formData.append('subject', subject);
        formData.append('body', body);
        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => alert(data.message || 'Test email sent.'))
            .catch(() => alert('Could not send test email.'));
    });

    document.getElementById('backupBtn').addEventListener('click', () => {
        fetch(apiEndpoint + '?action=backup_database', { method: 'POST' })
            .then(res => res.json())
            .then(data => alert(data.message || 'Backup completed.'))
            .catch(() => alert('Backup failed.'));
    });
    document.getElementById('exportBtn').addEventListener('click', () => {
        fetch(apiEndpoint + '?action=export_database', { method: 'POST' })
            .then(res => res.json())
            .then(data => alert(data.message || 'Export completed.'))
            .catch(() => alert('Export failed.'));
    });
    document.getElementById('restoreBtn').addEventListener('click', () => {
        const file = document.getElementById('restoreFile').value;
        if (!file) { alert('Select a backup file to restore.'); return; }
        const formData = new FormData();
        formData.append('action', 'restore_database');
        formData.append('file', file);
        fetch(apiEndpoint, { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => alert(data.message || 'Restore completed.'))
            .catch(() => alert('Restore failed.'));
    });
</script>
</body>
</html>
