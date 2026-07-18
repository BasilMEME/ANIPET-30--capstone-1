<?php
require_permission($conn, 'manage_pets');

// ── Stat counts ──────────────────────────────────────────────────────
$totalPets          = (int)($conn->query("SELECT COUNT(*) FROM pets")->fetch_row()[0] ?? 0);
$availablePets      = (int)($conn->query("SELECT COUNT(*) FROM pets WHERE status='available'")->fetch_row()[0] ?? 0);
$adoptedPets        = (int)($conn->query("SELECT COUNT(*) FROM pets WHERE status='adopted'")->fetch_row()[0] ?? 0);
$underTreatment     = (int)($conn->query("SELECT COUNT(*) FROM pets WHERE status='under_treatment'")->fetch_row()[0] ?? 0);
$totalApplications  = (int)($conn->query("SELECT COUNT(*) FROM adoption_applications")->fetch_row()[0] ?? 0);
$pendingApplications= (int)($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status='pending'")->fetch_row()[0] ?? 0);
$totalSuccessful    = (int)($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status IN('approved','for_releasing','ready_pickup','completed')")->fetch_row()[0] ?? 0);
$pendingAppointments= (int)($conn->query("SELECT COUNT(*) FROM appointments WHERE status='pending'")->fetch_row()[0] ?? 0);
$totalAppointments  = (int)($conn->query("SELECT COUNT(*) FROM appointments")->fetch_row()[0] ?? 0);
$totalUsers         = (int)($conn->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetch_row()[0] ?? 0);
$conn->query("UPDATE pet_pound SET status='Expired' WHERE status='Pending' AND claim_deadline < NOW()");
$inPetPound         = (int)($conn->query("SELECT COUNT(*) FROM pet_pound WHERE status NOT IN ('Deceased','Posted')")->fetch_row()[0] ?? 0);
$deceasedPets       = (int)($conn->query("SELECT COUNT(*) FROM pet_pound WHERE status='Deceased'")->fetch_row()[0] ?? 0);

// ── Chart: monthly adoptions (last 6 months) ─────────────────────────
$monthLabels = [];
$monthMap    = [];
for ($i = 5; $i >= 0; $i--) {
    $label          = date('M Y', strtotime("-{$i} months"));
    $key            = date('Y-m', strtotime("-{$i} months"));
    $monthLabels[]  = $label;
    $monthMap[$key] = 0;
}
$result = $conn->query(
    "SELECT DATE_FORMAT(created_at,'%Y-%m') as ym, COUNT(*) as cnt
     FROM adoption_applications
     WHERE status IN('approved','for_releasing','ready_pickup','completed') AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY ym"
);
if ($result) while ($r = $result->fetch_assoc()) {
    if (isset($monthMap[$r['ym']])) $monthMap[$r['ym']] = (int)$r['cnt'];
}
$monthlyData = array_values($monthMap);

// ── Chart: application status distribution ────────────────────────────
$appStatusLabels = [];
$appStatusData   = [];
$appStatusColors = [];
$colorMap = [
    'pending'              => '#f59e0b',
    'screening'            => '#3b82f6',
    'approved'             => '#10b981',
    'for_releasing'        => '#8b5cf6',
    'ready_pickup'         => '#d97706',
    'completed'            => '#14b8a6',
    'rejected'             => '#ef4444',
];
$result = $conn->query("SELECT status, COUNT(*) as cnt FROM adoption_applications GROUP BY status ORDER BY cnt DESC");
if ($result) while ($r = $result->fetch_assoc()) {
    $label = ucwords(str_replace('_',' ', $r['status']));
    $appStatusLabels[] = $label;
    $appStatusData[]   = (int)$r['cnt'];
    $appStatusColors[] = $colorMap[$r['status']] ?? '#94a3b8';
}

// ── Chart: pet status ─────────────────────────────────────────────────
$petStatusLabels = [];
$petStatusData   = [];
$petStatusChartColors = [];
$petStatusColors = ['available'=>'#22c55e','reserved'=>'#f59e0b','in_adoption'=>'#6366f1','adopted'=>'#14b8a6','under_treatment'=>'#ef4444'];
$result = $conn->query("SELECT status, COUNT(*) as cnt FROM pets WHERE is_archived = 0 GROUP BY status ORDER BY cnt DESC");
if ($result) while ($r = $result->fetch_assoc()) {
    $petStatusLabels[] = ucwords(str_replace('_',' ',$r['status']));
    $petStatusData[]   = (int)$r['cnt'];
    $petStatusChartColors[] = $petStatusColors[$r['status']] ?? '#94a3b8';
}

// ── Recent activities (last 10) ───────────────────────────────────────
$activities = [];
$result = $conn->query(
    "(SELECT 'application' as type, aa.id, aa.status, aa.created_at as dt,
            CONCAT(aa.applicant_name,' applied for ',IFNULL(p.name,'a pet')) as detail
      FROM adoption_applications aa LEFT JOIN pets p ON aa.pet_id=p.id
      ORDER BY aa.created_at DESC LIMIT 5)
     UNION ALL
     (SELECT 'appointment' as type, a.id, a.status, a.created_at as dt,
             CONCAT(IFNULL(u.full_name,'Someone'),' booked: ',a.title) as detail
      FROM appointments a LEFT JOIN users u ON a.user_id=u.id
      ORDER BY a.created_at DESC LIMIT 5)
     ORDER BY dt DESC LIMIT 10"
);
if ($result) while ($r = $result->fetch_assoc()) $activities[] = $r;

// ── Recent applications (for table) ──────────────────────────────────
$recentApps = [];
$result = $conn->query(
    "SELECT aa.id, aa.applicant_name, aa.status, aa.created_at, p.name AS pet_name
     FROM adoption_applications aa LEFT JOIN pets p ON aa.pet_id=p.id
     ORDER BY aa.created_at DESC LIMIT 8"
);
if ($result) while ($r = $result->fetch_assoc()) $recentApps[] = $r;

// ── Upcoming appointments ─────────────────────────────────────────────
$upcomingApts = [];
$result = $conn->query(
    "SELECT a.id, a.title, a.scheduled_at, a.status, u.full_name
     FROM appointments a LEFT JOIN users u ON a.user_id=u.id
     WHERE a.scheduled_at >= NOW()
     ORDER BY a.scheduled_at ASC LIMIT 5"
);
if ($result) while ($r = $result->fetch_assoc()) $upcomingApts[] = $r;
?>

<!-- ══ STAT CARDS ══════════════════════════════════════════════════════ -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">🐾</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $totalPets; ?></div>
            <div class="stat-label">Total Pets</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dcfce7;">✅</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $availablePets; ?></div>
            <div class="stat-label">Available</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5;">🏠</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $adoptedPets; ?></div>
            <div class="stat-label">Adopted</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">💊</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $underTreatment; ?></div>
            <div class="stat-label">Under Treatment</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;">📋</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $totalApplications; ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#ffedd5;">⏳</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $pendingApplications; ?></div>
            <div class="stat-label">Pending Review</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0e7ff;">📅</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $pendingAppointments; ?></div>
            <div class="stat-label">Pending Appointments</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f0fdf4;">👥</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $totalUsers; ?></div>
            <div class="stat-label">Registered Users</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fee2e2;">🏠</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $inPetPound; ?></div>
            <div class="stat-label">In Pet Pound</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#f1f5f9;">☠</div>
        <div class="stat-body">
            <div class="stat-value"><?php echo $deceasedPets; ?></div>
            <div class="stat-label">Deceased Records</div>
        </div>
    </div>
</div>

<!-- ══ CHARTS ROW ═════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:18px;margin-bottom:22px;">
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div>
                <div class="card-title">Monthly Successful Adoptions</div>
                <div class="card-sub">Last 6 months — approved &amp; adopted</div>
            </div>
        </div>
        <canvas id="chartAdoptions" height="100"></canvas>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div>
                <div class="card-title">Application Status</div>
                <div class="card-sub">Current breakdown</div>
            </div>
        </div>
        <canvas id="chartAppStatus" height="180"></canvas>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:22px;">
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-title">Pet Inventory by Status</div>
        </div>
        <canvas id="chartPetStatus" height="130"></canvas>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-title">Upcoming Appointments</div>
        </div>
        <?php if (empty($upcomingApts)): ?>
        <div class="empty-state"><div class="empty-icon">📅</div><p>No upcoming appointments</p></div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Title</th><th>Scheduled</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($upcomingApts as $a): ?>
            <tr>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td style="font-size:.8rem;"><?php echo date('M d, Y H:i', strtotime($a['scheduled_at'])); ?></td>
                <td><span class="badge badge-<?php echo $a['status']; ?>"><?php echo ucfirst($a['status']); ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ══ RECENT ACTIVITY + APPLICATIONS ════════════════════════════════ -->
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:18px;">
    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-title">Recent Activity</div>
        </div>
        <?php if (empty($activities)): ?>
        <div class="empty-state"><div class="empty-icon">📭</div><p>No recent activity</p></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0;">
        <?php foreach ($activities as $act):
            $icon = $act['type']==='application' ? '📋' : '📅';
            $badgeClass = 'badge-'.strtolower($act['status']);
        ?>
        <div style="display:flex;gap:10px;align-items:flex-start;padding:10px 0;border-bottom:1px solid var(--border);">
            <span style="font-size:1.1rem;margin-top:2px;"><?php echo $icon; ?></span>
            <div style="flex:1;min-width:0;">
                <div style="font-size:.82rem;color:var(--text);line-height:1.4;"><?php echo htmlspecialchars($act['detail']); ?></div>
                <div style="display:flex;align-items:center;gap:8px;margin-top:4px;">
                    <span class="badge <?php echo $badgeClass; ?>"><?php echo ucwords(str_replace('_',' ',$act['status'])); ?></span>
                    <span style="font-size:.73rem;color:var(--muted);"><?php echo date('M d, g:i a', strtotime($act['dt'])); ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card" style="margin-bottom:0;">
        <div class="card-header">
            <div class="card-title">Recent Applications</div>
            <a href="?page=applications" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <?php if (empty($recentApps)): ?>
        <div class="empty-state"><div class="empty-icon">📋</div><p>No applications yet</p></div>
        <?php else: ?>
        <div class="table-wrap">
        <table>
            <thead><tr><th>#</th><th>Applicant</th><th>Pet</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($recentApps as $app): ?>
            <tr>
                <td style="color:var(--muted);font-size:.8rem;"><?php echo $app['id']; ?></td>
                <td style="font-weight:600;"><?php echo htmlspecialchars($app['applicant_name']); ?></td>
                <td><?php echo htmlspecialchars($app['pet_name'] ?? '—'); ?></td>
                <td><span class="badge badge-<?php echo strtolower($app['status']); ?>"><?php echo ucwords(str_replace('_',' ',$app['status'])); ?></span></td>
                <td style="font-size:.8rem;color:var(--muted);"><?php echo date('M d', strtotime($app['created_at'])); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'Segoe UI', system-ui, sans-serif";
Chart.defaults.color = '#64748b';

// Monthly Adoptions
new Chart(document.getElementById('chartAdoptions'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($monthLabels); ?>,
        datasets:[{
            label: 'Adoptions',
            data: <?php echo json_encode($monthlyData); ?>,
            backgroundColor: 'rgba(242,134,126,.2)',
            borderColor: '#f2867e',
            borderWidth: 2,
            borderRadius: 6,
        }]
    },
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(0,0,0,.05)'}},x:{grid:{display:false}}}}
});

// Application Status Doughnut
new Chart(document.getElementById('chartAppStatus'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($appStatusLabels); ?>,
        datasets:[{
            data: <?php echo json_encode($appStatusData); ?>,
            backgroundColor: <?php echo json_encode($appStatusColors); ?>,
            borderWidth: 2, borderColor: '#fff'
        }]
    },
    options:{responsive:true,cutout:'65%',plugins:{legend:{position:'bottom',labels:{padding:10,font:{size:11}}}}}
});

// Pet Status Bar
new Chart(document.getElementById('chartPetStatus'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($petStatusLabels); ?>,
        datasets:[{
            data: <?php echo json_encode($petStatusData); ?>,
            backgroundColor: <?php echo json_encode($petStatusChartColors); ?>,
            borderRadius: 6,
        }]
    },
    options:{responsive:true,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0},grid:{color:'rgba(0,0,0,.05)'}},y:{grid:{display:false}}}}
});
</script>
