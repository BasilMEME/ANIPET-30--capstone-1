<?php
require_permission($conn, 'generate_reports');

// Summary stats always shown at top
$totalAdoptions   = (int)($conn->query("SELECT COUNT(*) FROM adoption_applications WHERE status IN('approved','for_releasing','ready_pickup','completed')")->fetch_row()[0] ?? 0);
$totalApps        = (int)($conn->query("SELECT COUNT(*) FROM adoption_applications")->fetch_row()[0] ?? 0);
$totalAppointments= (int)($conn->query("SELECT COUNT(*) FROM appointments")->fetch_row()[0] ?? 0);
$totalPets        = (int)($conn->query("SELECT COUNT(*) FROM pets")->fetch_row()[0] ?? 0);
$availablePets    = (int)($conn->query("SELECT COUNT(*) FROM pets WHERE status='available'")->fetch_row()[0] ?? 0);
?>

<!-- ══ SUMMARY STATS ═══════════════════════════════════════════════════ -->
<div class="stats-grid" style="margin-bottom:22px;">
    <div class="stat-card">
        <div class="stat-icon" style="background:#d1fae5;">✅</div>
        <div class="stat-body"><div class="stat-value"><?php echo $totalAdoptions; ?></div><div class="stat-label">Successful Adoptions</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#dbeafe;">📋</div>
        <div class="stat-body"><div class="stat-value"><?php echo $totalApps; ?></div><div class="stat-label">Total Applications</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#e0e7ff;">📅</div>
        <div class="stat-body"><div class="stat-value"><?php echo $totalAppointments; ?></div><div class="stat-label">Total Appointments</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background:#fef3c7;">🐾</div>
        <div class="stat-body"><div class="stat-value"><?php echo $totalPets; ?></div><div class="stat-label">Pets (<?php echo $availablePets; ?> available)</div></div>
    </div>
</div>

<!-- ══ REPORT CARDS ════════════════════════════════════════════════════ -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:18px;margin-bottom:24px;">

    <!-- Adoption Report -->
    <div class="card" style="margin-bottom:0;border-left:4px solid var(--accent);">
        <div class="card-title" style="margin-bottom:8px;">📋 Adoption Report</div>
        <p style="font-size:.83rem;color:var(--text-light);margin-bottom:14px;">Applications, approvals, and completed adoptions.</p>
        <div class="form-group">
            <label class="form-label">Date From</label>
            <input type="date" id="adopt_from" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label">Date To</label>
            <input type="date" id="adopt_to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <button class="btn btn-primary w-full" onclick="generateReport('adoption')">Generate Report</button>
    </div>

    <!-- Appointment Report -->
    <div class="card" style="margin-bottom:0;border-left:4px solid var(--success);">
        <div class="card-title" style="margin-bottom:8px;">📅 Appointment Report</div>
        <p style="font-size:.83rem;color:var(--text-light);margin-bottom:14px;">Scheduled, approved, and canceled appointments.</p>
        <div class="form-group">
            <label class="form-label">Date From</label>
            <input type="date" id="apt_from" class="form-control">
        </div>
        <div class="form-group">
            <label class="form-label">Date To</label>
            <input type="date" id="apt_to" class="form-control" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <button class="btn btn-success w-full" onclick="generateReport('appointment')">Generate Report</button>
    </div>

    <!-- Inventory Report -->
    <div class="card" style="margin-bottom:0;border-left:4px solid var(--warning);">
        <div class="card-title" style="margin-bottom:8px;">🐾 Pet Inventory Report</div>
        <p style="font-size:.83rem;color:var(--text-light);margin-bottom:14px;">Current pet inventory and status breakdown.</p>
        <div class="form-group">
            <label class="form-label">Filter by Status</label>
            <select id="inv_status" class="form-control">
                <option value="">All Statuses</option>
                <option value="available">Available</option>
                <option value="reserved">Reserved</option>
                <option value="in_adoption">In Adoption</option>
                <option value="adopted">Adopted</option>
                <option value="under_treatment">Under Treatment</option>
            </select>
        </div>
        <div style="height:16px;"></div>
        <button class="btn btn-warning w-full" onclick="generateReport('inventory')">Generate Report</button>
    </div>

</div>

<!-- ══ REPORT OUTPUT ═══════════════════════════════════════════════════ -->
<div id="reportOutput" style="display:none;">
<div class="card">
    <div class="card-header">
        <div>
            <div class="card-title" id="reportTitle">Report</div>
            <div class="card-sub" id="reportSub"></div>
        </div>
        <div style="display:flex;gap:8px;">
            <button class="btn btn-ghost btn-sm" onclick="printReport()">🖨️ Print</button>
            <button class="btn btn-primary btn-sm" onclick="exportCSV()">⬇️ Export CSV</button>
        </div>
    </div>

    <!-- Summary chips -->
    <div id="reportSummary" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;"></div>

    <!-- Table -->
    <div class="table-wrap">
        <table id="reportTable">
            <thead id="reportHead"></thead>
            <tbody id="reportBody"></tbody>
        </table>
    </div>
</div>
</div>

<!-- Loading indicator -->
<div id="reportLoading" style="display:none;text-align:center;padding:32px;color:var(--text-light);">
    <div style="font-size:1.8rem;margin-bottom:8px;">⏳</div>
    <p>Generating report…</p>
</div>

<script>
let currentReportData = null;
let currentReportType = '';

