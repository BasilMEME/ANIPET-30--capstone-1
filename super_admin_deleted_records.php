<?php
require_once __DIR__ . '/auth_helper.php';
require_super_admin();

if (function_exists('columnExists')) {
    if (!columnExists($conn, 'users', 'deleted_at')) {
        $conn->query("ALTER TABLE users ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER is_deleted");
    }
    if (!columnExists($conn, 'users', 'deleted_by')) {
        $conn->query("ALTER TABLE users ADD COLUMN deleted_by INT DEFAULT NULL AFTER deleted_at");
    }
    if (!columnExists($conn, 'pets', 'is_deleted')) {
        $conn->query("ALTER TABLE pets ADD COLUMN is_deleted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_archived");
    }
    if (!columnExists($conn, 'pets', 'deleted_at')) {
        $conn->query("ALTER TABLE pets ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER is_deleted");
    }
    if (!columnExists($conn, 'pets', 'deleted_by')) {
        $conn->query("ALTER TABLE pets ADD COLUMN deleted_by INT DEFAULT NULL AFTER deleted_at");
    }
}

function deletedRows(mysqli $conn, string $sql): array
{
    $rows = [];
    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    }

    return $rows;
}

$deletedAdmins = deletedRows(
    $conn,
    "SELECT
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.role,
        u.deleted_at,
        d.full_name AS deleted_by_name,
        d.username AS deleted_by_username
     FROM users u
     LEFT JOIN users d ON d.id = u.deleted_by
     WHERE u.is_deleted = 1
       AND u.role IN ('admin', 'super_admin', 'super')
     ORDER BY u.deleted_at DESC, u.id DESC"
);

$deletedUsers = deletedRows(
    $conn,
    "SELECT
        u.id,
        u.username,
        u.full_name,
        u.email,
        u.role,
        u.deleted_at,
        d.full_name AS deleted_by_name,
        d.username AS deleted_by_username
     FROM users u
     LEFT JOIN users d ON d.id = u.deleted_by
     WHERE u.is_deleted = 1
       AND u.role NOT IN ('admin', 'super_admin', 'super')
     ORDER BY u.deleted_at DESC, u.id DESC"
);

$deletedPets = deletedRows(
    $conn,
    "SELECT
        p.id,
        p.name,
        p.breed,
        p.age,
        p.gender,
        p.status,
        p.health_status,
        p.deleted_at,
        d.full_name AS deleted_by_name,
        d.username AS deleted_by_username
     FROM pets p
     LEFT JOIN users d ON d.id = p.deleted_by
     WHERE p.is_deleted = 1
     ORDER BY p.deleted_at DESC, p.id DESC"
);

function deletedByLabel(array $row): string
{
    $name = trim((string) ($row['deleted_by_name'] ?? ''));
    $username = trim((string) ($row['deleted_by_username'] ?? ''));

    if ($name !== '') {
        return $name;
    }

    if ($username !== '') {
        return $username;
    }

    return 'Unknown';
}

