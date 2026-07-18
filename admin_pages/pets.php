<?php
require_permission($conn, 'manage_pets');

$filterStatus  = $_GET['pet_status'] ?? '';
$filterSpecies = $_GET['species']    ?? '';
$showArchived  = isset($_GET['archived']) && $_GET['archived'] === '1';
// Kept in sync with the canonical pet-status set (add_pet/update_pet in admin_api.php,
// application_status_helper.php's in_adoption/adopted/available writes). 'archived' is
// a separate is_archived flag, not a status value, to avoid two conflicting archive signals.
$validStatuses = ['available','reserved','in_adoption','adopted','under_treatment'];
if (!in_array($filterStatus, $validStatuses)) $filterStatus = '';

$sql = "SELECT id, name, IFNULL(species,'') as species, breed, age, gender, status, health_status, image, is_archived, created_at FROM pets WHERE is_archived = ?";
$params = [$showArchived ? 1 : 0];
$types  = 'i';
if ($filterStatus)  { $sql .= " AND status=?";  $params[] = $filterStatus;  $types .= 's'; }
if ($filterSpecies) { $sql .= " AND species=?"; $params[] = $filterSpecies; $types .= 's'; }
$sql .= " ORDER BY id DESC";

$pets = [];
if ($params) {
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($sql);
}
if ($result) while ($row = $result->fetch_assoc()) $pets[] = $row;

// Count by status (scoped to the same archived/active bucket currently being viewed)
$statusCounts = [];
$countStmt = $conn->prepare("SELECT status, COUNT(*) as cnt FROM pets WHERE is_archived = ? GROUP BY status");
$archivedFlag = $showArchived ? 1 : 0;
$countStmt->bind_param('i', $archivedFlag);
$countStmt->execute();
$r = $countStmt->get_result();
if ($r) while ($row = $r->fetch_assoc()) $statusCounts[$row['status']] = $row['cnt'];
$totalCount = array_sum($statusCounts);
$archivedTotal = (int)($conn->query("SELECT COUNT(*) FROM pets WHERE is_archived = 1")->fetch_row()[0] ?? 0);
?>

<!-- ══ HEADER ══════════════════════════════════════════════════════════ -->
<div class="card">
<div class="card-header" style="flex-wrap:wrap;">
    <div>
        <div class="card-title">Pet Inventory<?php if ($showArchived) echo ' — Archived'; ?></div>
        <div class="card-sub"><?php echo $totalCount; ?> pet<?php echo $totalCount===1?'':'s'; ?> <?php echo $showArchived ? 'archived' : 'registered'; ?></div>
    </div>
    <button class="btn btn-primary" onclick="openModal('addPetModal')">+ Add New Pet</button>
</div>

<!-- Status filter pills -->
<div class="status-pills">
    <a href="?page=pets" class="s-pill <?php echo (!$filterStatus && !$showArchived)?'active':''; ?>">
        All <span class="pill-cnt"><?php echo $totalCount; ?></span>
    </a>
    <?php
    $pillLabels = ['available'=>'Available','reserved'=>'Reserved','in_adoption'=>'In Adoption','adopted'=>'Adopted','under_treatment'=>'Under Treatment'];
    foreach ($pillLabels as $s => $l):
        $cnt = $statusCounts[$s] ?? 0;
    ?>
    <a href="?page=pets&pet_status=<?php echo $s; ?><?php echo $showArchived?'&archived=1':''; ?>" class="s-pill <?php echo $filterStatus===$s?'active':''; ?>">
        <?php echo $l; ?> <span class="pill-cnt"><?php echo $cnt; ?></span>
    </a>
    <?php endforeach; ?>
    <a href="?page=pets&archived=1" class="s-pill <?php echo $showArchived?'active':''; ?>" style="margin-left:auto;">
        🗄️ Archived <span class="pill-cnt"><?php echo $archivedTotal; ?></span>
    </a>
</div>

