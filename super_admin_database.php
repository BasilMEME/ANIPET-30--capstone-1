<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('backup_restore_database');

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

function formatSizeMB($bytes) {
    return round($bytes / 1024 / 1024, 2);
}

$tables = ['users', 'pets', 'adoption_applications', 'return_requests', 'adoption_records', 'appointments', 'shelters', 'system_settings', 'audit_logs'];
$dbHealth = [];
$totalRows = 0;
$totalSize = 0;
$totalIndex = 0;
$totalFree = 0;

$tableList = implode("','", array_map(function ($t) { return $t; }, $tables));
$tableStatus = $conn->query("SHOW TABLE STATUS WHERE `Name` IN ('{$tableList}')");
if ($tableStatus) {
    while ($row = $tableStatus->fetch_assoc()) {
        $size = (int)$row['Data_length'];
        $index = (int)$row['Index_length'];
        $free = (int)$row['Data_free'];
        $dbHealth[] = [
            'table' => $row['Name'],
            'engine' => $row['Engine'],
            'rows' => (int)$row['Rows'],
            'sizeMB' => formatSizeMB($size),
            'indexMB' => formatSizeMB($index),
            'freeMB' => formatSizeMB($free),
            'updatedAt' => $row['Update_time'] ?: $row['Create_time'],
        ];
        $totalRows += (int)$row['Rows'];
        $totalSize += $size;
        $totalIndex += $index;
        $totalFree += $free;
    }
}

$connectionMetrics = [];
$statusVars = ['Threads_connected','Threads_running','Max_used_connections','Aborted_connects','Connections','Uptime'];
$statusResult = $conn->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('" . implode("','", $statusVars) . "')");
if ($statusResult) {
    while ($row = $statusResult->fetch_assoc()) {
        $connectionMetrics[$row['Variable_name']] = $row['Value'];
    }
}
$maxConnections = 0;
$varResult = $conn->query("SHOW VARIABLES LIKE 'max_connections'");
if ($varResult && $row = $varResult->fetch_assoc()) {
    $maxConnections = intval($row['Value']);
}

$activeQueries = [];
$processResult = $conn->query("SELECT ID, USER, HOST, DB, COMMAND, TIME, STATE, INFO FROM INFORMATION_SCHEMA.PROCESSLIST WHERE COMMAND != 'Sleep' ORDER BY TIME DESC LIMIT 12");
if ($processResult) {
    while ($row = $processResult->fetch_assoc()) {
        $activeQueries[] = $row;
    }
}

$slowQueries = [];
$perfCheck = $conn->query("SELECT COUNT(*) AS total FROM information_schema.tables WHERE table_schema = 'performance_schema' AND table_name = 'events_statements_summary_by_digest'");
if ($perfCheck && ($row = $perfCheck->fetch_assoc()) && intval($row['total']) > 0) {
    $slowResult = $conn->query("SELECT digest_text, COUNT_STAR, SUM_TIMER_WAIT, AVG_TIMER_WAIT, MIN_TIMER_WAIT, MAX_TIMER_WAIT FROM performance_schema.events_statements_summary_by_digest ORDER BY SUM_TIMER_WAIT DESC LIMIT 5");
    if ($slowResult) {
        while ($row = $slowResult->fetch_assoc()) {
            $slowQueries[] = [
                'query' => $row['digest_text'],
                'count' => intval($row['COUNT_STAR']),
                'totalMs' => round(intval($row['SUM_TIMER_WAIT']) / 1000000000, 2),
                'avgMs' => round(intval($row['AVG_TIMER_WAIT']) / 1000000000, 2),
                'maxMs' => round(intval($row['MAX_TIMER_WAIT']) / 1000000000, 2),
            ];
        }
    }
}

$replicaStatus = null;

try {
    $replicaResult = $conn->query("SHOW REPLICA STATUS");

    if ($replicaResult && $replicaResult->num_rows > 0) {
        $replicaStatus = $replicaResult->fetch_assoc();
    }
} catch (mysqli_sql_exception $e) {
    error_log('Replica status check failed: ' . $e->getMessage());
}

if ($replicaStatus) {
    $replicationStatus = [
        'role' => 'replica',
        'status' => ($replicaStatus['Replica_IO_Running'] ?? 'unknown')
            . ' / '
            . ($replicaStatus['Replica_SQL_Running'] ?? 'unknown'),
        'seconds_behind' => $replicaStatus['Seconds_Behind_Source'] ?? 'N/A',
        'source_host' => $replicaStatus['Source_Host'] ?? 'N/A',
    ];
} else {
    $masterStatusResult = $conn->query("SHOW MASTER STATUS");

    if ($masterStatusResult && $masterStatusResult->num_rows > 0) {
        $masterStatus = $masterStatusResult->fetch_assoc();

        $replicationStatus = [
            'role' => 'master',
            'file' => $masterStatus['File'] ?? 'N/A',
            'position' => $masterStatus['Position'] ?? 'N/A',
        ];
    } else {
        $replicationStatus = null;
    }
}

