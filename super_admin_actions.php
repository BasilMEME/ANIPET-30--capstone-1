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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin Actions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.92);
            --panel-soft: rgba(15, 23, 42, 0.78);
            --text: #f8fafc;
            --muted: #94a3b8;
            --accent: #F2867E;
            --accent-2: #F6C9A0;
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
        .input-group input, .input-group select, .input-group textarea{
            width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.06);color:var(--text);font-size:0.95rem;min-height:46px;appearance:auto;-webkit-appearance:menulist;-moz-appearance:menulist;
        }
        .input-group select option{color:#020617;background:#f8fafc;padding:8px;}
        .input-group select:focus, .input-group input:focus, .input-group textarea:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(242,134,126,.18);}
        .section{margin-top:28px;}
        .section h2{margin-bottom:18px;font-size:1.12rem;}
        .note{color:var(--muted);font-size:.94rem;line-height:1.6;}
        .action-row{display:flex;flex-wrap:wrap;gap:12px;margin-top:16px;}
        .inline-actions{display:flex;flex-wrap:wrap;gap:8px;}
        @media (max-width: 1000px){ .grid{grid-template-columns:1fr;} }
        @media (max-width: 760px){ .container{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>Super Admin Operations</h1>
            <p class="note">Execute admin, user, pet, and application actions directly from one panel.</p>
        </div>
        <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
    </div>

    <section class="section">
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

    <section class="section grid">
        <div class="card">
            <h2>Update Admin Account</h2>
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
            <form id="adminCreateForm">
                <div class="input-group"><label>Full Name</label><input type="text" name="full_name" required></div>
                <div class="input-group"><label>Email</label><input type="email" name="email" required></div>
                <div class="input-group"><label>Role</label><select name="role"><option value="admin">Admin</option><option value="super_admin">Super Admin</option></select></div>
                <div class="input-group"><label>Password</label><input type="password" name="password" required></div>
                <div class="action-row"><button class="btn btn-primary" type="submit">Create Admin</button></div>
            </form>
        </div>
        <div class="card">
            <h2>User Accounts</h2>
            <div class="table-wrap">
                <table>
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

    <section class="section">
        <div class="card">
            <h2>Pet Records</h2>
            <div class="table-wrap">
                <table>
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
                <div class="input-group"><label>Pet Image</label><input type="file" name="image" id="petImage" accept="image/*"></div>
                <div class="action-row"><button class="btn btn-primary" type="button" onclick="savePet()">Save Pet</button><button class="btn btn-secondary" type="button" onclick="resetPetForm()">Clear</button></div>
            </form>
        </div>
    </section>

    <section class="section grid">
        <div class="card">
            <h2>Pet Actions</h2>
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
        <div class="card">
            <h2>Application Override</h2>
            <form id="appActionForm">
                <div class="input-group"><label>Application ID</label><input type="number" name="id" required></div>
                <div class="input-group"><label>New Status</label><select name="status" required>
                    <option value="pending">Pending</option>
                    <option value="screening">Screening</option>
                    <option value="approved">Approved</option>
                    <option value="for_releasing">For Releasing</option>
                    <option value="ready_pickup">Ready Pickup</option>
                    <option value="completed">Completed</option>
                    <option value="rejected">Rejected</option>
                </select></div>
                <div class="input-group"><label>Admin Notes</label><input type="text" name="admin_notes" placeholder="Reason for override"></div>
                <div class="action-row"><button class="btn btn-primary" type="button" onclick="overrideApplication()">Override</button><button class="btn btn-secondary" type="button" onclick="reopenApplication()">Reopen</button></div>
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
        ).textContent = 'Edit Pet — ' + (pet.name || '');

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

    function overrideApplication() {
        const form =
            document.getElementById('appActionForm');

        const formData = new FormData(form);
        formData.append(
            'action',
            'override_application'
        );

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Application overridden.'
                        : 'Application override failed.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Override failed.');
            });
    }

    function reopenApplication() {
        const form =
            document.getElementById('appActionForm');

        const formData = new FormData(form);
        formData.append(
            'action',
            'reopen_application'
        );

        apiRequest(formData)
            .then(data => {
                alert(data.message || (
                    data.success
                        ? 'Application reopened.'
                        : 'Failed to reopen application.'
                ));

                if (data.success) {
                    window.location.reload();
                }
            })
            .catch(error => {
                handleApiError(error, 'Reopen failed.');
            });
    }
</script>
</body>
</html>
