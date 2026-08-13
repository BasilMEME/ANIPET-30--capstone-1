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
:root{
    --bg:#f4ead9;
    --panel:rgba(255,250,241,.94);
    --panel-soft:#f5e7d4;
    --surface:rgba(255,252,246,.96);
    --text:#3b2417;
    --muted:#916b50;
    --accent:#986038;
    --accent-dark:#754325;
    --border:#d9b996;
    --shadow:0 10px 28px rgba(82,48,27,.10);
    --radius:18px;
}

*{
    box-sizing:border-box;
}

html{
    scroll-behavior:smooth;
}

body{
    margin:0;
    font-family:'Inter',sans-serif;
    color:var(--text);
    min-height:100vh;
    background:
        linear-gradient(rgba(244,234,217,.90),rgba(244,234,217,.90)),
        url('/anipet_admin_wallpaper.png') center/cover fixed no-repeat;
}

.container{
    max-width:1480px;
    margin:0 auto;
    padding:24px 26px 44px;
}

/* HEADER */
.header{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    justify-content:space-between;
    gap:18px;
    margin-bottom:22px;
    padding:22px 24px;
    background:var(--panel);
    border:1px solid var(--border);
    border-radius:22px;
    box-shadow:var(--shadow);
}

.header h1{
    margin:0;
    font-size:clamp(1.6rem,2.4vw,2.25rem);
    color:var(--text);
}

.header .note{
    margin:7px 0 0;
}

.grid{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:18px;
    align-items:start;
}

.card{
    background:var(--panel);
    border:1px solid var(--border);
    border-radius:var(--radius);
    padding:22px;
    box-shadow:var(--shadow);
    transition:transform .18s ease,border-color .18s ease;
}

.card:hover{
    transform:translateY(-1px);
    border-color:#c99d73;
}

.card h2{
    margin:0 0 12px;
    font-size:1.08rem;
    color:var(--text);
}

/* TABLE */
.table-wrap{
    overflow-x:auto;
    border:1px solid var(--border);
    border-radius:15px;
    background:var(--surface);
}

table{
    width:100%;
    border-collapse:collapse;
    color:var(--text);
    table-layout:fixed;
    min-width:720px;
}

th,
td{
    padding:12px 13px;
    text-align:left;
    border-bottom:1px solid rgba(139,91,54,.16);
    overflow-wrap:anywhere;
    word-break:break-word;
}

th{
    color:#6f472c;
    font-size:.78rem;
    text-transform:uppercase;
    letter-spacing:.05em;
    background:#efddc5;
}

td{
    color:var(--text);
    background:rgba(255,252,246,.74);
}

tbody tr:hover td{
    background:#fbefdf;
}

/* BUTTONS */
.btn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 16px;
    border-radius:12px;
    border:1px solid transparent;
    cursor:pointer;
    font-weight:750;
    text-decoration:none;
    transition:transform .18s ease,background .18s ease,box-shadow .18s ease;
}

.btn:hover{
    transform:translateY(-1px);
}

.btn-primary{
    background:var(--accent);
    border-color:var(--accent);
    color:#fffaf3;
    box-shadow:0 6px 14px rgba(117,67,37,.14);
}

.btn-primary:hover{
    background:var(--accent-dark);
}

.btn-secondary{
    background:#fffaf3;
    border-color:var(--border);
    color:var(--text);
}

.btn-secondary:hover{
    background:#efddc5;
}

/* FORMS */
.input-group{
    display:grid;
    gap:7px;
    margin-bottom:14px;
}

.input-group label{
    font-size:.88rem;
    color:#7d583e;
    font-weight:700;
}

.input-group input,
.input-group select,
.input-group textarea{
    width:100%;
    padding:11px 13px;
    border-radius:12px;
    border:1.5px solid var(--border);
    background:rgba(255,252,246,.96);
    color:var(--text);
    font-size:.94rem;
    min-height:45px;
    outline:none;
}

.input-group textarea{
    resize:vertical;
}

.input-group input:focus,
.input-group select:focus,
.input-group textarea:focus{
    border-color:var(--accent);
    box-shadow:0 0 0 3px rgba(152,96,56,.12);
}

.input-group select option{
    background:#fffaf3;
    color:var(--text);
}

.input-group select option:disabled{
    color:#9a7a62;
}

.section{
    margin-top:20px;
}

.section h2{
    margin-bottom:14px;
}

.note{
    color:var(--muted);
    font-size:.9rem;
    line-height:1.55;
}

.action-row{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    margin-top:14px;
}

