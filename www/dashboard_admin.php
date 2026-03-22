<?php
// Admin Dashboard - GGHMS
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'admin') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$name = $user['user_metadata']['name'] ?? 'Admin';
$sb = new Supabase();

// 1. Fetch Staff, Patients & Guardians (From Profiles Table for DB consistency)
$profilesRes = $sb->request('GET', '/rest/v1/profiles?select=*', null, true);
$staffMembers = [];
$patientList = [];
$guardianList = [];
$profilesMap = []; // global map for ID -> Name lookups
$totalPatients = 0;
if ($profilesRes['status'] === 200) {
    foreach ($profilesRes['data'] as $u) {
        $profilesMap[$u['id']] = $u['name'] ?? 'Unknown';
        $r = strtolower($u['role'] ?? 'patient');
        if (in_array($r, ['doctor', 'nurse', 'pharmacist', 'technician', 'admin'])) {
            $staffMembers[] = [
                'id' => $u['id'], 
                'name' => $u['name'] ?? 'Unknown', 
                'role' => $r, 
                'department' => $u['department'] ?? 'General OPD', 
                'email' => $u['email'] ?? '', 
                'status' => 'Active'
            ];
        } elseif ($r === 'guardian') {
            $guardianList[] = [
                'id' => $u['id'],
                'name' => $u['name'] ?? 'Guest',
                'email' => $u['email'] ?? '',
                'joined' => $u['created_at']
            ];
        } else {
            $patientList[] = [
                'id' => $u['id'],
                'name' => $u['name'] ?? 'Guest',
                'email' => $u['email'] ?? '',
                'joined' => $u['created_at']
            ];
            $totalPatients++;
        }
    }
}

// 2. Fetch Analytics (Use service key for total system visibility)
$apptCountRes = $sb->request('GET', '/rest/v1/appointments?select=id', null, true);
$totalAppointments = ($apptCountRes['status'] === 200) ? count($apptCountRes['data']) : 0;

$emergenciesRes = $sb->request('GET', '/rest/v1/emergencies?order=created_at.desc');
$emergencies = ($emergenciesRes['status'] === 200) ? $emergenciesRes['data'] : [];

$auditRes = $sb->request('GET', '/rest/v1/audit_log?order=created_at.desc&limit=20');
$auditLogs = ($auditRes['status'] === 200) ? $auditRes['data'] : [];

// 3. Fetch Guardian Links
$guardianLinksRes = $sb->request('GET', '/rest/v1/guardians?select=*,patient:patient_id(name),guardian:guardian_id(name)');
$guardianLinks = ($guardianLinksRes['status'] === 200) ? $guardianLinksRes['data'] : [];

// Map patient_id -> guardian info for easy lookup in table
$patientGuardians = [];
foreach($guardianLinks as $link) {
    $guardianName = $profilesMap[$link['guardian_id']] ?? $link['guardian']['name'] ?? 'Unknown';
    $patientGuardians[$link['patient_id']][] = [
        'link_id' => $link['id'],
        'id' => $link['guardian_id'],
        'name' => $guardianName,
        'relationship' => $link['relationship'],
        'status' => $link['status'] ?? 'pending'
    ];
}

// 4. Fetch All Guardians for the Select Dropdown
$guardiansRes = $sb->request('GET', '/rest/v1/profiles?role=eq.guardian&select=id,name');
$allGuardians = ($guardiansRes['status'] === 200) ? $guardiansRes['data'] : [];

// 5. Fetch All Scheduled Appointments for Management
$appointmentsRes = $sb->request('GET', '/rest/v1/appointments?status=eq.scheduled&select=*,patient:patient_id(name),assigned_to:assigned_to(name)&order=appointment_date.asc', null, true);
$allAppointments = ($appointmentsRes['status'] === 200) ? $appointmentsRes['data'] : [];

// Emergency Status Calc
$highSeverityCount = 0;
foreach($emergencies as $e) if(($e['severity'] ?? '') === 'high' && ($e['status'] ?? '') !== 'resolved') $highSeverityCount++;