function deletedDateLabel(?string $value): string
{
    if (!$value) {
        return 'Not recorded';
    }

    $timestamp = strtotime($value);

    return $timestamp
        ? date('M d, Y h:i A', $timestamp)
        : $value;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Deleted Records</title>
    <link rel="stylesheet" href="/anipet_reference_theme.css?v=20260811">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{
            --bg:#f4ead9;
            --panel:rgba(255,250,241,.95);
            --surface:rgba(255,252,246,.96);
            --text:#3b2417;
            --muted:#916b50;
            --accent:#986038;
            --accent-dark:#754325;
            --border:#d9b996;
            --shadow:0 10px 28px rgba(82,48,27,.10);
        }

        *{box-sizing:border-box}
        html{scroll-behavior:smooth}
        body{
            margin:0;
            min-height:100vh;
            font-family:'Inter',sans-serif;
            color:var(--text);
            background:
                linear-gradient(rgba(244,234,217,.90),rgba(244,234,217,.90)),
                url('/anipet_admin_wallpaper.png') center/cover fixed no-repeat;
        }

        .container{
            max-width:1480px;
            margin:0 auto;
            padding:24px 26px 44px;
        }

        .header{
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:18px;
            padding:22px 24px;
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:22px;
            box-shadow:var(--shadow);
            margin-bottom:16px;
        }

        .header h1{margin:0 0 6px;font-size:clamp(1.6rem,2.4vw,2.25rem)}
        .header p{margin:0;color:var(--muted)}

        .stats{
            display:grid;
            grid-template-columns:repeat(3,minmax(0,1fr));
            gap:14px;
            margin-bottom:16px;
        }

        .stat{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:18px;
            padding:16px 18px;
            box-shadow:var(--shadow);
        }

        .stat strong{display:block;font-size:1.65rem;color:var(--accent-dark)}
        .stat span{color:var(--muted);font-size:.86rem}

        .quick-nav{
            display:flex;
            flex-wrap:wrap;
            gap:10px;
            margin-bottom:16px;
            padding:12px;
            border:1px solid var(--border);
            background:rgba(255,250,241,.90);
            border-radius:16px;
        }

        .quick-nav a,.btn{
            display:inline-flex;
            align-items:center;
            justify-content:center;
            min-height:40px;
            padding:9px 15px;
            border-radius:11px;
            border:1px solid var(--border);
            text-decoration:none;
            font-weight:750;
            font-size:.83rem;
            cursor:pointer;
        }

        .quick-nav a,.btn-secondary{
            color:var(--text);
            background:#fffaf3;
        }

        .btn-primary{
            color:#fffaf3;
            background:var(--accent);
            border-color:var(--accent);
        }

        .btn-primary:hover{background:var(--accent-dark)}
        .quick-nav a:hover,.btn-secondary:hover{background:#f1dfca}

        .section{
            margin-top:18px;
            scroll-margin-top:20px;
        }

        .card{
            background:var(--panel);
            border:1px solid var(--border);
            border-radius:18px;
            padding:20px 22px;
            box-shadow:var(--shadow);
        }

        .section-title{
            display:flex;
            justify-content:space-between;
            align-items:center;
            gap:12px;
            margin-bottom:12px;
        }

        .section-title h2{margin:0;font-size:1.08rem}
        .count{
            padding:6px 10px;
            border-radius:999px;
            background:#efddc5;
            color:#6f472c;
            font-weight:800;
            font-size:.78rem;
        }

        .search-row{margin-bottom:10px}
        .search-row input{
            width:100%;
            padding:11px 13px;
            border:1.5px solid var(--border);
            border-radius:12px;
            background:var(--surface);
            color:var(--text);
            outline:none;
        }

        .table-wrap{
            overflow:auto;
            border:1px solid var(--border);
            border-radius:15px;
            background:var(--surface);
        }

        table{
            width:100%;
            border-collapse:collapse;
            min-width:850px;
        }

        th,td{
            padding:12px 14px;
            text-align:left;
            border-bottom:1px solid rgba(139,91,54,.16);
            vertical-align:middle;
        }

        th{
            background:#efddc5;
            color:#6f472c;
            font-size:.74rem;
            text-transform:uppercase;
            letter-spacing:.06em;
        }

        td{font-size:.89rem}
        tbody tr:hover td{background:#fbefdf}

        .empty{
            padding:22px;
            text-align:center;
            color:var(--muted);
        }

        @media(max-width:760px){
            .container{padding:14px}
            .header{align-items:flex-start;flex-direction:column}
            .stats{grid-template-columns:1fr}
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Deleted Records</h1>
            <p>Deleted AniPet records are preserved here until a Super Admin restores them.</p>
        </div>
        <div>
            <a class="btn btn-secondary" href="super_admin_actions.php">Back to Operations</a>
            <a class="btn btn-secondary" href="super_admin_dashboard.php">Dashboard</a>
        </div>
    </div>

    <div class="stats">
        <div class="stat"><strong><?php echo count($deletedAdmins); ?></strong><span>Deleted Admins</span></div>
        <div class="stat"><strong><?php echo count($deletedUsers); ?></strong><span>Deleted Users</span></div>
        <div class="stat"><strong><?php echo count($deletedPets); ?></strong><span>Deleted Pets</span></div>
    </div>

    <nav class="quick-nav">
        <a href="#deleted-admins">Admins</a>
        <a href="#deleted-users">Users</a>
        <a href="#deleted-pets">Pets</a>
    </nav>

    <section class="section" id="deleted-admins">
        <div class="card">
            <div class="section-title">
                <h2>Deleted Admin Accounts</h2>
                <span class="count"><?php echo count($deletedAdmins); ?></span>
            </div>
            <div class="search-row">
                <input id="adminSearch" type="search" placeholder="Search deleted admins..." oninput="filterRows('adminSearch','deletedAdminsTable')">
            </div>
            <div class="table-wrap">
                <table id="deletedAdminsTable">
                    <thead>
                    <tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Deleted At</th><th>Deleted By</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$deletedAdmins): ?>
                        <tr><td class="empty" colspan="8">No deleted admin accounts.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deletedAdmins as $row): ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $row['username']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['role']); ?></td>
                                <td><?php echo htmlspecialchars(deletedDateLabel($row['deleted_at'])); ?></td>
                                <td><?php echo htmlspecialchars(deletedByLabel($row)); ?></td>
                                <td><button class="btn btn-primary" onclick="restoreRecord('restore_admin', <?php echo (int) $row['id']; ?>, 'admin')">Restore</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section" id="deleted-users">
        <div class="card">
            <div class="section-title">
                <h2>Deleted User Accounts</h2>
                <span class="count"><?php echo count($deletedUsers); ?></span>
            </div>
            <div class="search-row">
                <input id="userSearch" type="search" placeholder="Search deleted users..." oninput="filterRows('userSearch','deletedUsersTable')">
            </div>
            <div class="table-wrap">
                <table id="deletedUsersTable">
                    <thead>
                    <tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Deleted At</th><th>Deleted By</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$deletedUsers): ?>
                        <tr><td class="empty" colspan="8">No deleted user accounts.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deletedUsers as $row): ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $row['username']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['full_name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['email']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['role']); ?></td>
                                <td><?php echo htmlspecialchars(deletedDateLabel($row['deleted_at'])); ?></td>
                                <td><?php echo htmlspecialchars(deletedByLabel($row)); ?></td>
                                <td><button class="btn btn-primary" onclick="restoreRecord('restore_user', <?php echo (int) $row['id']; ?>, 'user')">Restore</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section" id="deleted-pets">
        <div class="card">
            <div class="section-title">
                <h2>Deleted Pet Records</h2>
                <span class="count"><?php echo count($deletedPets); ?></span>
            </div>
            <div class="search-row">
                <input id="petSearch" type="search" placeholder="Search deleted pets..." oninput="filterRows('petSearch','deletedPetsTable')">
            </div>
            <div class="table-wrap">
                <table id="deletedPetsTable">
                    <thead>
                    <tr><th>ID</th><th>Name</th><th>Breed</th><th>Age</th><th>Gender</th><th>Status</th><th>Deleted At</th><th>Deleted By</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (!$deletedPets): ?>
                        <tr><td class="empty" colspan="9">No deleted pet records.</td></tr>
                    <?php else: ?>
                        <?php foreach ($deletedPets as $row): ?>
                            <tr>
                                <td><?php echo (int) $row['id']; ?></td>
                                <td><?php echo htmlspecialchars((string) $row['name']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['breed']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['age']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['gender']); ?></td>
                                <td><?php echo htmlspecialchars((string) $row['status']); ?></td>
                                <td><?php echo htmlspecialchars(deletedDateLabel($row['deleted_at'])); ?></td>
                                <td><?php echo htmlspecialchars(deletedByLabel($row)); ?></td>
                                <td><button class="btn btn-primary" onclick="restoreRecord('restore_pet', <?php echo (int) $row['id']; ?>, 'pet')">Restore</button></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</div>

<script>
const apiEndpoint = 'super_admin_api.php';

async function restoreRecord(action, id, label) {
    if (!confirm(`Restore this ${label} record?`)) {
        return;
    }

    const formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);

    try {
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        const data = await response.json();

        alert(
            data.message ||
            (data.success ? 'Record restored.' : 'Restore failed.')
        );

        if (data.success) {
            window.location.reload();
        }
    } catch (error) {
        console.error(error);
        alert('Restore failed. Please try again.');
    }
}

function filterRows(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);

    if (!input || !table) {
        return;
    }

    const query = input.value.trim().toLowerCase();

    table.querySelectorAll('tbody tr').forEach(row => {
        row.style.display =
            row.textContent.toLowerCase().includes(query)
                ? ''
                : 'none';
    });
}
</script>
</body>
</html>