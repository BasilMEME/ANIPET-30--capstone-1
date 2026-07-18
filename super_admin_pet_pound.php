<?php
require_once __DIR__ . '/auth_helper.php';
require_super_or_permission('manage_returns');

// Lazy grace-period expiry — see admin_pages/pet_pound.php for the same check.
$conn->query("UPDATE pet_pound SET status='Expired' WHERE status='Pending' AND claim_deadline < NOW()");

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

$poundPets    = fetchRows($conn, "SELECT * FROM pet_pound WHERE status <> 'Deceased' ORDER BY impound_date DESC");
$deceasedPets = fetchRows($conn, "SELECT * FROM pet_pound WHERE status = 'Deceased' ORDER BY death_date DESC");

function poundBadgeClass($status){
    switch($status){
        case "Pending":  return "badge-pending";
        case "Claimed":
        case "Paid":     return "badge-approved";
        case "Posted":   return "badge-info";
        case "Expired":
        case "Deceased": return "badge-danger";
        default:         return "badge";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AniPet Super Admin — Pet Pound</title>
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
            --danger: #fb7185;
        }
        * { box-sizing: border-box; }
        body { margin:0; font-family:'Inter',sans-serif; color:var(--text); min-height:100vh; background: radial-gradient(circle at top left, rgba(242,134,126,.14), transparent 25%), radial-gradient(circle at bottom right, rgba(246,201,160,.12), transparent 24%), linear-gradient(135deg, #020617 0%, #07111f 100%); }
        .container{max-width:1440px;margin:0 auto;padding:24px 24px 40px;}
        .header{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;margin-bottom:24px;padding:18px 20px;background:rgba(15,23,42,.72);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow);backdrop-filter:blur(14px);}
        .header h1{margin:0;font-size:clamp(1.4rem,2vw,2rem);}
        .card{background:var(--panel);border:1px solid var(--border);border-radius:24px;padding:22px;box-shadow:var(--shadow);backdrop-filter:blur(12px);margin-bottom:22px;}
        .card h2{margin:0 0 12px;font-size:1.08rem;color:#cbd5e1;}
        .table-wrap{overflow-x:auto;border:1px solid rgba(148,163,184,.12);border-radius:18px;background:rgba(255,255,255,.025);}
        table{width:100%;border-collapse:collapse;color:var(--text);table-layout:fixed;min-width:820px;}
        th,td{padding:14px 12px;text-align:left;border-bottom:1px solid rgba(148,163,184,.12);overflow-wrap:anywhere;word-break:break-word;}
        th{color:var(--muted);font-size:.82rem;text-transform:uppercase;letter-spacing:.05em;}
        tr:hover{background:rgba(242,134,126,.08);}
        .btn{display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:14px;border:none;cursor:pointer;font-weight:700;transition:transform .18s ease,background .18s ease,box-shadow .18s ease;font-size:.85rem;}
        .btn:hover{transform:translateY(-1px);box-shadow:0 8px 20px rgba(2,8,23,.22);}
        .btn:disabled{opacity:.5;cursor:not-allowed;transform:none;box-shadow:none;}
        .btn-primary{background:linear-gradient(135deg,var(--accent),#D9695F);color:#241209;}
        .btn-secondary{background:rgba(255,255,255,.08);color:var(--text);}
        .btn-success{background:linear-gradient(135deg,#34d399,#059669);color:#052e18;}
        .btn-warning{background:linear-gradient(135deg,#fbbf24,#d97706);color:#2a1a02;}
        .btn-info{background:linear-gradient(135deg,#60a5fa,#2563eb);color:#eff6ff;}
        .btn-danger{background:linear-gradient(135deg,#fb7185,#e11d48);color:#fff1f2;}
        .btn-sm{padding:6px 12px;font-size:.78rem;}
        .note{color:var(--muted);font-size:.94rem;line-height:1.6;}
        .empty-state{padding:40px 20px;text-align:center;color:var(--muted);}
        .empty-icon{font-size:2rem;margin-bottom:8px;}
        .pet-cell{display:flex;align-items:center;gap:10px;}
        .pet-thumb{width:42px;height:42px;object-fit:cover;border-radius:10px;border:1px solid var(--border);}
        .badge{display:inline-flex;align-items:center;padding:5px 10px;border-radius:999px;font-size:.76rem;font-weight:700;background:rgba(148,163,184,.16);color:#e2e8f0;}
        .badge-pending{background:rgba(251,191,36,.16);color:#fbbf24;}
        .badge-approved{background:rgba(52,211,153,.16);color:#34d399;}
        .badge-info{background:rgba(96,165,250,.16);color:#60a5fa;}
        .badge-danger{background:rgba(251,113,133,.16);color:#fb7185;}
        .tabs{display:flex;gap:2px;margin-bottom:18px;border-bottom:2px solid var(--border);}
        .tab-btn{background:none;border:none;cursor:pointer;padding:9px 14px;font-size:.85rem;font-weight:600;color:var(--muted);border-bottom:2px solid transparent;margin-bottom:-2px;}
        .tab-btn.active{color:var(--accent);border-bottom-color:var(--accent);}
        .tab-pane{display:none;}
        .tab-pane.active{display:block;}
        .form-group{margin-bottom:14px;}
        .form-label{display:block;margin-bottom:6px;color:var(--muted);font-size:.85rem;}
        .form-control{width:100%;padding:12px 14px;border-radius:14px;border:1px solid rgba(148,163,184,.24);background:rgba(255,255,255,.06);color:var(--text);font-size:.95rem;min-height:46px;}
        .info-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .info-item label{display:block;color:var(--muted);font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;}
        .divider{height:1px;background:var(--border);margin:16px 0;}
        .modal-backdrop{display:none;position:fixed;inset:0;background:rgba(2,8,23,.65);z-index:200;align-items:center;justify-content:center;padding:20px;}
        .modal-backdrop.open{display:flex;}
        .modal{background:#0b1524;border:1px solid var(--border);border-radius:20px;max-width:560px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:var(--shadow);}
        .modal-lg{max-width:720px;}
        .modal-header{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--border);}
        .modal-title{font-size:1.05rem;font-weight:700;}
        .modal-close{background:none;border:none;color:var(--muted);font-size:1.4rem;cursor:pointer;line-height:1;}
        .modal-body{padding:20px 22px;}
        .modal-footer{display:flex;justify-content:flex-end;gap:10px;padding:16px 22px;border-top:1px solid var(--border);}
        @media (max-width: 760px){ .container{padding:16px 14px 28px;} .header{padding:16px;} .card{padding:18px;} .info-grid{grid-template-columns:1fr;} }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h1>🏠 Pet Pound</h1>
            <p class="note">Pets impounded due to a penalty get a 48-hour grace period before they can be posted for adoption.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary" onclick="openModal('addPetModal')">+ Add Impounded Pet</button>
            <a class="btn btn-secondary" href="super_admin_dashboard.php">Return to Dashboard</a>
        </div>
    </div>

    <div class="card">

        <div class="tabs" data-tg="pound">
            <button type="button" class="tab-btn active" data-tab="active" onclick="switchTab('active')">Impounded Pets</button>
            <button type="button" class="tab-btn" data-tab="deceased" onclick="switchTab('deceased')">Deceased Records</button>
        </div>

        <div class="tab-pane active" data-tab="active">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Pet</th><th>Owner</th><th>Reason</th><th>Penalty</th><th>Impounded</th><th>Grace Period Ends</th><th>Status</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($poundPets)): ?>
                        <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">🐾</div>No impounded pets found.</div></td></tr>
                    <?php else: foreach ($poundPets as $row): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <div class="pet-cell" style="cursor:pointer;" onclick="openPet(<?php echo $row['id']; ?>)">
                                    <?php if (!empty($row['pet_photo'])): ?>
                                        <img src="images/pet_pound/<?php echo htmlspecialchars($row['pet_photo']); ?>" class="pet-thumb">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($row['pet_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['reason']); ?></td>
                            <td>₱<?php echo number_format($row['penalty_amount'], 2); ?></td>
                            <td><?php echo date("M d, Y g:i A", strtotime($row['impound_date'])); ?></td>
                            <td><?php echo date("M d, Y g:i A", strtotime($row['claim_deadline'])); ?></td>
                            <td><span class="badge <?php echo poundBadgeClass($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                            <td><button class="btn btn-primary btn-sm" onclick="openPet(<?php echo $row['id']; ?>)">Manage</button></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="tab-pane" data-tab="deceased">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Pet</th><th>Owner</th><th>Cause of Death</th><th>Remarks</th><th>Date</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($deceasedPets)): ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">☠</div>No deceased records found.</div></td></tr>
                    <?php else: foreach ($deceasedPets as $row): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td>
                                <div class="pet-cell">
                                    <?php if (!empty($row['pet_photo'])): ?>
                                        <img src="images/pet_pound/<?php echo htmlspecialchars($row['pet_photo']); ?>" class="pet-thumb">
                                    <?php endif; ?>
                                    <span><?php echo htmlspecialchars($row['pet_name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['owner_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['cause_of_death'] ?? ''); ?></td>
                            <td><?php echo nl2br(htmlspecialchars($row['death_remarks'] ?? '')); ?></td>
                            <td><?php echo $row['death_date'] ? date("M d, Y g:i A", strtotime($row['death_date'])) : '—'; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- ADD IMPOUNDED PET MODAL -->
<div id="addPetModal" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Add Impounded Pet</div>
            <button class="modal-close" onclick="closeModal('addPetModal')">&times;</button>
        </div>
        <div class="modal-body">
            <form id="addPetForm" enctype="multipart/form-data">
                <div class="form-group"><label class="form-label">Pet Name</label><input type="text" name="pet_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Pet Photo</label><input type="file" name="pet_photo" class="form-control" accept="image/*"></div>
                <div class="form-group"><label class="form-label">Owner Name</label><input type="text" name="owner_name" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Reason</label><textarea name="reason" class="form-control" required></textarea></div>
                <div class="form-group"><label class="form-label">Penalty Amount</label><input type="number" step="0.01" name="penalty_amount" class="form-control" required></div>
                <div class="form-group"><p class="note" style="margin:0;">The owner gets a fixed 48-hour grace period from now to claim this pet.</p></div>
                <div class="form-group"><label class="form-label">Species</label><input type="text" name="species" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Breed</label><input type="text" name="breed" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Age</label><input type="text" name="age" class="form-control" required></div>
                <div class="form-group"><label class="form-label">Gender</label>
                    <select name="gender" class="form-control" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">Health Status</label><input type="text" name="health_status" class="form-control" placeholder="Healthy, Injured, Sick..." required></div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('addPetModal')">Cancel</button>
            <button class="btn btn-primary" onclick="saveImpoundedPet()">Save Pet</button>
        </div>
    </div>
</div>

<!-- PET DETAILS / MANAGE MODAL -->
<div id="petModal" class="modal-backdrop">
    <div class="modal modal-lg">
        <div class="modal-header">
            <div class="modal-title">Pet Details</div>
            <button class="modal-close" onclick="closeModal('petModal')">&times;</button>
        </div>
        <div class="modal-body" id="petModalBody"></div>
    </div>
</div>

<!-- PAYMENT MODAL -->
<div id="paymentModal" class="modal-backdrop">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Penalty Payment</div>
            <button class="modal-close" onclick="closeModal('paymentModal')">&times;</button>
        </div>
        <div class="modal-body" id="paymentContent">Loading...</div>
    </div>
</div>

<!-- MARK AS DECEASED MODAL -->
<div class="modal-backdrop" id="deceasedModal">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Mark as Deceased</div>
            <button class="modal-close" onclick="closeModal('deceasedModal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Cause of Death</label>
                <select id="recordType" class="form-control">
                    <option value="Illness">Illness / Disease</option>
                    <option value="Euthanasia">Euthanasia</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Remarks</label>
                <textarea id="recordRemarks" class="form-control" rows="4" placeholder="Enter remarks..."></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeModal('deceasedModal')">Cancel</button>
            <button class="btn btn-danger" onclick="confirmDeceased()">Confirm</button>
        </div>
    </div>
</div>

<script>
function openModal(id){ document.getElementById(id).classList.add('open'); }
function closeModal(id){ document.getElementById(id).classList.remove('open'); }
document.addEventListener('click', e => { if (e.target.classList.contains('modal-backdrop')) e.target.classList.remove('open'); });

function switchTab(tab){
    document.querySelectorAll('.tabs[data-tg="pound"] .tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    document.querySelectorAll('.tab-pane[data-tab]').forEach(p => p.classList.toggle('active', p.dataset.tab === tab));
}

let currentPetId = 0;

function openPet(id){
    currentPetId = id;
    fetch("admin_pages/view_pet.php?id=" + id)
    .then(r => r.text())
    .then(html => {
        document.getElementById("petModalBody").innerHTML = html;
        openModal("petModal");
    });
}

function saveImpoundedPet(){
    let form = document.getElementById("addPetForm");
    let data = new FormData(form);
    fetch("admin_pages/save_impounded_pet.php", { method: "POST", body: data })
    .then(r => r.text())
    .then(result => {
        result = result.trim();
        if(result === "success"){
            alert("Pet added successfully.");
            closeModal("addPetModal");
            location.reload();
        } else {
            alert(result);
        }
    });
}

function paymentPet(){
    closeModal("petModal");
    fetch("admin_pages/payment_pet.php?id=" + currentPetId)
    .then(r => r.text())
    .then(html => {
        document.getElementById("paymentContent").innerHTML = html;
        openModal("paymentModal");
    });
}

function savePayment(){
    let form = document.getElementById("paymentForm");
    if(!form){ alert("Payment form not found."); return; }
    let data = new FormData(form);
    data.append("save", "1");
    fetch("admin_pages/payment_pet.php?id=" + currentPetId, { method: "POST", body: data })
    .then(res => res.text())
    .then(result => {
        result = result.trim();
        if(result === "success"){
            alert("Payment recorded successfully.");
            closeModal("paymentModal");
            location.reload();
        } else {
            alert(result);
        }
    })
    .catch(err => { console.log(err); alert("Error saving payment."); });
}

function viewPaymentHistory(){
    window.open("admin_pages/payment_details.php?id=" + currentPetId, "_blank");
}

function updatePetStatus(){
    let status = document.getElementById("statusSelect").value;
    fetch("admin_pages/update_pet_status.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + currentPetId + "&status=" + encodeURIComponent(status)
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            alert("Status updated to " + data.new_status + ".");
            closeModal("petModal");
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function claimPet(){
    if(!confirm("Return this pet to the owner?")) return;
    fetch("admin_pages/claim_pet.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + currentPetId
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            alert("Pet successfully claimed.");
            closeModal("petModal");
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function postForAdoption(){
    if(!confirm("Post this pet for adoption?")) return;
    fetch("admin_pages/post_pet_for_adoption.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + currentPetId
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            alert("Pet posted for adoption.");
            closeModal("petModal");
            location.reload();
        } else {
            alert(data.message);
        }
    });
}

function openDeceasedModal(){
    document.getElementById("recordType").value = "Illness";
    document.getElementById("recordRemarks").value = "";
    openModal("deceasedModal");
}

function confirmDeceased(){
    let type = document.getElementById("recordType").value;
    let remarks = document.getElementById("recordRemarks").value;
    fetch("admin_pages/mark_pet_deceased.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "id=" + currentPetId + "&record_type=" + encodeURIComponent(type) + "&remarks=" + encodeURIComponent(remarks)
    })
    .then(r => r.json())
    .then(data => {
        if(data.success){
            alert("Pet marked as deceased.");
            closeModal("deceasedModal");
            closeModal("petModal");
            location.reload();
        } else {
            alert(data.message);
        }
    });
}
</script>
</body>
</html>