// 6. Fetch Ward Data for Bed Management
$wardsRes = $sb->request('GET', '/rest/v1/wards?select=*&order=ward_name.asc', null, true);
$wards = ($wardsRes['status'] === 200) ? $wardsRes['data'] : [];

// 7. Fetch Billing & Invoices for Admin
$allInvoicesRes = $sb->request('GET', '/rest/v1/invoices?select=*,patient:patient_id(name)&order=created_at.desc', null, true);
$allInvoices = ($allInvoicesRes['status'] === 200) ? $allInvoicesRes['data'] : [];

$totalRevenue = 0;
foreach($allInvoices as $inv) {
    if($inv['status'] === 'paid') $totalRevenue += (float)$inv['total_amount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .nav-link-custom { display: flex; align-items: center; padding: 12px 20px; color: #64748b; text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s; }
        .nav-link-custom:hover, .nav-link-custom.active { background: var(--primary-soft); color: var(--primary-color); }
        .nav-link-custom i { margin-right: 12px; font-size: 1.2rem; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="sidebar p-4">
        <div class="d-flex align-items-center mb-5">
            <div class="bg-primary rounded-circle me-2" style="width: 32px; height: 32px;"></div>
            <h4 class="fw-bold mb-0 text-secondary">GGHMS</h4>
        </div>

        <nav id="sidebarMenu">
            <a href="#" class="nav-link-custom active" data-target="section-analytics"><i class="bi bi-speedometer2"></i> Analytics</a>
            <a href="#" class="nav-link-custom" data-target="section-staff"><i class="bi bi-people"></i> Staff Management</a>
            <a href="#" class="nav-link-custom" data-target="section-patients"><i class="bi bi-person-badge"></i> Patient Directory</a>
            <a href="#" class="nav-link-custom" data-target="section-guardians"><i class="bi bi-shield-check"></i> Guardian Management</a>
            <a href="#" class="nav-link-custom" data-target="section-emergencies"><i class="bi bi-exclamation-octagon"></i> Emergency Queue</a>
            <a href="#" class="nav-link-custom" data-target="section-appointments"><i class="bi bi-calendar-check"></i> Appointments</a>
            <a href="#" class="nav-link-custom" data-target="section-audit"><i class="bi bi-journal-text"></i> Audit Logs</a>
            <a href="#" class="nav-link-custom" data-target="section-beds"><i class="bi bi-hospital"></i> Bed Management</a>
            <a href="#" class="nav-link-custom" data-target="section-billing"><i class="bi bi-credit-card-2-front"></i> Billing & Payments</a>
            <hr class="my-3">
            <div class="px-2 mb-3">
                <button class="btn btn-primary-soft text-primary w-100 rounded-pill d-flex align-items-center justify-content-center py-2" data-bs-toggle="modal" data-bs-target="#searchModal">
                    <i class="bi bi-search me-2"></i> Find Patient
                </button>
            </div>
            <hr class="my-4">
            <a href="/" class="nav-link-custom"><i class="bi bi-house"></i> Back to Home</a>
            <a href="/api/auth/logout.php" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <!-- Mobile Header -->
        <div class="d-flex d-lg-none align-items-center mb-4 pb-3 border-bottom">
            <button class="btn btn-light bg-white border-0 rounded-circle shadow-sm p-2 me-3" onclick="toggleSidebar()">
                <i class="bi bi-list fs-4 text-primary"></i>
            </button>
            <h4 class="fw-bold mb-0 text-primary">GGHMS</h4>
        </div>

        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Administrative Center</h2>
                <p class="text-muted mb-0">Overview of Ghanaian General Hospital operations.</p>
            </div>
            <div class="d-flex align-items-center">
                 <div class="me-4 text-end">
                    <p class="mb-0 fw-bold">Hospital Status</p>
                    <span class="badge bg-success-soft text-success rounded-pill px-3">Normal Operations</span>
                </div>
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- ANALYTICS SECTION -->
        <div id="section-analytics" class="dashboard-section">
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card p-4 border-0 shadow-sm">
                        <h6 class="text-muted mb-3">Total Patients</h6>
                        <h2 class="fw-bold mb-1 text-primary"><?php echo number_format($totalPatients); ?></h2>
                        <small class="text-success"><i class="bi bi-shield-check"></i> Registered</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-4 border-0 shadow-sm">
                        <h6 class="text-muted mb-3">Today's Visits</h6>
                        <h2 class="fw-bold mb-1 text-warning"><?php echo number_format($totalAppointments); ?></h2>
                        <small class="text-muted">Total Scheduled</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-4 border-0 shadow-sm">
                        <h6 class="text-muted mb-3">Live Emergencies</h6>
                        <h2 class="fw-bold mb-1 text-danger"><?php echo sprintf('%02d', $highSeverityCount); ?></h2>
                        <small class="text-danger small">High Severity Active</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-4 border-0 shadow-sm">
                        <h6 class="text-muted mb-3">Staff Active</h6>
                        <h2 class="fw-bold mb-1 text-info"><?php echo count($staffMembers); ?></h2>
                        <small class="text-success small">System-wide</small>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card p-4 border-0 shadow-sm h-100">
                        <h5 class="fw-bold mb-4">Department Performance</h5>
                        <div class="table-responsive">
                             <table class="table table-hover align-middle">
                                <thead class="table-light border-0">
                                    <tr>
                                        <th class="border-0">Department</th>
                                        <th class="border-0">Patients</th>
                                        <th class="border-0">Avg Visit</th>
                                        <th class="border-0">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><span class="fw-bold">General OPD</span></td>
                                        <td>450</td>
                                        <td>1.2 hrs</td>
                                        <td><span class="badge bg-success-soft text-success rounded-pill px-3">High</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="fw-bold">Maternity</span></td>
                                        <td>120</td>
                                        <td>4.5 days</td>
                                        <td><span class="badge bg-primary-soft text-primary rounded-pill px-3">Stable</span></td>
                                    </tr>
                                    <tr>
                                        <td><span class="fw-bold">Pharmacy</span></td>
                                        <td>890</td>
                                        <td>15 min</td>
                                        <td><span class="badge bg-danger-soft text-danger rounded-pill px-3">Overloaded</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                     <div class="card p-4 border-0 shadow-sm h-100">
                        <h5 class="fw-bold mb-4">Urgent Alerts</h5>
                        <?php if (empty($emergencies)): ?>
                            <p class="text-muted small">No active emergency alerts.</p>
                        <?php else: foreach (array_slice($emergencies, 0, 3) as $e): ?>
                        <div class="alert <?php echo ($e['severity'] === 'high') ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning'; ?> border-0 rounded-4 mb-3">
                            <div class="d-flex">
                                <i class="bi <?php echo ($e['severity'] === 'high') ? 'bi-exclamation-circle-fill' : 'bi-info-circle-fill'; ?> me-2"></i>
                                <div>
                                    <div class="fw-bold"><?php echo htmlspecialchars($e['status']); ?> Emergency</div>
                                    <small><?php echo htmlspecialchars($e['description']); ?></small>
                                    <div class="mt-2 text-decoration-underline small cursor-pointer" onclick="navigateTo('section-emergencies')">Respond</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PATIENT DIRECTORY SECTION -->
        <div id="section-patients" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Registered Patients</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Email</th><th>Guardian</th><th>Patient ID</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($patientList)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No patients registered.</td></tr>
                            <?php else: foreach ($patientList as $p): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($p['name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo htmlspecialchars($p['email'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (isset($patientGuardians[$p['id']])): foreach($patientGuardians[$p['id']] as $g): ?>
                                            <div class="mb-2">
                                                <span class="badge <?php echo ($g['status'] === 'approved' ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'); ?> rounded-pill px-2" title="<?php echo htmlspecialchars($g['relationship']); ?>">
                                                    <i class="bi bi-shield-check me-1"></i><?php echo htmlspecialchars($g['name']); ?> (<?php echo $g['status']; ?>)
                                                </span>
                                                <?php if ($g['status'] === 'pending'): ?>
                                                    <button class="btn btn-xs btn-success py-0 px-2 ms-1" style="font-size: 0.7rem;" onclick="approveLink('<?php echo $g['link_id']; ?>')">Approve</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; else: ?>
                                            <button class="btn btn-sm btn-link text-muted p-0" onclick="openLinkModal('<?php echo $p['id']; ?>', '<?php echo htmlspecialchars($p['name']); ?>')">+ Link</button>
                                        <?php endif; ?>
                                    </td>
                                    <td><small class="text-muted"><?php echo substr($p['id'], 0, 8); ?></small></td>
                                    <td><?php echo date('M d, Y', strtotime($p['joined'])); ?></td>
                                    <td><a href="/emr.php?patient_id=<?php echo $p['id']; ?>" class="btn btn-sm btn-light rounded-pill px-3">View EMR</a></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- GUARDIAN MANAGEMENT SECTION -->
        <div id="section-guardians" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Registered Guardians</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Name</th><th>Email</th><th>Linked Patients</th><th>Joined</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($guardianList)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No guardians registered.</td></tr>
                            <?php else: foreach ($guardianList as $g): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($g['name']); ?></td>
                                    <td><?php echo htmlspecialchars($g['email'] ?: '—'); ?></td>
                                    <td>
                                        <?php 
                                        $links = [];
                                        foreach($guardianLinks as $l) if($l['guardian_id'] === $g['id']) $links[] = $l;
                                        if (empty($links)): ?>
                                            <span class="text-muted small">No patients linked</span>
                                        <?php else: foreach($links as $l): ?>
                                            <div class="mb-1">
                                                <span class="badge bg-light text-dark border rounded-pill px-2">
                                                    <?php echo htmlspecialchars($profilesMap[$l['patient_id']] ?? 'Unknown'); ?> 
                                                    <small class="text-muted">(<?php echo $l['status']; ?>)</small>
                                                </span>
                                                <?php if ($l['status'] === 'pending'): ?>
                                                    <button class="btn btn-xs btn-outline-success py-0 px-2 ms-1" style="font-size: 0.7rem;" onclick="approveLink('<?php echo $l['id']; ?>')">Approve</button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($g['joined'])); ?></td>
                                    <td><button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="openLinkModalDirect('<?php echo $g['id']; ?>', '<?php echo htmlspecialchars($g['name']); ?>')">Link Patient</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STAFF MANAGEMENT SECTION -->
        <div id="section-staff" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Staff Member Directory</h5>
                    <div>
                        <button class="btn btn-outline-primary rounded-pill px-4 me-2" onclick="syncStaffData()"><i class="bi bi-arrow-repeat"></i> Sync Staff Data</button>
                        <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="bi bi-plus-lg"></i> Add New Staff</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light"><tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php if (empty($staffMembers)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No staff members found.</td></tr>
                            <?php else: foreach ($staffMembers as $staff): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td class="text-muted small"><?php echo htmlspecialchars($staff['email'] ?? '—'); ?></td>
                                    <td><span class="text-capitalize badge bg-secondary"><?php echo htmlspecialchars($staff['role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($staff['status']); ?></span></td>
                                    <td><button class="btn btn-sm btn-outline-secondary" onclick="editStaff('<?php echo base64_encode(json_encode($staff)); ?>')">Edit</button></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- APPOINTMENT MANAGEMENT SECTION -->
        <div id="section-appointments" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Patient Appointment Management</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Date</th><th>Patient</th><th>Department</th><th>Reason</th><th>Assigned To</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allAppointments)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No scheduled appointments to manage.</td></tr>
                            <?php endif; foreach($allAppointments as $a): ?>
                                <tr>
                                    <td><?php echo date('M d, H:i', strtotime($a['appointment_date'])); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($a['patient']['name'] ?? 'Guest'); ?></td>
                                    <td><span class="badge bg-info-soft text-info rounded-pill px-3"><?php echo htmlspecialchars($a['department'] ?? 'General'); ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars($a['reason']); ?></td>
                                    <td>
                                        <?php if(isset($a['assigned_to'])): ?>
                                            <span class="text-primary fw-bold"><i class="bi bi-person-check-fill me-1"></i><?php echo htmlspecialchars($a['assigned_to']['name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-primary rounded-pill px-3" onclick="openAssignModal('<?php echo $a['id']; ?>', '<?php echo htmlspecialchars($a['department'] ?? 'General OPD'); ?>')">
                                            <?php echo isset($a['assigned_to']) ? 'Reassign' : 'Assign Staff'; ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- EMERGENCIES SECTION -->
        <div id="section-emergencies" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Live Emergency Response Queue</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Time</th><th>Patient ID</th><th>Location</th><th>Severity</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($emergencies)): ?>
                                <tr><td colspan="6" class="text-muted py-4 text-center">No emergencies reported.</td></tr>
                            <?php endif; foreach($emergencies as $e): ?>
                                <tr>
                                    <td><?php echo date('H:i', strtotime($e['created_at'])); ?></td>
                                    <td><?php echo substr($e['patient_id'], 0, 8); ?></td>
                                    <td><code><?php echo htmlspecialchars($e['location']); ?></code></td>
                                    <td><span class="badge <?php echo ($e['severity'] === 'high') ? 'bg-danger' : 'bg-warning'; ?>-soft text-<?php echo ($e['severity'] === 'high') ? 'danger' : 'warning'; ?> rounded-pill px-3"><?php echo htmlspecialchars($e['severity']); ?></span></td>
                                    <td><span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo htmlspecialchars($e['status']); ?></span></td>
                                    <td><button class="btn btn-sm btn-outline-primary rounded-pill px-3">Assign Team</button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AUDIT LOG SECTION -->
        <div id="section-audit" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">System Audit Logs</h5>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light"><tr><th>Timestamp</th><th>User</th><th>Action</th><th>Detail</th></tr></thead>
                        <tbody>
                            <?php if (empty($auditLogs)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">Records empty.</td></tr>
                            <?php endif; foreach($auditLogs as $log): ?>
                                <tr>
                                    <td class="small text-muted"><?php echo $log['created_at']; ?></td>
                                    <td class="fw-bold"><?php echo substr($log['user_id'], 0, 8); ?></td>
                                    <td><span class="badge bg-secondary-soft text-secondary rounded-pill px-2"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                    <td class="small"><?php echo htmlspecialchars($log['details']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- BED MANAGEMENT SECTION -->
        <div id="section-beds" class="dashboard-section d-none">
            <div class="row g-4">
                <?php foreach ($wards as $w): 
                    $occupied = $w['occupied_beds'];
                    $total = $w['total_beds'];
                    $perc = ($total > 0) ? ($occupied / $total) * 100 : 0;
                    $colorClass = ($perc > 85) ? 'bg-danger' : (($perc > 60) ? 'bg-warning' : 'bg-primary');
                ?>
                <div class="col-md-4 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($w['ward_name']); ?></h6>
                            <span class="badge <?php echo str_replace('bg-', 'text-', $colorClass); ?> bg-light rounded-pill">
                                ₵ <?php echo number_format($w['admission_fee'], 0); ?>/bed
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2 small">
                            <span>Occupancy</span>
                            <span class="fw-bold"><?php echo $occupied; ?> / <?php echo $total; ?></span>
                        </div>
                        <div class="progress mb-3" style="height: 8px;">
                            <div class="progress-bar <?php echo $colorClass; ?>" style="width: <?php echo $perc; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between extra-small text-muted">
                            <span><?php echo $total - $occupied; ?> Beds Available</span>
                            <span><?php echo round($perc); ?>% Full</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- BILLING & PAYMENTS SECTION -->
        <div id="section-billing" class="dashboard-section d-none">
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="card p-4 border-0 shadow-sm bg-primary text-white">
                        <h6 class="opacity-75 mb-3">Total Hospital Revenue</h6>
                        <h2 class="fw-bold mb-1">₵ <?php echo number_format($totalRevenue, 2); ?></h2>
                        <small class="opacity-75">All-time collected</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card p-4 border-0 shadow-sm">
                        <h6 class="text-muted mb-3">Pending Invoices</h6>
                        <?php 
                        $pendingCount = 0; 
                        foreach($allInvoices as $i) if($i['status'] === 'unpaid') $pendingCount++;
                        ?>
                        <h2 class="fw-bold mb-1 text-warning"><?php echo $pendingCount; ?></h2>
                        <small class="text-muted">Awaiting payment</small>
                    </div>
                </div>
            </div>

            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Invoice Ledger</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Date</th>
                                <th>Patient</th>
                                <th>Method</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allInvoices)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No financial records found.</td></tr>
                            <?php endif; foreach($allInvoices as $inv): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($inv['created_at'])); ?></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($inv['patient']['name'] ?? 'Guest'); ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark border rounded-pill px-2">
                                            <?php echo strtoupper($inv['payment_method'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">₵ <?php echo number_format($inv['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($inv['status'] === 'paid') ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'; ?> rounded-pill px-3">
                                            <?php echo htmlspecialchars($inv['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-light rounded-pill px-3">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modals -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Add New Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form action="/api/admin/staff_add" method="POST">
                        <div class="mb-3"><label class="small text-muted">Full Name</label><input type="text" name="name" class="form-control rounded-pill px-3" required></div>
                        <div class="mb-3"><label class="small text-muted">Email Address</label><input type="email" name="email" class="form-control rounded-pill px-3" required></div>
                        <div class="mb-3"><label class="small text-muted">Temp Password</label><input type="password" name="password" class="form-control rounded-pill px-3" required minlength="6"></div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="small text-muted">Role</label><select name="role" class="form-select rounded-pill px-3"><option value="doctor">Doctor</option><option value="nurse">Nurse</option><option value="pharmacist">Pharmacist</option><option value="technician">Technician</option></select></div>
                            <div class="col-6 mb-3">
                                <label class="small text-muted">Department</label>
                                <select name="department" class="form-select rounded-pill px-3" required>
                                    <option value="General OPD">General OPD</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Pharmacy">Pharmacy</option>
                                    <option value="Radiology">Radiology</option>
                                    <option value="Maternity">Maternity</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Surgery">Surgery</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Create Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Edit Staff Profile</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form action="/api/admin/staff_edit" method="POST">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3"><label class="small text-muted">Full Name</label><input type="text" name="name" id="edit_name" class="form-control rounded-pill px-3" required></div>
                        <div class="mb-3"><label class="small text-muted">Email (Read-only)</label><input type="email" id="edit_email" class="form-control rounded-pill px-3" readonly disabled></div>
                        <div class="row">
                            <div class="col-6 mb-3"><label class="small text-muted">Role</label>
                                <select name="role" id="edit_role" class="form-select rounded-pill px-3">
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="technician">Technician</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="small text-muted">Department</label>
                                <select name="department" id="edit_department" class="form-select rounded-pill px-3" required>
                                    <option value="General OPD">General OPD</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="Laboratory">Laboratory</option>
                                    <option value="Pharmacy">Pharmacy</option>
                                    <option value="Radiology">Radiology</option>
                                    <option value="Maternity">Maternity</option>
                                    <option value="Orthopedics">Orthopedics</option>
                                    <option value="Surgery">Surgery</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Link Guardian Modal -->
    <div class="modal fade" id="linkGuardianModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Link Guardian to Patient</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form action="/api/admin/link_guardian" method="POST">
                        <input type="hidden" name="patient_id" id="link_patient_id">
                        <div class="mb-3">
                            <label class="small text-muted">Patient Name</label>
                            <input type="text" id="link_patient_name" class="form-control rounded-pill px-3" readonly disabled>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted">Select Guardian</label>
                            <select name="guardian_id" class="form-select rounded-pill px-3" required>
                                <option value="">-- Choose Guardian --</option>
                                <?php foreach($allGuardians as $g): ?>
                                    <option value="<?php echo $g['id']; ?>"><?php echo htmlspecialchars($g['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted">Relationship (e.g. Father, Mother, Spouse)</label>
                            <input type="text" name="relationship" class="form-control rounded-pill px-3" required placeholder="Mother">
                        </div>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" name="is_primary" value="1" id="linkPrimary" checked>
                            <label class="form-check-label small" for="linkPrimary">Set as Primary Guardian</label>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Create Relationship</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign Staff Modal -->
    <div class="modal fade" id="assignStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Assign Staff to Appointment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form action="/api/admin/assign_appointment.php" method="POST">
                        <input type="hidden" name="appointment_id" id="assign_appt_id">
                        <div class="mb-3">
                            <label class="small text-muted">Department Context</label>
                            <input type="text" id="assign_dept_display" class="form-control rounded-pill px-3" readonly disabled>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted">Select Staff Member</label>
                            <select name="assigned_to" class="form-select rounded-pill px-3" required id="assign_staff_select">
                                <option value="">-- Choose Staff --</option>
                                <?php foreach($staffMembers as $s): ?>
                                    <option value="<?php echo $s['id']; ?>" data-dept="<?php echo htmlspecialchars($s['department']); ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['role']); ?> - <?php echo htmlspecialchars($s['department']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="alert alert-info border-0 rounded-4 small">
                            <i class="bi bi-info-circle me-1"></i> Showing priority to staff in the matching department.
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Confirm Assignment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- PATIENT SEARCH MODAL -->
    <div class="modal fade" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="fw-bold mb-0">Patient Lookup</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="input-group mb-4 shadow-sm rounded-pill overflow-hidden border">
                        <span class="input-group-text bg-white border-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" id="patientSearchInput" class="form-control border-0 py-2" placeholder="Search by name, ID or GH card..." autocomplete="off">
                    </div>
                    <div id="searchResults" class="list-group list-group-flush">
                        <p class="text-center text-muted py-3 small">Start typing to see results...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab Navigation & Search Logic
        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    const targetId = link.getAttribute('data-target');
                    if (targetId) {
                        e.preventDefault();
                        sections.forEach(sec => sec.classList.add('d-none'));
                        const target = document.getElementById(targetId);
                        if (target) target.classList.remove('d-none');
                        links.forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                    }
                });
            });

            // Search Logic
            const searchInput = document.getElementById('patientSearchInput');
            const searchResults = document.getElementById('searchResults');
            let searchTimeout;

            if (searchInput) {
                searchInput.addEventListener('input', (e) => {
                    clearTimeout(searchTimeout);
                    const query = e.target.value;
                    if (query.length < 2) {
                        searchResults.innerHTML = '<p class="text-center text-muted py-3 small">Enter at least 2 characters...</p>';
                        return;
                    }

                    searchTimeout = setTimeout(async () => {
                        searchResults.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
                        try {
                            const res = await fetch(`/api/search_patients?q=${encodeURIComponent(query)}`);
                            const data = await res.json();
                            
                            if (data.length === 0) {
                                searchResults.innerHTML = '<p class="text-center text-muted py-3 small">No patients found matching your search.</p>';
                                return;
                            }

                            searchResults.innerHTML = data.map(p => `
                                <a href="/emr.php?patient_id=${p.id}" class="list-group-item list-group-item-action border-0 rounded-4 mb-2 p-3 d-flex align-items-center bg-light">
                                    <div class="bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center fw-bold" style="width: 40px; height: 40px; min-width: 40px;">
                                        ${p.name.charAt(0)}
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-0">${p.name}</h6>
                                        <small class="text-muted d-block" style="font-size: 0.75rem;">${p.email || 'No email'}</small>
                                        <small class="text-primary extra-small">ID: ${p.id.substring(0, 13)}...</small>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted ms-auto"></i>
                                </a>
                            `).join('');
                        } catch (err) {
                            searchResults.innerHTML = '<p class="text-center text-danger py-3 small">Error searching. Please try again.</p>';
                        }
                    }, 300);
                });
            }
        });

        function editStaff(staffBase64) {
            try {
                const staff = JSON.parse(atob(staffBase64));
                document.getElementById('edit_user_id').value = staff.id;
                document.getElementById('edit_name').value = staff.name;
                document.getElementById('edit_email').value = staff.email;
                document.getElementById('edit_role').value = staff.role;
                document.getElementById('edit_department').value = staff.department;
                
                const editModal = new bootstrap.Modal(document.getElementById('editStaffModal'));
                editModal.show();
            } catch (e) {
                console.error("Error parsing staff data:", e);
                alert("Could not load staff details. Please try again.");
            }
        }

        function openLinkModal(patientId, patientName) {
            document.getElementById('link_patient_id').value = patientId;
            document.getElementById('link_patient_name').value = patientName;
            const linkModal = new bootstrap.Modal(document.getElementById('linkGuardianModal'));
            linkModal.show();
        }

        function openLinkModalDirect(guardianId, guardianName) {
            // Re-use linkGuardianModal but swap fields? Better to have a dedicated one or generic
            // For now, let's just alert that it's coming soon or use the same one logic
            alert("To link " + guardianName + ", please go to the Patient Directory and click '+ Link' next to the patient.");
        }

        async function approveLink(linkId) {
            if (!confirm("Approve this guardian-patient link?")) return;
            const fd = new FormData();
            fd.append('link_id', linkId);
            fd.append('action', 'approve');

            const res = await fetch('/api/admin/approve_guardian.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Link approved successfully!");
                location.reload();
            } else {
                alert("Error: " + data.error);
            }
        }

        function openAssignModal(apptId, department) {
            document.getElementById('assign_appt_id').value = apptId;
            document.getElementById('assign_dept_display').value = department;
            
            // Filter dropdown to show relevant department first (optional UX improvement)
            const select = document.getElementById('assign_staff_select');
            const options = select.options;
            for (let i = 1; i < options.length; i++) {
                const optDept = options[i].getAttribute('data-dept');
                if (optDept === department) {
                    options[i].style.fontWeight = 'bold';
                    options[i].text = "⭐ " + options[i].text.replace("⭐ ", "");
                }
            }

            const assignModal = new bootstrap.Modal(document.getElementById('assignStaffModal'));
            assignModal.show();
        }

        async function syncStaffData() {
            if (!confirm("This will scan for missing staff profiles and restore them. Continue?")) return;
            
            const btn = event.currentTarget;
            const originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Syncing...';

            try {
                const response = await fetch('/api/admin/reconcile_staff.php');
                const result = await response.json();
                
                if (result.success) {
                    let logSummary = result.logs ? "\n\nDetails:\n" + result.logs.slice(0, 10).join("\n") : "";
                    if (result.logs && result.logs.length > 10) logSummary += "\n...and more.";
                    
                    alert(`Sync Complete!\nReconciled: ${result.reconciled_count}\nSkipped: ${result.skipped_count}\nTotal Checked: ${result.total_checked}${logSummary}`);
                    window.location.reload();
                } else {
                    alert("Sync failed: " + (result.error || "Unknown error"));
                }
            } catch (e) {
                console.error(e);
                alert("An error occurred during synchronization.");
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        }
        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.sidebar-overlay').classList.toggle('show');
        }

        // Auto-close sidebar on mobile link click
        document.querySelectorAll('.nav-link-custom').forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    document.querySelector('.sidebar').classList.remove('show');
                    document.querySelector('.sidebar-overlay').classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
