<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_admins');

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

$admins = fetchRows($conn, "SELECT id, username, full_name, email, role, is_verified, is_suspended, is_deleted, created_at FROM users WHERE role IN ('admin', 'super_admin', 'super') ORDER BY created_at DESC");
$users = fetchRows($conn, "SELECT id, username, full_name, email, role, is_verified, is_suspended, is_deleted, created_at FROM users WHERE role NOT IN ('admin', 'super_admin', 'super') ORDER BY created_at DESC");
$pets = fetchRows($conn, "SELECT id, name, breed, age, gender, status, health_status, description, is_archived, shelter_id, created_at FROM pets ORDER BY created_at DESC");
$shelters = fetchRows($conn, "SELECT id, name FROM shelters ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="/anipet_reference_theme.css?v=20260811">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin Actions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
:root{
    color-scheme:dark;
    --bg:#07111f;
    --panel:#0f1b2f;
    --panel-soft:#152238;
    --surface:#1b2940;
    --surface-2:#22314a;
    --text:#f8fafc;
    --muted:#9fb0c6;
    --accent:#f2867e;
    --accent-dark:#d9695f;
    --border:rgba(148,163,184,.18);
    --shadow:0 12px 32px rgba(2,8,23,.28);
    --radius:16px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{
    margin:0;
    font-family:'Inter',sans-serif;
    color:var(--text);
    min-height:100vh;
    background:radial-gradient(circle at top left,rgba(242,134,126,.10),transparent 24%),linear-gradient(135deg,#050d18 0%,#081421 45%,#0a1625 100%);
}
.container{max-width:1380px;margin:0 auto;padding:22px 24px 40px}
.header{
    display:flex;align-items:center;justify-content:space-between;gap:18px;
    margin-bottom:14px;padding:18px 20px;background:rgba(15,27,47,.94);
    border:1px solid var(--border);border-radius:20px;box-shadow:var(--shadow);
    position:sticky;top:12px;z-index:30;backdrop-filter:blur(14px);
}
.header h1{margin:0 0 5px;font-size:clamp(1.35rem,2vw,1.9rem)}
.header .note{margin:0}
.quick-nav{
    display:flex;gap:8px;flex-wrap:wrap;margin:0 0 18px;padding:10px;
    border:1px solid var(--border);background:rgba(15,27,47,.78);border-radius:15px;
    position:sticky;top:104px;z-index:25;backdrop-filter:blur(12px);
}
.quick-nav a{
    text-decoration:none;color:var(--muted);padding:9px 12px;border-radius:10px;
    font-weight:700;font-size:.82rem;transition:.18s ease;
}
.quick-nav a:hover{background:rgba(242,134,126,.13);color:#ffd1cc}
.section{margin-top:10px;scroll-margin-top:175px}
.grid,.compact-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;align-items:start}
.card{
    background:rgba(15,27,47,.95);border:1px solid var(--border);border-radius:var(--radius);
    padding:16px 18px;box-shadow:var(--shadow);
}
.card h2{margin:0 0 10px;font-size:1.02rem;color:#e5edf8}
.card-sub{margin:-4px 0 10px;color:var(--muted);font-size:.84rem;line-height:1.45}
.table-wrap{overflow:auto;border:1px solid var(--border);border-radius:14px;background:#101c30}
table{width:100%;border-collapse:collapse;color:var(--text);table-layout:auto;min-width:820px}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid rgba(148,163,184,.12);vertical-align:middle}
th{
    color:#9fb0c6;font-size:.72rem;text-transform:uppercase;letter-spacing:.06em;
    background:#142137;position:sticky;top:0;z-index:2
}
td{font-size:.88rem;color:#e8eef7}
tbody tr:hover{background:rgba(242,134,126,.055)}
tbody tr:last-child td{border-bottom:0}
.btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:38px;
    padding:9px 14px;border-radius:10px;border:1px solid transparent;cursor:pointer;
    font-weight:750;font-size:.82rem;transition:all .18s ease;text-decoration:none;white-space:nowrap
}
.btn:hover{transform:translateY(-1px)}
.btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent-dark));color:#21110f;box-shadow:0 7px 18px rgba(242,134,126,.14)}
.btn-secondary{background:#24324a;color:#edf3fb;border-color:rgba(148,163,184,.12)}
.btn-secondary:hover{background:#2d3d59}
.input-group{display:grid;gap:5px;margin-bottom:9px}
.input-group label{font-size:.82rem;color:#aab9cc;font-weight:650}
.input-group input,.input-group select,.input-group textarea{
    width:100%;padding:9px 12px;border-radius:10px;border:1px solid rgba(148,163,184,.22);
    background:#1b2940;color:var(--text);font-size:.9rem;min-height:40px;outline:none
}
.input-group textarea{resize:vertical}
.input-group select option{color:#0f172a;background:#f8fafc}
.input-group input:focus,.input-group select:focus,.input-group textarea:focus{
    border-color:var(--accent);box-shadow:0 0 0 3px rgba(242,134,126,.12)
}
.note{color:var(--muted);font-size:.88rem;line-height:1.55}
.action-row{display:flex;flex-wrap:wrap;gap:8px;margin-top:6px}
.inline-actions{display:flex;align-items:center;flex-wrap:wrap;gap:6px}
.inline-actions .btn{min-height:34px;padding:7px 10px;font-size:.76rem}
.full-span{grid-column:1/-1}
#admin-management .action-row{margin-top:8px}
.search-row{display:flex;gap:10px;align-items:center;margin-bottom:8px}
.search-row input{
    width:100%;padding:10px 12px;border:1px solid var(--border);border-radius:10px;
    background:#16243a;color:var(--text);outline:none
}
.search-row input:focus{border-color:var(--accent)}

#admin-management .compact-grid{align-items:start}
#admin-management .card{height:auto;min-height:0}
#user-accounts{margin-top:12px}

#petFormSection{display:block}
#petFormSection .card{max-width:900px;margin:0 auto}
@media(max-width:1000px){
    .grid,.compact-grid{grid-template-columns:1fr}
    .full-span{grid-column:auto}
    .quick-nav{top:100px}
}
@media(max-width:760px){
    .container{padding:14px}
    .header{position:relative;top:0;align-items:flex-start;flex-direction:column;padding:16px}
    .quick-nav{position:relative;top:0;overflow-x:auto;flex-wrap:nowrap}
    .card{padding:16px}
    .section{scroll-margin-top:20px}
}
</style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Super Admin Operations</h1>
            <p class="note">Manage staff accounts, users, and pet records from one organized workspace.</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">â† Dashboard</a>
    </div>
    <nav class="quick-nav" aria-label="Page sections">
        <a href="#admin-accounts">Admin Accounts</a>
        <a href="#admin-management">Create / Update Admin</a>
        <a href="#user-accounts">User Accounts</a>
        <a href="#pet-records">Pet Records</a>
        <a href="#petFormSection">Pet Editor</a>
        <a href="#pet-actions">Pet Actions</a>
    </nav>

    <section class="section" id="admin-accounts">
        <div class="card">
            <h2>Admin Accounts</h2>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($admins as $admin): ?>
                        <tr>
                            <td><?php echo $admin['id']; ?></td>
                            <td><?php echo htmlspecialchars($admin['username']); ?></td>
                            <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($admin['email']); ?></td>
                            <td><?php echo htmlspecialchars($admin['role']); ?></td>
                            <td><?php echo $admin['is_deleted'] ? 'Deleted' : ($admin['is_suspended'] ? 'Suspended' : 'Active'); ?></td>
                            <td class="inline-actions">
                                <button class="btn btn-secondary" onclick="resetAdminPassword(<?php echo $admin['id']; ?>)">Reset</button>
                                <button class="btn btn-secondary" onclick="toggleAdminState(<?php echo $admin['id']; ?>, <?php echo $admin['is_suspended'] ? 0 : 1; ?>)"><?php echo $admin['is_suspended'] ? 'Unsuspend' : 'Suspend'; ?></button>
                                <?php if (!$admin['is_deleted']): ?>
                                    <button class="btn btn-secondary" onclick="deleteAdmin(<?php echo $admin['id']; ?>)">Delete</button>
                                <?php else: ?>
                                    <button class="btn btn-primary" onclick="restoreAdmin(<?php echo $admin['id']; ?>)">Restore</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section" id="admin-management">
        <div class="grid compact-grid">
        <div class="card">
            <h2>Update Admin Account</h2>
            <p class="card-sub">Select an administrator, then edit their profile, role, or account state.</p>
            <form id="adminUpdateForm">
                <div class="input-group"><label>Select Admin</label><select id="updateAdminSelect" name="id" required><option value="">Choose admin</option><?php foreach ($admins as $admin): ?><option value="<?php echo $admin['id']; ?>"><?php echo htmlspecialchars($admin['username']); ?></option><?php endforeach; ?></select></div>
                <div class="input-group"><label>Full Name</label><input type="text" name="full_name" id="updateFullName" required></div>
                <div class="input-group"><label>Email</label><input type="email" name="email" id="updateEmail" required></div>
                <div class="input-group"><label>Role</label><select name="role" id="updateRole"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select></div>
                <div class="input-group"><label>State</label><select name="state" id="updateState"><option value="0">Active</option><option value="1">Suspended</option><option value="2">Deleted</option></select></div>
                <div class="action-row"><button class="btn btn-primary" type="submit">Update Admin</button></div>
            </form>
        </div>
        <div class="card">
            <h2>Create Admin Account</h2>
            <p class="card-sub">Create a new staff or owner account with the correct role.</p>
            <form id="adminCreateForm">
                <div class="input-group"><label>Full Name</label><input type="text" name="full_name" required></div>
                <div class="input-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="input-group"><label>Role</label><select name="role"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select></div>
                <div class="input-group"><label>Password</label><input type="password" name="password" required></div>
                <div class="action-row"><button class="btn btn-primary" type="submit">Create Admin</button></div>
            </form>
        </div>
        </div>
        <div class="card full-span" id="user-accounts">
            <h2>User Accounts</h2>
            <div class="search-row"><input id="userSearch" type="search" placeholder="Search users by name, username, email, role, or state..." oninput="filterRows(\'userSearch\', \'userAccountsTable\')"></div>
            <div class="table-wrap">
                <table id="userAccountsTable">
                    <thead><tr><th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Role</th><th>State</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td><?php echo htmlspecialchars($user['role']); ?></td>
                            <td><?php echo $user['is_deleted'] ? 'Deleted' : ($user['is_suspended'] ? 'Suspended' : 'Active'); ?></td>
                            <td class="inline-actions">
                                <?php if (!$user['is_deleted']): ?>
                                    <button class="btn btn-secondary" onclick="suspendUser(<?php echo $user['id']; ?>)">Suspend</button>
                                    <button class="btn btn-secondary" onclick="deleteUser(<?php echo $user['id']; ?>)">Delete</button>
                                <?php else: ?>
                                    <button class="btn btn-primary" onclick="restoreUser(<?php echo $user['id']; ?>)">Restore</button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section" id="pet-records">
        <div class="card">
            <h2>Pet Records</h2>
            <div class="search-row"><input id="petSearch" type="search" placeholder="Search pets by name, breed, status, gender, or shelter..." oninput="filterRows(\'petSearch\', \'petRecordsTable\')"></div>
            <div class="table-wrap">
                <table id="petRecordsTable">
                    <thead><tr><th>ID</th><th>Name</th><th>Breed</th><th>Age</th><th>Gender</th><th>Status</th><th>Shelter</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($pets as $pet): ?>
                        <tr>
                            <td><?php echo $pet['id']; ?></td>
                            <td><?php echo htmlspecialchars($pet['name']); ?><?php if ($pet['is_archived']): ?> <em style="color:var(--muted);font-style:normal;font-size:.78rem;">(archived)</em><?php endif; ?></td>
                            <td><?php echo htmlspecialchars($pet['breed']); ?></td>
                            <td><?php echo htmlspecialchars($pet['age']); ?></td>
                            <td><?php echo htmlspecialchars($pet['gender']); ?></td>
                            <td><?php echo htmlspecialchars($pet['status']); ?></td>
                            <td><?php echo htmlspecialchars($pet['shelter_id'] ?? ''); ?></td>
                            <td class="inline-actions">
                                <button class="btn btn-secondary" type="button" onclick="populatePetForm(<?php echo $pet['id']; ?>)">Edit</button>
                                <?php if ($pet['is_archived']): ?>
                                <button class="btn btn-secondary" type="button" onclick="quickSetArchived(<?php echo $pet['id']; ?>, false)">Unarchive</button>
                                <?php else: ?>
                                <button class="btn btn-secondary" type="button" onclick="quickSetArchived(<?php echo $pet['id']; ?>, true)">Archive</button>
                                <?php endif; ?>
                                <button class="btn btn-secondary" type="button" onclick="deletePet(<?php echo $pet['id']; ?>)">Delete</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="section grid" id="petFormSection">
        <div class="card">
            <h2 id="petFormTitle">Create / Edit Pet</h2>
            <form id="petForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="petId">
                <div class="input-group"><label>Name</label><input type="text" name="name" id="petName" required></div>
                <div class="input-group"><label>Breed</label><input type="text" name="breed" id="petBreed" required></div>
                <div class="input-group"><label>Age</label><input type="text" name="age" id="petAge"></div>
                <div class="input-group"><label>Gender</label><select name="gender" id="petGender"><option value="Male">Male</option><option value="Female">Female</option><option value="Unknown">Unknown</option></select></div>
                <div class="input-group"><label>Status</label><select name="status" id="petStatus"><option value="available">Available</option><option value="reserved">Reserved</option><option value="in_adoption">In Adoption</option><option value="adopted">Adopted</option><option value="under_treatment">Under Treatment</option></select></div>
                <div class="input-group"><label>Shelter</label><select name="shelter_id" id="petShelter">
                    <option value="0">None</option>
                    <?php foreach ($shelters as $shelter): ?>
                        <option value="<?php echo $shelter['id']; ?>"><?php echo htmlspecialchars($shelter['name']); ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div class="input-group"><label>Description</label><textarea name="description" id="petDescription" rows="4"></textarea></div>
                <div class="input-group"><label>Health Status</label><input type="text" name="health_status" id="petHealth"></div>
                <div class="input-group"><label>Pet Image</label><input type="file" id="petImages" name="images[]" accept="image/jpeg,image/png,image/webp"multiple></div>
                <div class="action-row"><button class="btn btn-primary" type="button" onclick="savePet()">Save Pet</button><button class="btn btn-secondary" type="button" onclick="resetPetForm()">Clear</button></div>
            </form>
        </div>
    </section>

    <section class="section" id="pet-actions">
        <div class="card">
            <h2>Pet Actions</h2>
            <p class="card-sub">Use these controls to transfer a pet or change its archive state.</p>
            <form id="petActionForm">
                <div class="input-group"><label>Pet ID</label><input type="number" name="id" required></div>
                <div class="input-group"><label>Transfer to Shelter</label><select name="shelter_id" required>
                    <option value="">Select shelter</option>
                    <?php foreach ($shelters as $shelter): ?>
                        <option value="<?php echo $shelter['id']; ?>"><?php echo htmlspecialchars($shelter['name']); ?></option>
                    <?php endforeach; ?>
                </select></div>
                <div class="action-row"><button class="btn btn-primary" type="button" onclick="transferPet()">Transfer Pet</button><button class="btn btn-secondary" type="button" onclick="archivePet()">Archive Pet</button><button class="btn btn-secondary" type="button" onclick="unarchivePet()">Unarchive Pet</button></div>
            </form>
        </div>
    </section>
</div>
<script>
    const apiEndpoint = 'super_admin_api.php';
    const petsData = <?php echo json_encode($pets); ?>;
    const adminsData = <?php echo json_encode($admins); ?>;

    async function apiRequest(formData) {
        const response = await fetch(apiEndpoint, {
            method: 'POST',
            credentials: 'include',
            body: formData
        });

        const responseText = await response.text();

        let data;

        try {
            data = JSON.parse(responseText);
        } catch (error) {
            console.error('Invalid API response:', responseText);
            throw new Error(
                'The server returned an invalid response. Check Railway logs.'
            );
        }

        if (
            response.status === 401 ||
            data.message === 'Unauthorized'
        ) {
            alert('Your login session has expired. Please log in again.');
            window.location.href = 'login_form.php';
            throw new Error('Unauthorized');
        }

        return data;
    }

    function handleApiError(error, fallbackMessage) {
        if (error.message === 'Unauthorized') {
            return;
        }

        console.error(error);
        alert(error.message || fallbackMessage);
    }

    function resetAdminPassword(id) {
        const password = prompt('Enter new password for admin:');

        if (!password) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'reset_admin_password');
        formData.append('id', id);
        formData.append('password', password);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Password reset successfully.'
                        : 'Password reset failed.'
                ));
            })
            .catch(error => {
                handleApiError(error, 'Reset failed.');
            });
    }

    function toggleAdminState(id, suspend) {
        const admin = adminsData.find(item => item.id == id);

        if (!admin) {
            alert('Admin record not found.');
            return;
        }

        const formData = new FormData();
        formData.append('action', 'update_admin');
        formData.append('id', id);
        formData.append('full_name', admin.full_name || '');
        formData.append('email', admin.email || '');
        formData.append('role', admin.role || 'admin');
        formData.append('is_suspended', suspend ? '1' : '0');
        formData.append(
            'is_deleted',
            admin.is_deleted ? '1' : '0'
        );

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    suspend
                        ? 'Admin suspended.'
                        : 'Admin unsuspended.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(
                    error,
                    'Admin state update failed.'
                );
            });
    }

    function deleteAdmin(id) {
        if (!confirm('Delete this admin account?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_admin');
        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Admin deleted.'
                        : 'Admin deletion failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Delete failed.');
            });
    }

    function restoreAdmin(id) {
        const formData = new FormData();
        formData.append('action', 'restore_admin');
        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Admin restored.'
                        : 'Admin restore failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Restore failed.');
            });
    }

    document
        .getElementById('updateAdminSelect')
        .addEventListener('change', event => {
            const id = parseInt(event.target.value, 10);

            const admin = adminsData.find(
                item => item.id == id
            );

            if (!admin) {
                return;
            }

            document.getElementById('updateFullName').value =
                admin.full_name || '';

            document.getElementById('updateEmail').value =
                admin.email || '';

            document.getElementById('updateRole').value =
                admin.role || 'admin';

            document.getElementById('updateState').value =
                admin.is_deleted
                    ? '2'
                    : (
                        admin.is_suspended
                            ? '1'
                            : '0'
                    );
        });

    document
        .getElementById('adminCreateForm')
        .addEventListener('submit', event => {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);

            formData.append('action', 'create_admin');

            apiRequest(formData)
                .then(data => {
                    alert(data.message || (
                        data.success
                            ? 'Admin created successfully.'
                            : 'Admin creation failed.'
                    ));

                    if (data.success) {
                        form.reset();
                        window.location.reload();
                    }
                })
                .catch(error => {
                    handleApiError(
                        error,
                        'Admin creation failed.'
                    );
                });
        });

    document
        .getElementById('adminUpdateForm')
        .addEventListener('submit', event => {
            event.preventDefault();

            const form = event.target;
            const formData = new FormData(form);
            const state = formData.get('state');

            formData.append('action', 'update_admin');

            formData.append(
                'is_suspended',
                state === '1' ? '1' : '0'
            );

            formData.append(
                'is_deleted',
                state === '2' ? '1' : '0'
            );

            apiRequest(formData)
                .then(data => {
                    alert(data.message || (
                        data.success
                            ? 'Admin updated.'
                            : 'Admin update failed.'
                    ));

                    if (data.success) {
                        window.location.reload();
                    }
                })
                .catch(error => {
                    handleApiError(
                        error,
                        'Update failed.'
                    );
                });
        });

    function suspendUser(id) {
        const formData = new FormData();
        formData.append('action', 'suspend_user');
        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'User suspended.'
                        : 'User suspension failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Suspend failed.');
            });
    }

    function deleteUser(id) {
        if (!confirm('Delete this user account?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'User deleted.'
                        : 'User deletion failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Delete failed.');
            });
    }

    function restoreUser(id) {
        const formData = new FormData();
        formData.append('action', 'restore_user');
        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'User restored.'
                        : 'User restore failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Restore failed.');
            });
    }

    function populatePetForm(id) {
        const pet = petsData.find(item => item.id == id);

        if (!pet) {
            alert('Pet record not found.');
            return;
        }

        document.getElementById('petId').value =
            pet.id || '';

        document.getElementById('petName').value =
            pet.name || '';

        document.getElementById('petBreed').value =
            pet.breed || '';

        document.getElementById('petAge').value =
            pet.age || '';

        document.getElementById('petGender').value =
            pet.gender || 'Unknown';

        document.getElementById('petStatus').value =
            pet.status || 'available';

        document.getElementById('petShelter').value =
            pet.shelter_id || 0;

        document.getElementById('petDescription').value =
            pet.description || '';

        document.getElementById('petHealth').value =
            pet.health_status || '';

        document.getElementById(
            'petFormTitle'
        ).textContent = 'Edit Pet â€” ' + (pet.name || '');

        document
            .getElementById('petFormSection')
            .scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
    }

    function resetPetForm() {
        document.getElementById('petForm').reset();
        document.getElementById('petId').value = '';

        document.getElementById(
            'petFormTitle'
        ).textContent = 'Create / Edit Pet';
    }

    function quickSetArchived(id, archive) {
        const formData = new FormData();

        formData.append(
            'action',
            archive
                ? 'archive_pet'
                : 'unarchive_pet'
        );

        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    archive
                        ? 'Pet archived.'
                        : 'Pet unarchived.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(
                    error,
                    archive
                        ? 'Archive failed.'
                        : 'Unarchive failed.'
                );
            });
    }

    function savePet() {
        const form = document.getElementById('petForm');
        const formData = new FormData(form);

        const action = formData.get('id')
            ? 'update_pet'
            : 'create_pet';

        formData.append('action', action);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Pet saved.'
                        : 'Pet save failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Save failed.');
            });
    }

    function deletePet(id) {
        if (!confirm('Delete this pet record?')) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'delete_pet');
        formData.append('id', id);

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Pet deleted.'
                        : 'Pet deletion failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Delete failed.');
            });
    }

    function transferPet() {
        const form =
            document.getElementById('petActionForm');

        const formData = new FormData(form);
        formData.append('action', 'transfer_pet');

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Pet transferred.'
                        : 'Pet transfer failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Transfer failed.');
            });
    }

    function archivePet() {
        const form =
            document.getElementById('petActionForm');

        const formData = new FormData(form);
        formData.append('action', 'archive_pet');

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Pet archived.'
                        : 'Pet archive failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Archive failed.');
            });
    }

    function unarchivePet() {
        const form =
            document.getElementById('petActionForm');

        const formData = new FormData(form);
        formData.append('action', 'unarchive_pet');

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Pet unarchived.'
                        : 'Pet unarchive failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Unarchive failed.');
            });
    }

    function filterRows(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        if (!input || !table) return;

        const query = input.value.trim().toLowerCase();

        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(query) ? '' : 'none';
        });
    }

</script>
</body>
</html>