$backupDir = __DIR__ . '/backups';
$backups = [];
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
    <title>AniPet Database Monitoring</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
        body { margin: 0; font-family: 'Inter', sans-serif; color: var(--text); min-height: 100vh; background: radial-gradient(circle at top left, rgba(242,134,126,.14), transparent 25%), radial-gradient(circle at bottom right, rgba(246,201,160,.12), transparent 24%), linear-gradient(135deg, #020617 0%, #07111f 100%); }
        .wrapper { max-width: 1320px; margin: 0 auto; padding: 24px 24px 40px; }
        .header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; padding:18px 20px; background:rgba(15,23,42,.72); border:1px solid var(--border); border-radius:24px; box-shadow:var(--shadow); backdrop-filter:blur(14px); }
        .header h1 { margin: 0; font-size:clamp(1.4rem,2vw,2rem); }
        .header p { margin: 0; color: var(--muted); line-height:1.6; }
        .button { color: var(--text); background: rgba(255,255,255,.08); border: 1px solid rgba(148,163,184,.16); border-radius: 14px; padding: 12px 18px; text-decoration: none; display: inline-flex; align-items: center; transition: transform .18s ease, background .18s ease, box-shadow .18s ease; }
        .button:hover { transform:translateY(-1px); background: rgba(255,255,255,.12); box-shadow:0 8px 20px rgba(2,8,23,.22); }
        .grid { display: grid; grid-template-columns: repeat(3,minmax(0,1fr)); gap: 18px; margin-bottom: 24px; }
        .card { background: var(--panel); border: 1px solid var(--border); border-radius: 24px; padding: 22px; box-shadow: var(--shadow); backdrop-filter:blur(12px); transition:transform .18s ease,border-color .18s ease; }
        .card:hover { transform:translateY(-1px); border-color:rgba(242,134,126,.24); }
        .card h2 { margin: 0 0 14px; font-size: 1.05rem; color: #cbd5e1; }
        .metric { font-size: 2.2rem; font-weight: 700; margin-bottom: 8px; }
        .metric-label { color: var(--muted); line-height:1.6; }
        .table-wrap { overflow-x:auto; border:1px solid rgba(148,163,184,.12); border-radius:18px; background:rgba(255,255,255,.025); }
        table { width: 100%; border-collapse: collapse; color: var(--text); table-layout:fixed; min-width:720px; }
        th, td { padding: 12px 14px; border-bottom: 1px solid rgba(148,163,184,.12); text-align: left; overflow-wrap:anywhere; word-break:break-word; }
        th { color: var(--muted); font-size: .82rem; text-transform: uppercase; letter-spacing:.04em; }
        tr:hover { background: rgba(242,134,126,.08); }
        .pill { display: inline-flex; align-items: center; padding: 6px 10px; border-radius: 999px; font-size: .82rem; background: rgba(148,163,184,.12); color: #e2e8f0; }
        .pill.engine { color: #38bdf8; }
        .action-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
        .note { color: var(--muted); font-size: .93rem; line-height: 1.6; }
        @media (max-width: 1100px) { .grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 760px) { .wrapper{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} .grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <div>
            <h1>Database Monitoring</h1>
            <p>Dedicated database health and backup console for AniPet system administration.</p>
        </div>
        <div class="action-row">
            <a class="button" href="super_admin_dashboard.php">Dashboard</a>
            <a class="button" href="super_admin_settings.php">Settings</a>
            <a class="button" href="super_admin_security.php">Security</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h2>Total Tables</h2>
            <div class="metric"><?php echo count($dbHealth); ?></div>
            <div class="metric-label">Monitored system tables</div>
        </div>
        <div class="card">
            <h2>Total Rows</h2>
            <div class="metric"><?php echo number_format($totalRows); ?></div>
            <div class="metric-label">Rows across all core tables</div>
        </div>
        <div class="card">
            <h2>Data + Index Size</h2>
            <div class="metric"><?php echo number_format(formatSizeMB($totalSize + $totalIndex), 2); ?> MB</div>
            <div class="metric-label">Includes table and index storage</div>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h2>Connection & Query Performance</h2>
        <div class="grid">
            <div class="card" style="padding:18px;">
                <h3>Connected</h3>
                <div class="metric"><?php echo number_format(intval($connectionMetrics['Threads_connected'] ?? 0)); ?></div>
                <div class="metric-label">Open client connections</div>
            </div>
            <div class="card" style="padding:18px;">
                <h3>Running</h3>
                <div class="metric"><?php echo number_format(intval($connectionMetrics['Threads_running'] ?? 0)); ?></div>
                <div class="metric-label">Active executing queries</div>
            </div>
            <div class="card" style="padding:18px;">
                <h3>Peak Used</h3>
                <div class="metric"><?php echo number_format(intval($connectionMetrics['Max_used_connections'] ?? 0)); ?></div>
                <div class="metric-label">Peak concurrent connections</div>
            </div>
        </div>
        <div class="grid" style="margin-top:16px;">
            <div class="card" style="padding:18px;">
                <h3>Max Allowed</h3>
                <div class="metric"><?php echo number_format($maxConnections); ?></div>
                <div class="metric-label">Configured max_connections</div>
            </div>
            <div class="card" style="padding:18px;">
                <h3>Uptime</h3>
                <div class="metric"><?php echo number_format(intval($connectionMetrics['Uptime'] ?? 0)); ?>s</div>
                <div class="metric-label">Server runtime</div>
            </div>
            <div class="card" style="padding:18px;">
                <h3>Aborted</h3>
                <div class="metric"><?php echo number_format(intval($connectionMetrics['Aborted_connects'] ?? 0)); ?></div>
                <div class="metric-label">Connection failures</div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h2>Active Queries</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>ID</th><th>User</th><th>Host</th><th>DB</th><th>State</th><th>Time</th><th>Info</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($activeQueries)): ?>
                    <?php foreach ($activeQueries as $query): ?>
                        <tr>
                            <td><?php echo intval($query['ID']); ?></td>
                            <td><?php echo htmlspecialchars($query['USER']); ?></td>
                            <td><?php echo htmlspecialchars($query['HOST']); ?></td>
                            <td><?php echo htmlspecialchars($query['DB']); ?></td>
                            <td><?php echo htmlspecialchars($query['STATE'] ?? ''); ?></td>
                            <td><?php echo intval($query['TIME']); ?></td>
                            <td><?php echo htmlspecialchars(strlen($query['INFO']) > 100 ? substr($query['INFO'], 0, 100) . '…' : $query['INFO']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No active queries found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h2>Slow Query Analytics</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Query Digest</th><th>Executions</th><th>Total ms</th><th>Avg ms</th><th>Max ms</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($slowQueries)): ?>
                    <?php foreach ($slowQueries as $slow): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($slow['query']); ?></td>
                            <td><?php echo number_format($slow['count']); ?></td>
                            <td><?php echo number_format($slow['totalMs'], 2); ?></td>
                            <td><?php echo number_format($slow['avgMs'], 2); ?></td>
                            <td><?php echo number_format($slow['maxMs'], 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5">Slow query digest data unavailable. Ensure performance_schema is enabled.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h2>Replication Status</h2>
        <div class="note">
            <?php if (!empty($replicationStatus)): ?>
                <?php if ($replicationStatus['role'] === 'replica'): ?>
                    Source host: <?php echo htmlspecialchars($replicationStatus['source_host']); ?><br>
                    Replica status: <?php echo htmlspecialchars($replicationStatus['status']); ?><br>
                    Seconds behind source: <?php echo htmlspecialchars($replicationStatus['seconds_behind']); ?>
                <?php else: ?>
                    Role: Master<br>
                    File: <?php echo htmlspecialchars($replicationStatus['file']); ?><br>
                    Position: <?php echo htmlspecialchars($replicationStatus['position']); ?>
                <?php endif; ?>
            <?php else: ?>
                Replication is not configured or status cannot be determined.
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h2>Backup & Restore Actions</h2>
        <p class="note">Create database backups, export schema/data, or restore a previous backup file. Backup files are stored in <code>php-backend/backups/</code>.</p>
        <div class="action-row">
            <button class="button" id="backupBtn">Create Backup</button>
            <button class="button" id="exportBtn">Export Database</button>
        </div>
    </div>

    <div class="card" style="margin-bottom:24px;">
        <h2>Recent Backups</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>File</th><th>Download</th><th>Restore</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($backups)): ?>
                    <?php foreach ($backups as $backup): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($backup['file']); ?></td>
                            <td><a class="button" href="backups/<?php echo htmlspecialchars($backup['file']); ?>" target="_blank">Download</a></td>
                            <td><button class="button restore-btn" data-file="<?php echo htmlspecialchars($backup['file']); ?>">Restore</button></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3">No backup files found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Table Health & Storage</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Table</th><th>Engine</th><th>Rows</th><th>Data MB</th><th>Index MB</th><th>Free MB</th><th>Updated</th></tr>
                </thead>
                <tbody>
                <?php if (!empty($dbHealth)): ?>
                    <?php foreach ($dbHealth as $table): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($table['table']); ?></td>
                            <td><span class="pill engine"><?php echo htmlspecialchars($table['engine']); ?></span></td>
                            <td><?php echo number_format($table['rows']); ?></td>
                            <td><?php echo number_format($table['sizeMB'], 2); ?></td>
                            <td><?php echo number_format($table['indexMB'], 2); ?></td>
                            <td><?php echo number_format($table['freeMB'], 2); ?></td>
                            <td><?php echo htmlspecialchars($table['updatedAt']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7">No database metadata available.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
    const apiEndpoint = 'super_admin_api.php';
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
    document.querySelectorAll('.restore-btn').forEach(button => {
        button.addEventListener('click', () => {
            const file = button.dataset.file;
            if (!file) return;
            if (!confirm('Restore backup "' + file + '"? This will overwrite current database tables.')) return;
            const formData = new FormData();
            formData.append('action', 'restore_database');
            formData.append('file', file);
            fetch(apiEndpoint, { method: 'POST', body: formData })
                .then(res => res.json())
                .then(data => alert(data.message || 'Restore completed.'))
                .catch(() => alert('Restore failed.'));
        });
    });
</script>
</body>
</html>
