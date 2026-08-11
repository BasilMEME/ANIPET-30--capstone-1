<?php
require_once __DIR__ . '/auth_helper.php';
require_admin_or_super();

$page = $_GET['page'] ?? 'dashboard';

$validPages = [
    'dashboard',
    'pets',
    'applications',
    'appointments',
    'users',
    'notifications',
    'reports',
    'pet_pound',
    'penalty_payments',
    'settings'
];

if (!in_array($page, $validPages)) {
    $page = 'dashboard';
}

// Admin display name
$adminName = 'Admin';
$stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$stmt->bind_result($adminName);
$stmt->fetch();
$stmt->close();

// Pending badge counts
$pendingApps    = (int)($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$pendingApts    = (int)($conn->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetch_row()[0] ?? 0);
$notifBadge     = $pendingApps + $pendingApts;

$pageInfo = [
    'dashboard'         => ['title' => 'Dashboard',              'icon' => 'ðŸ“Š', 'sub' => 'Overview & statistics'],
    'pets'              => ['title' => 'Pet Management',         'icon' => 'ðŸ¾', 'sub' => 'Manage shelter pets'],
    'applications'      => ['title' => 'Adoption Applications',  'icon' => 'ðŸ“‹', 'sub' => 'Review & process applications'],
    'appointments'      => ['title' => 'Appointments',           'icon' => 'ðŸ“…', 'sub' => 'Schedule & manage appointments'],
    'users'             => ['title' => 'User Management',        'icon' => 'ðŸ‘¥', 'sub' => 'Manage adopter accounts'],
    'notifications'     => ['title' => 'Notifications',          'icon' => 'ðŸ””', 'sub' => 'Send announcements & reminders'],
    'reports'           => ['title' => 'Reports',                'icon' => 'ðŸ“ˆ', 'sub' => 'Generate & export reports'],
    'pet_pound'         => ['title' => 'Pet Pound',              'icon' => 'ðŸ ', 'sub' => 'Manage impounded pets'],
    'penalty_payments'  => ['title' => 'Penalty Payments',       'icon' => 'ðŸ’³', 'sub' => 'View penalty payment history'],
    'settings'          => ['title' => 'Settings',               'icon' => 'âš™ï¸', 'sub' => 'Penalty, beneficiary & donation settings'],
];
$pi = $pageInfo[$page];
$isSuperAdmin = current_user_role() === 'super_admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/anipet_reference_theme.css?v=20260811">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>AniPet Admin â€” <?php echo htmlspecialchars($pi['title']); ?></title>
<style>
:root{
    --bg:#fdf3ef;--surface:#fff;--surface-alt:#faf3f0;
    --sidebar-bg:#1b2a41;--sidebar-hover:#25384f;
    --text:#1b2a41;--text-light:#64748b;--muted:#94a3b8;
    --border:rgba(148,163,184,.2);
    --accent:#f2867e;--accent-hover:#e56f66;
    --success:#16a34a;--warning:#d97706;--danger:#dc2626;--info:#0891b2;
    --shadow-sm:0 1px 3px rgba(0,0,0,.08);
    --shadow:0 4px 12px rgba(15,23,42,.10);
    --shadow-lg:0 8px 24px rgba(15,23,42,.16);
    --radius:12px;--radius-sm:8px;--sidebar-w:258px;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',system-ui,-apple-system,sans-serif;background:var(--bg);color:var(--text);min-height:100vh;display:flex;}

/* SIDEBAR */
.sidebar{width:var(--sidebar-w);background-color:var(--sidebar-bg);background-image:linear-gradient(rgba(27,42,65,.88),rgba(27,42,65,.94)),url('/anipet_admin_wallpaper.png');background-position:left center;background-size:auto 100%;background-repeat:no-repeat;color:#fff;position:fixed;inset:0 auto 0 0;display:flex;flex-direction:column;z-index:100;transition:transform .3s ease;overflow-y:auto;}
.sidebar-brand{padding:18px 20px;border-bottom:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:12px;flex-shrink:0;}
.brand-logo{width:40px;height:40px;background:linear-gradient(135deg,#f2867e,#1b2a41);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;}
.brand-text h2{font-size:1.05rem;font-weight:700;}
.brand-text p{font-size:.68rem;color:var(--muted);text-transform:uppercase;letter-spacing:.1em;margin-top:2px;}
.sidebar-nav{flex:1;padding:12px 0;}
.nav-label{padding:12px 20px 4px;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--muted);}
.nav-link{display:flex;align-items:center;gap:11px;padding:9px 20px;color:rgba(255,255,255,.6);text-decoration:none;border-left:3px solid transparent;transition:all .18s;font-size:.875rem;position:relative;}
.nav-link:hover{background:var(--sidebar-hover);color:#fff;border-left-color:rgba(255,255,255,.3);}
.nav-link.active{background:rgba(242,134,126,.25);color:#fbcac5;border-left-color:var(--accent);font-weight:600;}
.nav-icon{font-size:1rem;width:20px;text-align:center;flex-shrink:0;}
.nav-badge{margin-left:auto;background:var(--danger);color:#fff;font-size:.6rem;font-weight:700;padding:2px 6px;border-radius:10px;min-width:18px;text-align:center;}
.sidebar-footer{border-top:1px solid rgba(255,255,255,.08);padding:14px;flex-shrink:0;}
.admin-profile{display:flex;align-items:center;gap:10px;padding:10px;border-radius:var(--radius-sm);background:rgba(255,255,255,.06);}
.admin-avatar{width:36px;height:36px;background:linear-gradient(135deg,#f2867e,#1b2a41);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:#fff;flex-shrink:0;}
.admin-info{flex:1;min-width:0;}
.admin-name{font-size:.83rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.admin-role{font-size:.68rem;color:var(--muted);}
.logout-link{color:var(--muted);text-decoration:none;font-size:1rem;padding:4px;border-radius:4px;transition:color .2s;flex-shrink:0;}
.logout-link:hover{color:var(--danger);}

/* MAIN */
.main{margin-left:var(--sidebar-w);flex:1;display:flex;flex-direction:column;min-height:100vh;}
.topbar{background:var(--surface);border-bottom:1px solid var(--border);padding:0 24px;height:62px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow-sm);position:sticky;top:0;z-index:50;}
.hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;border-radius:6px;color:var(--text-light);font-size:1.25rem;transition:background .2s;line-height:1;}
.hamburger:hover{background:var(--surface-alt);}
.page-breadcrumb{flex:1;}
.page-breadcrumb h1{font-size:1.1rem;font-weight:700;line-height:1.2;}
.page-breadcrumb small{font-size:.78rem;color:var(--text-light);}
.topbar-right{display:flex;align-items:center;gap:10px;}
.notif-btn{position:relative;background:none;border:none;cursor:pointer;padding:8px;border-radius:8px;color:var(--text-light);font-size:1.1rem;transition:background .2s;}
.notif-btn:hover{background:var(--surface-alt);color:var(--text);}
.notif-count{position:absolute;top:2px;right:2px;background:var(--danger);color:#fff;font-size:.58rem;font-weight:700;padding:1px 4px;border-radius:8px;min-width:16px;text-align:center;}
.topbar-date{font-size:.8rem;color:var(--text-light);display:none;}
@media(min-width:900px){.topbar-date{display:block;}}
.content{flex:1;padding:24px;overflow-y:auto;}

/* CARDS */
.card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:22px;box-shadow:var(--shadow-sm);margin-bottom:22px;}
.card-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;gap:14px;flex-wrap:wrap;}
.card-title{font-size:1rem;font-weight:700;}
.card-sub{font-size:.78rem;color:var(--text-light);margin-top:2px;}

/* STAT CARDS */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:14px;margin-bottom:22px;}
.stat-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:18px 16px;box-shadow:var(--shadow-sm);display:flex;align-items:flex-start;gap:12px;transition:box-shadow .2s,transform .2s;}
.stat-card:hover{box-shadow:var(--shadow);transform:translateY(-1px);}
.stat-icon{width:42px;height:42px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;}
.stat-body{flex:1;min-width:0;}
.stat-value{font-size:1.75rem;font-weight:800;line-height:1;color:var(--text);}
.stat-label{font-size:.78rem;color:var(--text-light);margin-top:4px;}

/* TABLE */
.table-wrap{overflow-x:auto;}
table{width:100%;border-collapse:collapse;font-size:.875rem;}
th{background:var(--surface-alt);padding:9px 12px;text-align:left;color:var(--text-light);font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;border-bottom:1px solid var(--border);white-space:nowrap;}
td{padding:11px 12px;border-bottom:1px solid var(--border);vertical-align:middle;}
tbody tr:last-child td{border-bottom:none;}
tbody tr:hover{background:var(--surface-alt);}

/* BADGES */
.badge{display:inline-flex;align-items:center;padding:3px 9px;border-radius:20px;font-size:.72rem;font-weight:600;white-space:nowrap;}
.badge-pending{background:#fef3c7;color:#92400e;}
.badge-screening{background:#dbeafe;color:#1e40af;}
.badge-approved{background:#dcfce7;color:#14532d;}
.badge-for_releasing{background:#e0e7ff;color:#3730a3;}
.badge-ready_pickup{background:#fde68a;color:#78350f;}
.badge-completed{background:#d1fae5;color:#065f46;}
.badge-rejected{background:#fee2e2;color:#991b1b;}
.badge-available{background:#dcfce7;color:#14532d;}
.badge-reserved{background:#fef3c7;color:#92400e;}
.badge-in_adoption{background:#e0e7ff;color:#3730a3;}
.badge-adopted{background:#d1fae5;color:#065f46;}
.badge-under_treatment{background:#fee2e2;color:#991b1b;}
.badge-archived{background:#f1f5f9;color:#475569;}
.badge-active{background:#dcfce7;color:#14532d;}
.badge-suspended{background:#fee2e2;color:#991b1b;}

/* BUTTONS */
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 14px;border-radius:8px;border:none;cursor:pointer;font-weight:600;font-size:.85rem;transition:all .15s;text-decoration:none;white-space:nowrap;}
.btn:disabled{opacity:.5;cursor:not-allowed;}
.btn-primary{background:var(--accent);color:#fff;}  .btn-primary:hover{background:var(--accent-hover);}
.btn-success{background:var(--success);color:#fff;}  .btn-success:hover{background:#15803d;}
.btn-danger {background:var(--danger); color:#fff;}  .btn-danger:hover {background:#b91c1c;}
.btn-warning{background:var(--warning);color:#fff;}  .btn-warning:hover{background:#b45309;}
.btn-info   {background:var(--info);   color:#fff;}  .btn-info:hover   {background:#0e7490;}
.btn-ghost  {background:transparent;color:var(--text-light);border:1.5px solid var(--border);}
.btn-ghost:hover{background:var(--surface-alt);color:var(--text);}
.btn-sm{padding:5px 10px;font-size:.78rem;border-radius:6px;}
.btn-icon{padding:6px;border-radius:6px;}

/* FORMS */
.form-group{margin-bottom:14px;}
.form-label{display:block;font-size:.82rem;font-weight:600;margin-bottom:5px;color:var(--text);}
.form-control{width:100%;padding:9px 11px;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;font-size:.875rem;color:var(--text);background:var(--surface);transition:border-color .2s,box-shadow .2s;outline:none;}
.form-control:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(242,134,126,.12);}
textarea.form-control{resize:vertical;min-height:80px;}
select.form-control{cursor:pointer;}
.form-row{display:grid;gap:14px;}
.cols-2{grid-template-columns:1fr 1fr;}
.cols-3{grid-template-columns:1fr 1fr 1fr;}

/* MODAL */
.modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.5);z-index:200;display:none;align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(2px);}
.modal-backdrop.open{display:flex;}
.modal{background:var(--surface);border-radius:var(--radius);box-shadow:var(--shadow-lg);width:100%;max-width:580px;max-height:92vh;display:flex;flex-direction:column;animation:modalIn .2s ease;}
.modal-lg{max-width:760px;}
.modal-xl{max-width:940px;}
@keyframes modalIn{from{opacity:0;transform:scale(.96) translateY(-8px);}}
.modal-header{padding:18px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-shrink:0;}
.modal-title{font-size:1rem;font-weight:700;}
.modal-close{background:none;border:none;cursor:pointer;color:var(--text-light);font-size:1.2rem;padding:4px 8px;border-radius:4px;transition:all .2s;line-height:1;}
.modal-close:hover{background:var(--surface-alt);color:var(--text);}
.modal-body{padding:22px;overflow-y:auto;flex:1;}
.modal-footer{padding:14px 22px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px;flex-shrink:0;}

/* TABS */
.tabs{display:flex;gap:2px;margin-bottom:18px;border-bottom:2px solid var(--border);}
.tab-btn{background:none;border:none;cursor:pointer;padding:9px 14px;font-size:.85rem;font-weight:600;color:var(--text-light);border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .2s;white-space:nowrap;}
.tab-btn:hover{color:var(--text);}
.tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
.tab-pane{display:none;}
.tab-pane.active{display:block;}

/* STATUS PILLS */
.status-pills{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px;}
.s-pill{padding:5px 12px;border-radius:20px;font-size:.78rem;font-weight:600;cursor:pointer;border:1.5px solid var(--border);background:var(--surface);color:var(--text-light);transition:all .18s;text-decoration:none;display:inline-flex;align-items:center;gap:5px;}
.s-pill:hover{border-color:var(--accent);color:var(--accent);background:rgba(242,134,126,.05);}
.s-pill.active{background:var(--accent);color:#fff;border-color:var(--accent);}
.pill-cnt{background:rgba(0,0,0,.08);padding:0 5px;border-radius:8px;font-size:.68rem;}
.s-pill.active .pill-cnt{background:rgba(255,255,255,.25);}

/* FILTER BAR */
.filters-bar{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px;align-items:center;}
.search-wrap{position:relative;flex:1;min-width:180px;}
.search-wrap input{width:100%;padding:9px 11px 9px 34px;border:1.5px solid var(--border);border-radius:8px;font-size:.875rem;outline:none;transition:border-color .2s;}
.search-wrap input:focus{border-color:var(--accent);}
.search-icon{position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--muted);font-size:.9rem;}

/* TOAST */
#toast-container{position:fixed;bottom:20px;right:20px;z-index:9999;display:flex;flex-direction:column;gap:8px;}
.toast{padding:11px 16px;border-radius:8px;background:var(--text);color:#fff;font-size:.85rem;font-weight:500;box-shadow:var(--shadow-lg);animation:toastIn .3s ease;max-width:300px;}
.toast-success{background:var(--success);}
.toast-error{background:var(--danger);}
.toast-warning{background:var(--warning);}
@keyframes toastIn{from{opacity:0;transform:translateX(16px);}}

/* MISC */
.pet-thumb{width:40px;height:40px;border-radius:8px;object-fit:cover;background:var(--surface-alt);display:inline-flex;align-items:center;justify-content:center;font-size:1.15rem;flex-shrink:0;color:var(--muted);overflow:hidden;vertical-align:middle;}
.pet-thumb img{width:100%;height:100%;object-fit:cover;}
.empty-state{text-align:center;padding:36px;color:var(--text-light);}
.empty-state .empty-icon{font-size:2.2rem;margin-bottom:10px;}
.divider{height:1px;background:var(--border);margin:18px 0;}
.info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.info-item label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin-bottom:3px;}
.info-item span{font-size:.875rem;color:var(--text);}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:99;}

/* RESPONSIVE */
@media(max-width:768px){
    .sidebar{transform:translateX(-100%);}
    .sidebar.open{transform:translateX(0);}
    .sidebar-overlay.open{display:block;}
    .main{margin-left:0;}
    .hamburger{display:flex;}
    .content{padding:14px;}
    .cols-2,.cols-3{grid-template-columns:1fr;}
    .stats-grid{grid-template-columns:1fr 1fr;}
    .topbar{padding:0 14px;}
    .modal{max-width:100%;margin:0;}
    .info-grid{grid-template-columns:1fr;}
}
</style>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-logo">ðŸ¾</div>
        <div class="brand-text">
            <h2>AniPet</h2>
            <p>Admin Portal</p>
        </div>
    </div>

<nav class="sidebar-nav">

    <div class="nav-label">Overview</div>

    <a href="?page=dashboard" class="nav-link <?php echo $page==='dashboard'?'active':''; ?>">
        <span class="nav-icon">ðŸ“Š</span>
        Dashboard
    </a>

    <?php if ($isSuperAdmin): ?>
<a href="super_admin_dashboard.php" class="nav-link">
    <span class="nav-icon">ðŸ‘‘</span>
    Owner Panel
</a>
<?php endif; ?>

    <div class="nav-label" style="margin-top:8px;">Operations</div>

    <a href="?page=pets" class="nav-link <?php echo $page==='pets'?'active':''; ?>">
        <span class="nav-icon">ðŸ¾</span>
        Pet Management
    </a>

    <a href="?page=applications" class="nav-link <?php echo $page==='applications'?'active':''; ?>">
        <span class="nav-icon">ðŸ“‹</span>
        Applications
        <?php if ($pendingApps > 0): ?>
            <span class="nav-badge"><?php echo $pendingApps; ?></span>
        <?php endif; ?>
    </a>

    <a href="?page=appointments" class="nav-link <?php echo $page==='appointments'?'active':''; ?>">
        <span class="nav-icon">ðŸ“…</span>
        Appointments
        <?php if ($pendingApts > 0): ?>
            <span class="nav-badge"><?php echo $pendingApts; ?></span>
        <?php endif; ?>
    </a>

    <a href="?page=pet_pound" class="nav-link <?php echo $page==='pet_pound'?'active':''; ?>">
        <span class="nav-icon">ðŸ </span>
        Pet Pound
    </a>

    <!-- NEW -->
    <a href="?page=penalty_payments" class="nav-link <?php echo $page==='penalty_payments'?'active':''; ?>">
        <span class="nav-icon">ðŸ’³</span>
        Penalty Payments
    </a>

    <div class="nav-label" style="margin-top:8px;">Management</div>

    <a href="?page=users" class="nav-link <?php echo $page==='users'?'active':''; ?>">
        <span class="nav-icon">ðŸ‘¥</span>
        Users
    </a>

    <a href="?page=notifications" class="nav-link <?php echo $page==='notifications'?'active':''; ?>">
        <span class="nav-icon">ðŸ””</span>
        Notifications
    </a>

    <div class="nav-label" style="margin-top:8px;">Analytics</div>

    <a href="?page=reports" class="nav-link <?php echo $page==='reports'?'active':''; ?>">
        <span class="nav-icon">ðŸ“ˆ</span>
        Reports
    </a>

    <div class="nav-label" style="margin-top:8px;">Configuration</div>

    <a href="?page=settings" class="nav-link <?php echo $page==='settings'?'active':''; ?>">
        <span class="nav-icon">âš™ï¸</span>
        Settings
    </a>

</nav>

    <div class="sidebar-footer">
        <div class="admin-profile">
            <div class="admin-avatar"><?php echo strtoupper(substr($adminName,0,1)); ?></div>
            <div class="admin-info">
                <div class="admin-name"><?php echo htmlspecialchars($adminName); ?></div>
                <div class="admin-role">Administrator</div>
            </div>
            <a href="logout.php" class="logout-link" title="Logout">ðŸšª</a>
        </div>
    </div>
</aside>

<div class="main">
    <header class="topbar">
        <button class="hamburger" onclick="openSidebar()">â˜°</button>
        <div class="page-breadcrumb">
            <h1><?php echo htmlspecialchars($pi['icon'].' '.$pi['title']); ?></h1>
            <small><?php echo htmlspecialchars($pi['sub']); ?></small>
        </div>
        <div class="topbar-right">

    <?php if ($isSuperAdmin): ?>
        <a href="super_admin_dashboard.php" class="btn btn-ghost btn-sm">
            â† Back to Owner Panel
        </a>
    <?php endif; ?>

    <span class="topbar-date">
        <?php echo date('F j, Y'); ?>
    </span>

    <button
        class="notif-btn"
        onclick="location.href='?page=applications'"
        title="Pending alerts"
    >
        ðŸ””

        <?php if ($notifBadge > 0): ?>
            <span class="notif-count">
                <?php echo $notifBadge; ?>
            </span>
        <?php endif; ?>
    </button>

</div>
    </header>

    <div class="content">
    <?php
    $pageMap = [
    'dashboard'         => 'admin_pages/dashboard.php',
    'pets'              => 'admin_pages/pets.php',
    'applications'      => 'admin_pages/applications.php',
    'appointments'      => 'admin_pages/appointments.php',
    'users'             => 'admin_pages/users.php',
    'notifications'     => 'admin_pages/notifications.php',
    'reports'           => 'admin_pages/reports.php',
    'pet_pound'         => 'admin_pages/pet_pound.php',

    // NEW PAGE
    'penalty_payments'  => 'admin_pages/payment_history.php',

    'settings'          => 'admin_pages/settings.php',
];
    include $pageMap[$page];
    ?>
    </div>
</div>

<div id="toast-container"></div>

<script>
function openSidebar(){
    document.getElementById('sidebar').classList.add('open');
    document.getElementById('sidebarOverlay').classList.add('open');
}
function closeSidebar(){
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('open');
}
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }

document.addEventListener('click', e => {
    if (e.target.classList.contains('modal-backdrop')) e.target.classList.remove('open');
});

function showToast(msg, type='success'){
    const c = document.getElementById('toast-container');
    const t = document.createElement('div');
    t.className = 'toast toast-'+type;
    t.textContent = msg;
    c.appendChild(t);
    setTimeout(()=>t.remove(), 3500);
}

function switchTab(group, tab){
    document.querySelectorAll('.tabs[data-tg="'+group+'"] .tab-btn').forEach(b=>{
        b.classList.toggle('active', b.dataset.tab===tab);
    });
    document.querySelectorAll('.tab-pane[data-tg="'+group+'"]').forEach(p=>{
        p.classList.toggle('active', p.dataset.tab===tab);
    });
}

function filterTable(inputId, tableId){
    const q = document.getElementById(inputId).value.toLowerCase();
    document.querySelectorAll('#'+tableId+' tbody tr').forEach(row=>{
        row.style.display = row.textContent.toLowerCase().includes(q)?'':'none';
    });
}
</script>
</body>
</html>
<?php $conn->close(); ?>