/* Make the shelter list use full width inside its grid cell cleanly. */
.table-wrap table{
    min-width:700px;
}

@media(max-width:1000px){
    .grid{
        grid-template-columns:1fr;
    }
}

@media(max-width:760px){
    .container{
        padding:14px;
    }

    .header{
        align-items:flex-start;
        flex-direction:column;
        padding:18px;
    }

    .card{
        padding:17px;
    }
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Owner Settings & Database Tools</h1>

<p class="note">
    Manage pet pound information, opening hours,
    notification preferences, shelter details,
    and database backups.
</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
    </div>


    <section class="section grid">
        <div class="card">
    <h2>Pet Pound Information</h2>

    <p class="note">
        These details represent the pet pound in the AniPet system.
    </p>

    <form id="poundInfoForm">

        <div class="input-group">
            <label>Pet Pound Name</label>

            <input
                type="text"
                name="pound_name"
                value="<?php
                    echo htmlspecialchars(
                        $settingsByKey['pound_name']
                            ?? 'AniPet Pet Adoption Center'
                    );
                ?>"
                required
            >
        </div>

        <div class="input-group">
            <label>Contact Email</label>

            <input
                type="email"
                name="contact_email"
                value="<?php
                    echo htmlspecialchars(
                        $settingsByKey['contact_email']
                            ?? 'anipet.adoption@gmail.com'
                    );
                ?>"
                required
            >
        </div>

        <div class="input-group">
            <label>Contact Number</label>

            <input
                type="text"
                name="support_phone"
                placeholder="+63 9XX XXX XXXX"
                value="<?php
                    echo htmlspecialchars(
                        $settingsByKey['support_phone'] ?? ''
                    );
                ?>"
            >
        </div>

        <div class="input-group">
            <label>Address</label>

            <textarea
                name="pound_address"
                rows="4"
                placeholder="Enter the pet pound address"
            ><?php
                echo htmlspecialchars(
                    $settingsByKey['pound_address'] ?? ''
                );
            ?></textarea>
        </div>

        <div class="action-row">
            <button class="btn btn-primary" type="submit">
                Save Pet Pound Information
            </button>
        </div>
    </form>
</div>

<div class="card">
    <h2>Opening Hours</h2>

    <p class="note">
        Set the regular operating hours of the pet pound.
    </p>

    <form id="openingHoursForm">

        <div class="input-group">
            <label>Monday to Friday</label>

            <input
                type="text"
                name="hours_weekdays"
                value="<?php
                    echo htmlspecialchars(
                        $settingsByKey['hours_weekdays']
                            ?? '8:00 AM - 5:00 PM'
                    );
                ?>"
            >
        </div>

        <div class="input-group">
            <label>Saturday</label>

            <input
                type="text"
                name="hours_saturday"
                value="<?php
                    echo htmlspecialchars(
                        $settingsByKey['hours_saturday']
                            ?? '8:00 AM - 12:00 PM'
                    );
                ?>"
            >
        </div>

        <div class="input-group">
            <label>Sunday</label>

            <input
                type="text"
                name="hours_sunday"
                value="<?php
                    echo htmlspecialchars(
                        $settingsByKey['hours_sunday']
                            ?? 'Closed'
                    );
                ?>"
            >
        </div>

        <div class="action-row">
            <button class="btn btn-primary" type="submit">
                Save Opening Hours
            </button>
        </div>
    </form>
</div>

        <div class="card">
    <h2>Notification Preferences</h2>

    <p class="note">
        Email notifications use the existing Gmail OAuth configuration.
        Firebase push notifications will use FCM once integration is complete.
    </p>

    <form id="notificationForm">

        <div class="input-group">
            <label>Email Notifications</label>

            <select name="email_notifications_enabled">
                <option
                    value="1"
                    <?php echo (
                        ($settingsByKey['email_notifications_enabled'] ?? '1')
                        === '1'
                    ) ? 'selected' : ''; ?>
                >
                    Enabled
                </option>

                <option
                    value="0"
                    <?php echo (
                        ($settingsByKey['email_notifications_enabled'] ?? '1')
                        === '0'
                    ) ? 'selected' : ''; ?>
                >
                    Disabled
                </option>
            </select>
        </div>

        <div class="input-group">
            <label>Firebase Push Notifications</label>

            <select name="fcm_notifications_enabled">
                <option
                    value="1"
                    <?php echo (
                        ($settingsByKey['fcm_notifications_enabled'] ?? '1')
                        === '1'
                    ) ? 'selected' : ''; ?>
                >
                    Enabled
                </option>

                <option
                    value="0"
                    <?php echo (
                        ($settingsByKey['fcm_notifications_enabled'] ?? '1')
                        === '0'
                    ) ? 'selected' : ''; ?>
                >
                    Disabled
                </option>
            </select>
        </div>

        <div class="input-group">
            <label>New Adoption Application Alerts</label>

            <select name="notify_new_application">
                <option
                    value="1"
                    <?php echo (
                        ($settingsByKey['notify_new_application'] ?? '1')
                        === '1'
                    ) ? 'selected' : ''; ?>
                >
                    Enabled
                </option>

                <option
                    value="0"
                    <?php echo (
                        ($settingsByKey['notify_new_application'] ?? '1')
                        === '0'
                    ) ? 'selected' : ''; ?>
                >
                    Disabled
                </option>
            </select>
        </div>

        <div class="input-group">
            <label>Application Status Notifications</label>

            <select name="notify_status_update">
                <option
                    value="1"
                    <?php echo (
                        ($settingsByKey['notify_status_update'] ?? '1')
                        === '1'
                    ) ? 'selected' : ''; ?>
                >
                    Enabled
                </option>

                <option
                    value="0"
                    <?php echo (
                        ($settingsByKey['notify_status_update'] ?? '1')
                        === '0'
                    ) ? 'selected' : ''; ?>
                >
                    Disabled
                </option>
            </select>
        </div>

        <div class="input-group">
            <label>Donation Notifications</label>

            <select name="notify_donation_received">
                <option
                    value="1"
                    <?php echo (
                        ($settingsByKey['notify_donation_received'] ?? '1')
                        === '1'
                    ) ? 'selected' : ''; ?>
                >
                    Enabled
                </option>

                <option
                    value="0"
                    <?php echo (
                        ($settingsByKey['notify_donation_received'] ?? '1')
                        === '0'
                    ) ? 'selected' : ''; ?>
                >
                    Disabled
                </option>
            </select>
        </div>

        <div class="input-group">
            <label>Ready for Pick Up Reminders</label>

            <select name="notify_pickup_reminder">
                <option
                    value="1"
                    <?php echo (
                        ($settingsByKey['notify_pickup_reminder'] ?? '1')
                        === '1'
                    ) ? 'selected' : ''; ?>
                >
                    Enabled
                </option>

                <option
                    value="0"
                    <?php echo (
                        ($settingsByKey['notify_pickup_reminder'] ?? '1')
                        === '0'
                    ) ? 'selected' : ''; ?>
                >
                    Disabled
                </option>
            </select>
        </div>

        <div class="action-row">
            <button class="btn btn-primary" type="submit">
                Save Notification Preferences
            </button>
        </div>
    </form>
</div>
        <div class="card">
            <h2>Shelter Management</h2>
            <p class="note">View the shelters currently registered in AniPet.</p>

            <div class="table-wrap" style="margin-top:14px;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($shelters)): ?>
                        <?php foreach ($shelters as $shelter): ?>
                            <tr>
                                <td><?php echo (int)$shelter['id']; ?></td>
                                <td><?php echo htmlspecialchars($shelter['name']); ?></td>
                                <td><?php echo htmlspecialchars($shelter['phone'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($shelter['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($shelter['status'] ?? ''); ?></td>
                                <td>
                                    <?php
                                        echo !empty($shelter['created_at'])
                                            ? date('M d, Y', strtotime($shelter['created_at']))
                                            : '-';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No shelters found.</td>
                        </tr>
                    <?php endif; ?>
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

    async function saveFormSettings(form, successMessage) {
    const formData = new FormData(form);
    const settings = Array.from(formData.entries());

    try {
        const results = await Promise.all(
            settings.map(([key, value]) => {
                return saveSetting(key, value);
            })
        );

        const failed = results.find(result => !result.success);

        if (failed) {
            throw new Error(
                failed.message || 'One or more settings could not be saved.'
            );
        }

        alert(successMessage);

        // Reload so the UI reflects what was actually saved.
        window.location.reload();

    } catch (error) {
        console.error(error);

        alert(
            error.message ||
            'The settings could not be saved.'
        );
    }
}

    document.getElementById('poundInfoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveFormSettings(this, 'Pet pound information saved successfully.');
    });

    document.getElementById('openingHoursForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveFormSettings(this, 'Opening hours saved successfully.');
    });

    document.getElementById('notificationForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveFormSettings(this, 'Notification preferences saved successfully.');
    });
</script>
</body>
</html>