<!-- Search -->
<div class="filters-bar">
    <div class="search-wrap">
        <span class="search-icon">🔍</span>
        <input type="text" id="petSearch" placeholder="Search by name, breed, species…" oninput="filterTable('petSearch','petTable')">
    </div>
    <select class="form-control" style="width:auto;" onchange="location.href='?page=pets&pet_status=<?php echo $filterStatus; ?>&species='+this.value">
        <option value="">All Species</option>
        <?php foreach(['Dog','Cat','Rabbit','Bird','Other'] as $sp): ?>
        <option value="<?php echo $sp; ?>" <?php echo $filterSpecies===$sp?'selected':''; ?>><?php echo $sp; ?></option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Table -->
<?php if (empty($pets)): ?>
<div class="empty-state"><div class="empty-icon">🐾</div><p>No pets found. Add one above!</p></div>
<?php else: ?>
<div class="table-wrap">
<table id="petTable">
    <thead>
        <tr>
            <th>Photo</th><th>Name</th><th>Species/Breed</th><th>Age</th>
            <th>Gender</th><th>Status</th><th>Health</th><th>Added</th><th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($pets as $p):
        $imgSrc = !empty($p['image']) ? 'images/'.htmlspecialchars($p['image']) : null;
    ?>
    <tr>
        <td>
            <div class="pet-thumb">
                <?php if ($imgSrc): ?>
                <img src="<?php echo $imgSrc; ?>" alt="<?php echo htmlspecialchars($p['name']); ?>">
                <?php else: ?>🐾<?php endif; ?>
            </div>
        </td>
        <td style="font-weight:600;"><?php echo htmlspecialchars($p['name']); ?></td>
        <td>
            <div><?php echo htmlspecialchars($p['breed']); ?></div>
            <?php if ($p['species']): ?><div style="font-size:.76rem;color:var(--muted);"><?php echo htmlspecialchars($p['species']); ?></div><?php endif; ?>
        </td>
        <td><?php echo htmlspecialchars($p['age']); ?></td>
        <td><?php echo htmlspecialchars($p['gender']); ?></td>
        <td><span class="badge badge-<?php echo $p['status']; ?>"><?php echo ucwords(str_replace('_',' ',$p['status'])); ?></span></td>
        <td style="font-size:.82rem;"><?php echo htmlspecialchars($p['health_status']); ?></td>
        <td style="font-size:.8rem;color:var(--muted);"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></td>
        <td>
            <button class="btn btn-primary btn-sm" onclick="editPet(<?php echo $p['id']; ?>)">Edit</button>
            <?php if ($p['is_archived']): ?>
            <button class="btn btn-ghost btn-sm" onclick="setArchived(<?php echo $p['id']; ?>, false)">Unarchive</button>
            <?php else: ?>
            <button class="btn btn-ghost btn-sm" onclick="setArchived(<?php echo $p['id']; ?>, true)">Archive</button>
            <?php endif; ?>
            <button class="btn btn-danger btn-sm" onclick="deletePet(<?php echo $p['id']; ?>, '<?php echo htmlspecialchars($p['name'],ENT_QUOTES); ?>')">Delete</button>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<?php endif; ?>
</div>