async function generateReport(type) {
    currentReportType = type;
    document.getElementById('reportOutput').style.display  = 'none';
    document.getElementById('reportLoading').style.display = 'block';

    const params = new URLSearchParams({action:'generate_report', report_type:type});
    if (type==='adoption') {
        params.append('date_from', document.getElementById('adopt_from').value);
        params.append('date_to',   document.getElementById('adopt_to').value);
    } else if (type==='appointment') {
        params.append('date_from', document.getElementById('apt_from').value);
        params.append('date_to',   document.getElementById('apt_to').value);
    } else if (type==='inventory') {
        params.append('status_filter', document.getElementById('inv_status').value);
    }

    try {
        const res  = await fetch('admin_api.php', {method:'POST', body:params});
        const data = await res.json();
        document.getElementById('reportLoading').style.display = 'none';

        if (!data.success) { showToast(data.message,'error'); return; }
        currentReportData = data;
        renderReport(data);
    } catch(e) {
        document.getElementById('reportLoading').style.display = 'none';
        showToast('Failed to generate report','error');
    }
}

function renderReport(data) {
    const titles = {adoption:'Adoption Report', appointment:'Appointment Report', inventory:'Pet Inventory Report'};
    document.getElementById('reportTitle').textContent = titles[data.report_type] || 'Report';
    document.getElementById('reportSub').textContent   = `${data.rows.length} records found — generated ${new Date().toLocaleString()}`;

    // Summary chips
    const s    = data.summary || {};
    const sDiv = document.getElementById('reportSummary');
    sDiv.innerHTML = Object.entries(s).map(([k,v])=>
        `<div style="background:var(--surface-alt);border:1px solid var(--border);padding:8px 14px;border-radius:8px;text-align:center;">
            <strong style="display:block;font-size:1.3rem;">${v}</strong>
            <small style="color:var(--text-light);font-size:.75rem;">${k.replace(/_/g,' ')}</small>
        </div>`
    ).join('');

    // Table headers
    const headers = {
        adoption:    ['#','Applicant','Pet','Breed','Status','Applied','Interview','Notes'],
        appointment: ['#','Title','Client','Email','Pet','Scheduled','Status','Requested'],
        inventory:   ['#','Name','Species','Breed','Age','Gender','Status','Health','Added'],
    };
    const cols = headers[data.report_type] || [];
    document.getElementById('reportHead').innerHTML = '<tr>'+cols.map(c=>`<th>${c}</th>`).join('')+'</tr>';

    // Table rows
    let bodyHtml = '';
    data.rows.forEach((r,i)=>{
        if (data.report_type==='adoption') {
            bodyHtml += `<tr>
                <td style="color:var(--muted);font-size:.8rem;">${i+1}</td>
                <td style="font-weight:600;">${escH(r.applicant_name)}</td>
                <td>${escH(r.pet_name||'—')}</td>
                <td>${escH(r.pet_breed||'—')}</td>
                <td><span class="badge badge-${r.status}">${r.status.replace(/_/g,' ')}</span></td>
                <td style="font-size:.82rem;">${r.applied_date||'—'}</td>
                <td style="font-size:.82rem;">${r.interview_date||'—'}</td>
                <td style="font-size:.78rem;max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${escH(r.admin_notes||'—')}</td>
            </tr>`;
        } else if (data.report_type==='appointment') {
            bodyHtml += `<tr>
                <td style="color:var(--muted);font-size:.8rem;">${i+1}</td>
                <td style="font-weight:600;">${escH(r.title)}</td>
                <td>${escH(r.client_name||'—')}</td>
                <td style="font-size:.82rem;">${escH(r.client_email||'—')}</td>
                <td>${escH(r.pet_name||'—')}</td>
                <td style="font-size:.82rem;">${r.scheduled_at||'—'}</td>
                <td><span class="badge badge-${r.status}">${r.status}</span></td>
                <td style="font-size:.82rem;">${r.created_at||'—'}</td>
            </tr>`;
        } else if (data.report_type==='inventory') {
            bodyHtml += `<tr>
                <td style="color:var(--muted);font-size:.8rem;">${i+1}</td>
                <td style="font-weight:600;">${escH(r.name)}</td>
                <td>${escH(r.species||'—')}</td>
                <td>${escH(r.breed||'—')}</td>
                <td>${escH(r.age||'—')}</td>
                <td>${escH(r.gender||'—')}</td>
                <td><span class="badge badge-${r.status}">${r.status.replace(/_/g,' ')}</span></td>
                <td style="font-size:.82rem;">${escH(r.health_status||'—')}</td>
                <td style="font-size:.82rem;">${r.created_at||'—'}</td>
            </tr>`;
        }
    });

    if (!bodyHtml) bodyHtml = `<tr><td colspan="${cols.length}" style="text-align:center;padding:24px;color:var(--muted);">No records found for selected criteria.</td></tr>`;
    document.getElementById('reportBody').innerHTML = bodyHtml;
    document.getElementById('reportOutput').style.display = 'block';
    document.getElementById('reportOutput').scrollIntoView({behavior:'smooth',block:'start'});
}

function escH(s){ const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

function printReport() {
    window.print();
}

function exportCSV() {
    if (!currentReportData) return;
    const rows   = currentReportData.rows;
    const type   = currentReportData.report_type;
    if (!rows.length) { showToast('No data to export','warning'); return; }

    const keys   = Object.keys(rows[0]);
    let csv      = keys.join(',') + '\n';
    rows.forEach(r => {
        csv += keys.map(k => '"' + String(r[k]||'').replace(/"/g,'""') + '"').join(',') + '\n';
    });

    const blob = new Blob([csv], {type:'text/csv'});
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = type + '_report_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}
</script>

<style>
@media print {
    .sidebar, .topbar, .stats-grid, [style*="border-left"], .btn, #reportSummary + div + div .btn { display:none !important; }
    .main { margin-left:0; }
    #reportOutput { display:block !important; }
    .card { box-shadow:none !important; border:none !important; }
}
</style>