<!-- ══ ADD PET MODAL ═══════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="addPetModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title">🐾 Add New Pet</span>
        <button class="modal-close" onclick="closeModal('addPetModal')">✕</button>
    </div>
    <div class="modal-body">
        <div class="tabs" data-tg="add">
            <button class="tab-btn active" data-tab="basic" onclick="switchTab('add','basic')">Basic Info</button>
            <button class="tab-btn" data-tab="medical" onclick="switchTab('add','medical')">Medical Records</button>
            <button class="tab-btn" data-tab="photo" onclick="switchTab('add','photo')">Photo</button>
        </div>

        <form id="addPetForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="add_pet">

        <!-- Basic Info -->
        <div class="tab-pane active" data-tg="add" data-tab="basic">
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Buddy">
                </div>
                <div class="form-group">
                    <label class="form-label">Species</label>
                    <select name="species" class="form-control">
                        <option value="">Select…</option>
                        <?php foreach(['Dog','Cat','Rabbit','Bird','Other'] as $sp): ?>
                        <option value="<?php echo $sp; ?>"><?php echo $sp; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Breed *</label>
                    <input type="text" name="breed" class="form-control" required placeholder="e.g. Golden Retriever">
                </div>
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input type="text" name="age" class="form-control" placeholder="e.g. 2 years">
                </div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-control">
                        <option value="">Select…</option>
                        <option>Male</option><option>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="in_adoption">In Adoption</option>
                        <option value="adopted">Adopted</option>
                        <option value="under_treatment">Under Treatment</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Health Status</label>
                <input type="text" name="health_status" class="form-control" placeholder="e.g. Healthy, Recovering from surgery">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" placeholder="Personality, background, special notes…"></textarea>
            </div>
        </div>

        <!-- Medical Records -->
        <div class="tab-pane" data-tg="add" data-tab="medical">
            <div class="form-group">
                <label class="form-label">Vaccination Records</label>
                <textarea name="vaccination_records" class="form-control" style="min-height:120px;" placeholder="e.g.&#10;- Rabies vaccine — Jan 15, 2025&#10;- Distemper — Mar 10, 2025"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Medical Records / Notes</label>
                <textarea name="medical_records" class="form-control" style="min-height:120px;" placeholder="e.g.&#10;- Spayed — Feb 2025&#10;- Dewormed — Mar 2025&#10;- Flea treatment — Apr 2025"></textarea>
            </div>
        </div>

        <!-- Photo -->
        <div class="tab-pane" data-tg="add" data-tab="photo">
            <div class="form-group">
                <label class="form-label">Pet Photo</label>
                <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImg(this,'addPreview')">
            </div>
            <div id="addPreview" style="margin-top:12px;display:none;">
                <img id="addPreviewImg" src="" alt="" style="max-width:200px;border-radius:8px;border:1px solid var(--border);">
            </div>
            <p style="margin-top:8px;font-size:.8rem;color:var(--muted);">Max 5 MB. JPG, PNG, GIF, WebP allowed.</p>
        </div>

        </form>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('addPetModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitAddPet()">Save Pet</button>
    </div>
</div>
</div>

<!-- ══ EDIT PET MODAL ══════════════════════════════════════════════════ -->
<div class="modal-backdrop" id="editPetModal">
<div class="modal modal-lg">
    <div class="modal-header">
        <span class="modal-title">✏️ Edit Pet</span>
        <button class="modal-close" onclick="closeModal('editPetModal')">✕</button>
    </div>
    <div class="modal-body">
        <div class="tabs" data-tg="edit">
            <button class="tab-btn active" data-tab="basic" onclick="switchTab('edit','basic')">Basic Info</button>
            <button class="tab-btn" data-tab="medical" onclick="switchTab('edit','medical')">Medical Records</button>
            <button class="tab-btn" data-tab="photo" onclick="switchTab('edit','photo')">Photo</button>
        </div>

        <form id="editPetForm" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_pet">
        <input type="hidden" name="id" id="edit_id">

        <!-- Basic Info -->
        <div class="tab-pane active" data-tg="edit" data-tab="basic">
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Species</label>
                    <select name="species" id="edit_species" class="form-control">
                        <option value="">Select…</option>
                        <?php foreach(['Dog','Cat','Rabbit','Bird','Other'] as $sp): ?>
                        <option value="<?php echo $sp; ?>"><?php echo $sp; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Breed *</label>
                    <input type="text" name="breed" id="edit_breed" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Age</label>
                    <input type="text" name="age" id="edit_age" class="form-control">
                </div>
            </div>
            <div class="form-row cols-2">
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" id="edit_gender" class="form-control">
                        <option value="">Select…</option>
                        <option>Male</option><option>Female</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="available">Available</option>
                        <option value="reserved">Reserved</option>
                        <option value="in_adoption">In Adoption</option>
                        <option value="adopted">Adopted</option>
                        <option value="under_treatment">Under Treatment</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Health Status</label>
                <input type="text" name="health_status" id="edit_health" class="form-control">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="edit_desc" class="form-control"></textarea>
            </div>
        </div>

        <!-- Medical Records -->
        <div class="tab-pane" data-tg="edit" data-tab="medical">
            <div class="form-group">
                <label class="form-label">Vaccination Records</label>
                <textarea name="vaccination_records" id="edit_vaccines" class="form-control" style="min-height:120px;"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">Medical Records / Notes</label>
                <textarea name="medical_records" id="edit_medical" class="form-control" style="min-height:120px;"></textarea>
            </div>
        </div>

        <!-- Photo -->
        <div class="tab-pane" data-tg="edit" data-tab="photo">
            <div id="currentPetPhoto" style="margin-bottom:12px;"></div>
            <div class="form-group">
                <label class="form-label">Replace Photo</label>
                <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImg(this,'editPreview')">
            </div>
            <div id="editPreview" style="margin-top:12px;display:none;">
                <img id="editPreviewImg" src="" alt="" style="max-width:200px;border-radius:8px;border:1px solid var(--border);">
            </div>
        </div>

        </form>
    </div>
    <div class="modal-footer">
        <button class="btn btn-ghost" onclick="closeModal('editPetModal')">Cancel</button>
        <button class="btn btn-primary" onclick="submitEditPet()">Save Changes</button>
    </div>
</div>
</div>

<script>
function previewImg(input, wrapId) {
    const wrap = document.getElementById(wrapId);
    const img  = wrap.querySelector('img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; wrap.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}

async function editPet(id) {
    switchTab('edit','basic');
    try {
        const res  = await fetch('admin_api.php?action=get_pet&id='+id);
        const data = await res.json();
        if (!data.success) { showToast(data.message,'error'); return; }
        const p = data.pet;
        document.getElementById('edit_id').value      = p.id;
        document.getElementById('edit_name').value    = p.name     || '';
        document.getElementById('edit_breed').value   = p.breed    || '';
        document.getElementById('edit_age').value     = p.age      || '';
        document.getElementById('edit_health').value  = p.health_status || '';
        document.getElementById('edit_desc').value    = p.description   || '';
        document.getElementById('edit_vaccines').value= p.vaccination_records || '';
        document.getElementById('edit_medical').value = p.medical_records     || '';
        setSelectVal('edit_species', p.species || '');
        setSelectVal('edit_gender',  p.gender  || '');
        setSelectVal('edit_status',  p.status  || 'available');
        // Current photo
        const cp = document.getElementById('currentPetPhoto');
        if (p.image) {
            cp.innerHTML = '<p style="font-size:.82rem;color:var(--muted);margin-bottom:6px;">Current photo:</p><img src="images/'+p.image+'" style="max-width:160px;border-radius:8px;border:1px solid var(--border);">';
        } else {
            cp.innerHTML = '<p style="font-size:.82rem;color:var(--muted);">No photo uploaded.</p>';
        }
        document.getElementById('editPreview').style.display = 'none';
        openModal('editPetModal');
    } catch(e) { showToast('Failed to load pet data','error'); }
}

function setSelectVal(id, val) {
    const el = document.getElementById(id);
    if (!el) return;
    for (let i=0; i<el.options.length; i++) {
        if (el.options[i].value === val) { el.selectedIndex = i; break; }
    }
}

async function submitAddPet() {
    const form = document.getElementById('addPetForm');
    const data = new FormData(form);
    try {
        const res  = await fetch('admin_api.php', {method:'POST', body:data});
        const json = await res.json();
        if (json.success) { showToast('Pet added successfully!'); closeModal('addPetModal'); form.reset(); location.reload(); }
        else showToast(json.message,'error');
    } catch(e) { showToast('Request failed','error'); }
}

async function submitEditPet() {
    const form = document.getElementById('editPetForm');
    const data = new FormData(form);
    try {
        const res  = await fetch('admin_api.php', {method:'POST', body:data});
        const json = await res.json();
        if (json.success) { showToast('Pet updated!'); closeModal('editPetModal'); location.reload(); }
        else showToast(json.message,'error');
    } catch(e) { showToast('Request failed','error'); }
}

async function deletePet(id, name) {
    if (!confirm('Delete "' + name + '"? This cannot be undone.')) return;
    const res  = await fetch('admin_api.php', {method:'POST', body: new URLSearchParams({action:'delete_pet',id})});
    const data = await res.json();
    if (data.success) { showToast('Pet deleted'); location.reload(); }
    else showToast(data.message,'error');
}

async function setArchived(id, archive) {
    const action = archive ? 'archive_pet' : 'unarchive_pet';
    const res  = await fetch('admin_api.php', {method:'POST', body: new URLSearchParams({action,id})});
    const data = await res.json();
    if (data.success) { showToast(archive ? 'Pet archived' : 'Pet unarchived'); location.reload(); }
    else showToast(data.message,'error');
}
</script>
