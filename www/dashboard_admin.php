<?php
// Admin Dashboard - Kobby Moore Hospital
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
// Fetch Drugs for Emergency Prescription
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];
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

$emergenciesRes = $sb->request('GET', '/rest/v1/emergencies?status=in.(active,pending,assigned)&select=*,reporter_id,assigned_to&order=created_at.desc', null, true);
$emergencies = ($emergenciesRes['status'] === 200) ? $emergenciesRes['data'] : [];
$emergError = ($emergenciesRes['status'] !== 200) ? ($emergenciesRes['error'] ?? 'Unknown Error') : null;

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

// 8. Fetch Inventory for Admin
$inventoryRes = $sb->request('GET', '/rest/v1/drug_inventory?select=*&order=drug_name.asc', null, true);
$inventory = ($inventoryRes['status'] === 200) ? $inventoryRes['data'] : [];

// 9. Fetch Active Admissions for Bed Management
$admissionsRes = $sb->request('GET', '/rest/v1/admissions?status=eq.active&select=*,patient:patient_id(name),ward:ward_id(ward_name)', null, true);
$activeAdmissions = ($admissionsRes['status'] === 200) ? $admissionsRes['data'] : [];

// 10. Fetch Pending Admission Recommendations from NOTIFICATIONS (Single Source of Truth)
$pendingAdmissions = [];
$pendingNotifsRes = $sb->request('GET', '/rest/v1/notifications?type=eq.admission_recommendation&select=*&order=created_at.desc', null, true);
if ($pendingNotifsRes['status'] === 200) {
    foreach ($pendingNotifsRes['data'] as $notif) {
        $pId = $notif['related_id'];
        $pRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $pId . '&select=name', null, true);
        $pName = ($pRes['status'] === 200 && !empty($pRes['data'])) ? $pRes['data'][0]['name'] : 'Patient';
        
        $pendingAdmissions[] = [
            'id' => $notif['id'],
            'patient_id' => $pId,
            'patient' => ['name' => $pName],
            'message' => $notif['message'],
            'created_at' => $notif['created_at']
        ];
    }
}

$totalRevenue = 0;
foreach($allInvoices as $inv) {
    if($inv['status'] === 'paid') $totalRevenue += (float)$inv['total_amount'];
}
// 12. Fetch Notifications
$notificationsRes = $sb->request('GET', '/rest/v1/notifications?user_id=eq.' . $user['id'] . '&order=created_at.desc&limit=5', null, true);
$notifications = ($notificationsRes['status'] === 200) ? $notificationsRes['data'] : [];
$unreadCount = count(array_filter($notifications, fn($n) => empty($n['is_read'])));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .nav-link-custom { display: flex; align-items: center; padding: 12px 20px; color: #64748b; text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s; }
        .nav-link-custom:hover, .nav-link-custom.active { background: var(--primary-soft); color: var(--primary-color); }
        .nav-link-custom i { margin-right: 12px; font-size: 1.2rem; }
        .transition-all { transition: all 0.4s ease-in-out; }
        .pulse-highlight { animation: pulse-yellow 1.5s infinite; }
        @keyframes pulse-yellow {
            0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
            100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
        }
    </style>
</head>
<body>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="sidebar p-4">
        <div class="d-flex align-items-center mb-5">
            <img src="/assets/img/logo.png" alt="KM Logo" style="width: 36px; height: 36px; object-fit: contain;" class="me-2 rounded-3 shadow-sm">
            <h4 class="fw-bold mb-0 text-secondary">Kobby Moore Hospital</h4>
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
            <a href="#" class="nav-link-custom" data-target="section-inventory"><i class="bi bi-box-seam"></i> Inventory</a>
            <a href="#" class="nav-link-custom" data-target="section-reports"><i class="bi bi-bar-chart-line"></i> Reports & Analytics</a>
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
            <h4 class="fw-bold mb-0 text-primary">Kobby Moore Hospital</h4>
        </div>

        <div id="dashboard-global-header">
            <header class="d-flex justify-content-between align-items-center mb-5">
                <div>
                    <h2 class="fw-bold mb-1">Administrative Center</h2>
                    <p class="text-muted mb-0">Overview of Kobby Moore Hospital operations.</p>
                </div>
                <div class="d-flex align-items-center">
                    <div class="dropdown me-4">
                        <button class="btn btn-light bg-white border-0 rounded-circle shadow-sm position-relative p-2" data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5 text-secondary"></i>
                            <?php if ($unreadCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger top-notif-badge" style="padding: 0.35em 0.5em;">
                                <?php echo $unreadCount; ?>
                            </span>
                            <?php endif; ?>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-3 rounded-4" style="width: 320px;">
                            <h6 class="fw-bold mb-3">System Alerts</h6>
                            <?php if (empty($notifications)): ?>
                                <p class="text-muted small mb-0">No alerts at this time.</p>
                            <?php else: foreach ($notifications as $n): 
                                $msg = $n['message'];
                                foreach($profilesMap as $pid => $pname) {
                                    $msg = str_replace($pid, $pname, $msg);
                                }
                            ?>
                                <div class="p-2 mb-2 rounded-3 <?php echo empty($n['is_read']) ? 'bg-light' : ''; ?>" 
                                     onclick="<?php echo empty($n['is_read']) ? 'markNotificationRead(this, \''.$n['id'].'\')' : ''; ?>"
                                     style="<?php echo empty($n['is_read']) ? 'cursor: pointer;' : ''; ?>">
                                    <p class="mb-1 small <?php echo empty($n['is_read']) ? 'fw-bold text-dark' : 'text-muted'; ?>">
                                        <?php echo htmlspecialchars($msg); ?>
                                    </p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted extra-small"><?php echo date('H:i', strtotime($n['created_at'])); ?></small>
                                        <?php if (($n['type'] ?? '') === 'emergency_handled_by_staff'): ?>
                                            <button class="btn btn-success btn-xs py-0 px-2 rounded-pill fw-bold extra-small"
                                                    onclick="event.stopPropagation(); clearEmergencyTask('<?php echo $n['id']; ?>', this)">
                                                Clear
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                     <div class="me-4 text-end">
                        <p class="mb-0 fw-bold">Hospital Status</p>
                        <span class="badge bg-success-soft text-success rounded-pill px-3">Normal Operations</span>
                    </div>
                    <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                        <?php echo strtoupper(substr($name, 0, 1)); ?>
                    </div>
                </div>
            </header>

            <?php include 'components/health_tips.php'; ?>
        </div>

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
                                    <div class="fw-bold"><?php echo htmlspecialchars($e['status'] ?? 'Active'); ?> Emergency</div>
                                    <small><?php echo htmlspecialchars($e['symptoms'] ?? 'No symptoms reported'); ?></small>
                                     <button class="btn btn-sm btn-white shadow-sm rounded-pill mt-3 px-3 fw-bold border-0" onclick="respondToEmergency('<?php echo $e['id']; ?>')">
                                        <i class="bi bi-arrow-right-circle me-1"></i> Respond
                                    </button>
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
                                                <button class="btn btn-xs btn-outline-danger py-0 px-2 ms-1" style="font-size: 0.7rem;" onclick="removeGuardianLink('<?php echo $l['id']; ?>')"><i class="bi bi-x"></i> Unlink</button>
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
            <div class="card p-4 border-0 shadow-sm overflow-hidden">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Emergency Response Queue</h5>
                    <div class="d-flex align-items-center gap-3">
                        <?php if ($emergError): ?>
                            <span class="badge bg-danger rounded-pill px-3" title="<?php echo htmlspecialchars($emergError); ?>">Fetch Error: Check console</span>
                        <?php endif; ?>
                        <span class="badge bg-danger-soft text-danger rounded-pill px-3"><?php echo $highSeverityCount; ?> High Priority Active</span>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Reported</th>
                                <th>Patient/Reporter</th>
                                <th>Location (GPS)</th>
                                <th>Severity/Symptoms</th>
                                <th>Assigned Staff</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($emergencies)): ?>
                                <tr><td colspan="7" class="text-muted py-5 text-center">No emergencies currently active in the queue.</td></tr>
                            <?php endif; foreach($emergencies as $e): ?>
                                <tr id="emerg-row-<?php echo $e['id']; ?>" class="transition-all">
                                    <td>
                                        <div class="fw-bold"><?php echo date('H:i', strtotime($e['created_at'])); ?></div>
                                        <small class="text-muted extra-small"><?php echo date('M d, Y', strtotime($e['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-primary"><?php echo htmlspecialchars($profilesMap[$e['reporter_id']] ?? 'Guest Patient'); ?></div>
                                        <small class="text-muted extra-small">ID: <?php echo substr($e['reporter_id'] ?? 'N/A', 0, 8); ?></small>
                                    </td>
                                    <td>
                                        <a href="https://www.google.com/maps/search/<?php echo urlencode($e['ghana_post_gps'] ?? $e['location'] ?? ''); ?>" target="_blank" class="text-decoration-none">
                                            <code class="bg-light px-2 py-1 rounded text-primary"><?php echo htmlspecialchars($e['ghana_post_gps'] ?? $e['location'] ?? 'N/A'); ?></code>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($e['severity'] === 'high') ? 'bg-danger' : 'bg-warning'; ?>-soft text-<?php echo ($e['severity'] === 'high') ? 'danger' : 'warning'; ?> rounded-pill px-2 mb-1">
                                            <?php echo strtoupper($e['severity']); ?>
                                        </span>
                                        <div class="small text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($e['symptoms'] ?? ''); ?>">
                                            <?php echo htmlspecialchars($e['symptoms'] ?? 'No notes provided'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if(!empty($e['assigned_to'])): ?>
                                            <span class="text-success fw-bold"><i class="bi bi-person-check-fill me-1"></i><?php echo htmlspecialchars($profilesMap[$e['assigned_to']] ?? 'Staff Member'); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted extra-small italic">Awaiting Assignment</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $s = $e['status'] ?? 'pending';
                                        $cls = 'bg-secondary-soft text-secondary';
                                        if($s === 'active') $cls = 'bg-danger text-white';
                                        if($s === 'assigned') $cls = 'bg-warning text-dark';
                                        if($s === 'dispatched') $cls = 'bg-info text-white';
                                        if($s === 'resolved') $cls = 'bg-success text-white';
                                        ?>
                                        <span class="badge <?php echo $cls; ?> rounded-pill px-3 text-capitalize"><?php echo $s; ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group shadow-sm rounded-pill overflow-hidden">
                                            <button class="btn btn-sm btn-light border-end" onclick='openAssignEmergencyModal(<?php echo json_encode($e); ?>)' title="Assign Staff"><i class="bi bi-person-plus text-primary"></i></button>
                                            <button class="btn btn-sm btn-light border-end" onclick='openDispatchEmergencyModal(<?php echo json_encode($e); ?>)' title="Dispatch Help"><i class="bi bi-truck text-danger"></i></button>
                                            <?php if($e['status'] !== 'resolved'): ?>
                                                <button class="btn btn-sm btn-light" onclick="resolveEmergency('<?php echo $e['id']; ?>')" title="Resolve"><i class="bi bi-check-circle text-success"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
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
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Total Capacity</h6>
                        <?php 
                        $totalCap = array_sum(array_column($wards, 'total_beds'));
                        $totalOcc = array_sum(array_column($wards, 'occupied_beds'));
                        ?>
                        <h2 class="fw-bold mb-0 text-primary"><?php echo $totalCap; ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Currently Occupied</h6>
                        <h2 class="fw-bold mb-0 text-danger"><?php echo $totalOcc; ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Available Beds</h6>
                        <h2 class="fw-bold mb-0 text-success"><?php echo $totalCap - $totalOcc; ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Pending Requests</h6>
                        <h2 class="fw-bold mb-0 text-warning"><?php echo count($pendingAdmissions); ?></h2>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold mb-4">Ward Live Occupancy</h5>
            <div class="row g-4 mb-5">
                <?php foreach ($wards as $w): 
                    $occupied = $w['occupied_beds'];
                    $total = $w['total_beds'];
                    $perc = ($total > 0) ? ($occupied / $total) * 100 : 0;
                    $colorClass = ($perc > 85) ? 'bg-danger' : (($perc > 60) ? 'bg-warning' : 'bg-primary');
                ?>
                <div class="col-md-4 mb-2">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($w['ward_name']); ?></h6>
                            <span class="badge <?php echo str_replace('bg-', 'text-', $colorClass); ?> bg-light rounded-pill">
                                ₵ <?php echo number_format($w['admission_fee'], 0); ?>
                            </span>
                        </div>
                        <div class="progress mb-2" style="height: 6px;">
                            <div class="progress-bar <?php echo $colorClass; ?>" style="width: <?php echo $perc; ?>%"></div>
                        </div>
                        <div class="d-flex justify-content-between extra-small text-muted">
                            <span><?php echo $occupied; ?> / <?php echo $total; ?> Beds</span>
                            <span><?php echo round($perc); ?>%</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card p-4 border-0 shadow-sm">
                        <h5 class="fw-bold mb-4">Current Active Admissions</h5>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr><th>Patient</th><th>Ward</th><th>Bed #</th><th>Admitted On</th><th>Actions</th></tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activeAdmissions)): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No active admissions currently.</td></tr>
                                    <?php endif; foreach($activeAdmissions as $adm): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($adm['patient']['name'] ?? 'P-' . substr($adm['patient_id'], 0, 8)); ?></td>
                                            <td><span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo htmlspecialchars($adm['ward']['ward_name'] ?? 'Unknown'); ?></span></td>
                                            <td class="fw-bold"><?php echo htmlspecialchars($adm['bed_number'] ?: 'N/A'); ?></td>
                                            <td class="small"><?php echo date('M d, H:i', strtotime($adm['admission_date'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary rounded-pill px-3 me-2" onclick='openEditAdmissionModal(<?php echo json_encode($adm); ?>)'>Edit</button>
                                                <button class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="dischargePatient('<?php echo $adm['id']; ?>', '<?php echo $adm['ward_id']; ?>')">Discharge</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card p-4 border-0 shadow-sm bg-light">
                        <h5 class="fw-bold mb-4 small text-uppercase text-muted">Pending Recommendations</h5>
                        <?php if (empty($pendingAdmissions)): ?>
                            <p class="text-muted small text-center py-3">No pending recommendations.</p>
                        <?php endif; foreach($pendingAdmissions as $pa): ?>
                            <div class="bg-white p-3 rounded-4 mb-3 shadow-sm border-start border-warning border-4">
                                <h6 class="fw-bold mb-1 small"><?php echo htmlspecialchars($pa['patient']['name'] ?? 'Patient'); ?></h6>
                                <p class="extra-small text-muted mb-2"><?php echo htmlspecialchars($pa['message']); ?></p>
                                <button class="btn btn-warning btn-sm w-100 rounded-pill extra-small fw-bold" onclick="openAssignBedModal('<?php echo $pa['patient_id']; ?>', '<?php echo htmlspecialchars($pa['patient']['name'] ?? 'Patient'); ?>')">Assign Ward & Bed</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
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
                                            <?php 
                                            $method = $inv['payment_method'] ?? '';
                                            if (!$method && str_starts_with($inv['nhis_note'] ?? '', 'PAYMENT_META:')) {
                                                $metaStr = substr($inv['nhis_note'], 13);
                                                $meta = json_decode($metaStr, true);
                                                $method = $meta['method'] ?? 'N/A';
                                            }
                                            echo strtoupper($method ?: 'N/A'); 
                                            ?>
                                        </span>
                                    </td>
                                    <td class="fw-bold">₵ <?php echo number_format($inv['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge <?php echo ($inv['status'] === 'paid') ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'; ?> rounded-pill px-3">
                                            <?php echo htmlspecialchars($inv['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-light rounded-pill px-3" onclick="viewInvoiceDetails('<?php echo $inv['id']; ?>', '<?php echo htmlspecialchars($inv['patient']['name'] ?? 'Guest'); ?>')">View Details</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- INVENTORY MANAGEMENT SECTION -->
        <div id="section-inventory" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Pharmacy Drug Inventory</h5>
                    <button class="btn btn-primary rounded-pill px-4" onclick="openInventoryModal('add')"><i class="bi bi-plus-lg me-2"></i> Add Drug Stock</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr><th>Drug Name</th><th>Category</th><th>Unit Price</th><th>Stock</th><th>Status</th><th>Actions</th></tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">Inventory is empty.</td></tr>
                            <?php endif; foreach($inventory as $drug): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($drug['drug_name']); ?></td>
                                    <td><span class="badge bg-light text-dark border rounded-pill px-2"><?php echo htmlspecialchars($drug['category'] ?: 'General'); ?></span></td>
                                    <td>₵ <?php echo number_format($drug['unit_price'], 2); ?></td>
                                    <td>
                                        <span class="fw-bold <?php echo ($drug['stock_count'] < 10) ? 'text-danger' : ''; ?>">
                                            <?php echo $drug['stock_count']; ?> units
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($drug['stock_count'] <= 0): ?>
                                            <span class="badge bg-danger-soft text-danger">Out of Stock</span>
                                        <?php elseif($drug['stock_count'] < 10): ?>
                                            <span class="badge bg-warning-soft text-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-soft text-success">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick='openInventoryModal("edit", <?php echo json_encode($drug); ?>)'>Update</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AUDIT LOGS SECTION -->
        <div id="section-audit" class="dashboard-section d-none">
            </div>
        </div>

        <!-- REPORTS & ANALYTICS SECTION -->
        <div id="section-reports" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm mb-4">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0"><i class="bi bi-graph-up-arrow me-2 text-primary"></i>Hospital Performance Reports</h5>
                    <div class="d-flex gap-2">
                        <input type="date" id="report_start_date" class="form-control form-control-sm rounded-pill px-3" value="<?php echo date('Y-m-01'); ?>">
                        <input type="date" id="report_end_date" class="form-control form-control-sm rounded-pill px-3" value="<?php echo date('Y-m-d'); ?>">
                        <button class="btn btn-primary btn-sm rounded-pill px-3" onclick="refreshReports()">Generate</button>
                    </div>
                </div>

                <ul class="nav nav-pills mb-4 bg-light p-1 rounded-pill" id="reportTabs" role="tablist">
                    <li class="nav-item flex-fill"><button class="nav-link active rounded-pill w-100" data-bs-toggle="pill" data-bs-target="#tab-inventory-report"><i class="bi bi-box-seam me-1"></i>Inventory &amp; Meds</button></li>
                    <li class="nav-item flex-fill"><button class="nav-link rounded-pill w-100" data-bs-toggle="pill" data-bs-target="#tab-ward-report"><i class="bi bi-hospital me-1"></i>Wards &amp; Admissions</button></li>
                    <li class="nav-item flex-fill"><button class="nav-link rounded-pill w-100" data-bs-toggle="pill" data-bs-target="#tab-financial-report"><i class="bi bi-cash-stack me-1"></i>Financial Summary</button></li>
                </ul>

                <div class="tab-content" id="reportTabContent">
                    <!-- Inventory Report Tab -->
                    <div class="tab-pane fade show active" id="tab-inventory-report">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-3 border rounded-4 bg-white h-100">
                                    <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-trophy-fill text-warning me-1"></i>Most Prescribed Drugs</h6>
                                    <div id="report_most_prescribed" class="report-container"><div class="text-center py-4 text-muted small">Click Generate to load data.</div></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 border rounded-4 bg-white h-100">
                                    <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-person-badge-fill text-primary me-1"></i>Prescriptions per Doctor</h6>
                                    <div id="report_per_doctor" class="report-container"><div class="text-center py-4 text-muted small">Click Generate to load data.</div></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="p-3 border rounded-4 bg-white">
                                    <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-currency-dollar text-success me-1"></i>Drug Revenue Breakdown</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle small">
                                            <thead class="table-light"><tr><th>Medication</th><th class="text-end">Total Revenue (Est.)</th></tr></thead>
                                            <tbody id="report_drug_revenue_table"><tr><td colspan="2" class="text-center text-muted py-3">Click Generate to load data.</td></tr></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Ward Report Tab -->
                    <div class="tab-pane fade" id="tab-ward-report">
                        <div class="row g-4">
                            <div class="col-md-7">
                                <div class="p-3 border rounded-4 bg-white">
                                    <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-bar-chart-fill text-info me-1"></i>Ward Occupancy Overview</h6>
                                    <div id="report_ward_occupancy" class="report-container"></div>
                                </div>
                            </div>
                            <div class="col-md-5">
                                <div class="p-3 border rounded-4 bg-white h-100">
                                    <h6 class="fw-bold mb-3 small text-uppercase text-muted"><i class="bi bi-cash-coin text-success me-1"></i>Admission Revenue by Ward</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle small">
                                            <thead class="table-light"><tr><th>Ward</th><th class="text-end">Revenue</th></tr></thead>
                                            <tbody id="report_ward_revenue_table"><tr><td colspan="2" class="text-center text-muted py-3">Click Generate to load data.</td></tr></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Financial Summary Tab -->
                    <div class="tab-pane fade" id="tab-financial-report">
                        <div class="p-4 rounded-4 mb-4 text-center text-white" style="background: linear-gradient(135deg, #1a237e 0%, #1565c0 100%);">
                            <p class="text-white-50 small mb-1 text-uppercase fw-bold" style="letter-spacing:1px;">Total Estimated Revenue for Period</p>
                            <h1 class="fw-bold mb-0" id="report_total_rev" style="font-size: 2.5rem;">&#8373; 0.00</h1>
                        </div>
                        <div class="row g-4">
                            <div class="col-md-6">
                                <div class="p-4 border rounded-4 bg-white text-center">
                                    <div class="mb-2"><span class="badge rounded-pill px-3 py-2" style="background:rgba(25,135,84,0.12); color:#198754;"><i class="bi bi-capsule-pill me-1"></i>Pharmacy</span></div>
                                    <h2 class="fw-bold text-success mb-0" id="report_med_total">&#8373; 0.00</h2>
                                    <p class="text-muted small mt-1 mb-0">Sales from Dispensed Prescriptions</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-4 border rounded-4 bg-white text-center">
                                    <div class="mb-2"><span class="badge rounded-pill px-3 py-2" style="background:rgba(13,202,240,0.12); color:#0dcaf0;"><i class="bi bi-hospital me-1"></i>Admissions</span></div>
                                    <h2 class="fw-bold text-info mb-0" id="report_ward_total">&#8373; 0.00</h2>
                                    <p class="text-muted small mt-1 mb-0">Revenue from Ward Admission Fees</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Modals -->

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

    <!-- Invoice Details Modal -->
    <div class="modal fade" id="invoiceDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Invoice Breakdown: <span id="detail_patient_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="invoice_items_container">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Management Modal -->
    <div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="invModalTitle">Add Drug Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="/api/admin/manage_inventory.php" method="POST">
                        <input type="hidden" name="action" id="inv_action" value="add">
                        <input type="hidden" name="id" id="inv_id">
                        <div class="mb-3">
                            <label class="small text-muted">Drug Name</label>
                            <input type="text" name="drug_name" id="inv_name" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="small text-muted">Stock Count</label>
                                <input type="number" name="stock_count" id="inv_stock" class="form-control rounded-pill px-3" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="small text-muted">Unit Price (₵)</label>
                                <input type="number" step="0.01" name="unit_price" id="inv_price" class="form-control rounded-pill px-3" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted">Category</label>
                            <select name="category" id="inv_category" class="form-select rounded-pill px-3">
                                <option value="General">General</option>
                                <option value="Antibiotics">Antibiotics</option>
                                <option value="Painkillers">Painkillers</option>
                                <option value="Antimalarials">Antimalarials</option>
                                <option value="Injections">Injections</option>
                                <option value="Surgical Supplies">Surgical Supplies</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Commit Changes</button>
                        <button type="submit" name="action" value="delete" id="inv_delete_btn" class="btn btn-link text-danger w-100 mt-2 d-none">Delete Item</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ASSIGN BED MODAL -->
    <div class="modal fade" id="assignBedModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Assign Bed: <span id="assign_bed_patient_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="assignBedForm">
                        <input type="hidden" name="patient_id" id="assign_bed_patient_id">
                        <div class="mb-3">
                            <label class="small text-muted">Target Ward</label>
                            <select name="ward_id" id="assign_bed_ward_select" class="form-select rounded-pill px-3" required>
                                <option value="">-- Choose Ward --</option>
                                <?php foreach($wards as $w): 
                                    $isFull = ($w['occupied_beds'] >= $w['total_beds']);
                                ?>
                                    <option value="<?php echo $w['id']; ?>" <?php echo $isFull ? 'disabled' : ''; ?>>
                                        <?php echo htmlspecialchars($w['ward_name']); ?> (<?php echo $w['total_beds'] - $w['occupied_beds']; ?> beds free)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted">Bed Number</label>
                            <select name="bed_number" id="assign_bed_number_select" class="form-select rounded-pill px-3" required>
                                <option value="">-- Select Ward First --</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary w-100 rounded-pill mt-3" onclick="submitBedAssignment()">Finalize Admission</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ASSIGN EMERGENCY MODAL -->
    <div class="modal fade" id="assignEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Assign Response Staff</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form id="assignEmergencyForm">
                        <input type="hidden" name="emergency_id" id="assign_emerg_id">
                        <div class="mb-3">
                            <label class="small text-muted">Select Medical Staff</label>
                            <select name="assigned_to" class="form-select rounded-pill px-3" required id="assign_emerg_staff_select">
                                <option value="">-- Choose Staff --</option>
                                <?php foreach($staffMembers as $s): if($s['role'] === 'doctor' || $s['role'] === 'nurse'): ?>
                                    <option value="<?php echo $s['id']; ?>">
                                        <?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['role']); ?>)
                                    </option>
                                <?php endif; endforeach; ?>
                            </select>
                        </div>
                        <p class="extra-small text-muted mb-4"><i class="bi bi-info-circle me-1"></i> The assigned staff will receive an immediate notification to check the emergency details and coordinate response.</p>
                        <button type="button" class="btn btn-primary w-100 rounded-pill" onclick="submitEmergencyAssignment()">Confirm Assignment</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- DISPATCH EMERGENCY MODAL -->
    <div class="modal fade" id="dispatchEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Direct Help Dispatch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form id="dispatchEmergencyForm">
                        <input type="hidden" name="emergency_id" id="dispatch_emerg_id">
                        <div class="mb-3">
                            <label class="small text-muted">Dispatch Type</label>
                            <select name="dispatch_type" class="form-select rounded-pill px-3" required>
                                <option value="ambulance">🚑 Ambulance (Critical Transfer)</option>
                                <option value="team">🚑 Emergency Response Team (On-site Help)</option>
                                <option value="rider">🏍️ Dispatch Rider (Meds/Supplies Only)</option>
                            </select>
                        </div>
                        <div id="riderMedicationSection" class="mb-3 d-none">
                            <h6 class="fw-bold text-primary small mb-3"><i class="bi bi-capsule me-2"></i>Prescribe Medications (for Rider)</h6>
                            <div id="emergency-med-list" class="mb-3">
                                <div class="card bg-light border-0 rounded-4 mb-3 med-item">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="small fw-bold text-muted">Medication #1</span>
                                            <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle remove-med-btn d-none" onclick="this.closest('.med-item').remove()">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                        <div class="row g-2 mb-2">
                                            <div class="col-md-7">
                                                <select name="meds[0][drug_id]" class="form-select form-select-sm rounded-4">
                                                    <option value="">-- Select Drug --</option>
                                                    <?php foreach ($availableDrugs as $drug): ?>
                                                        <option value="<?php echo $drug['id']; ?>" <?php echo ($drug['stock_count'] <= 0 ? 'disabled' : ''); ?>>
                                                            <?php echo htmlspecialchars($drug['drug_name']); ?> (<?php echo $drug['stock_count']; ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-5">
                                                <input type="text" name="meds[0][dosage]" class="form-control form-select-sm rounded-4" placeholder="Dosage">
                                            </div>
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <input type="text" name="meds[0][frequency]" class="form-control form-select-sm rounded-4" placeholder="Freq">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" name="meds[0][duration]" class="form-control form-select-sm rounded-4" placeholder="Duration">
                                            </div>
                                            <div class="col-md-3">
                                                <input type="number" name="meds[0][quantity]" class="form-control form-select-sm rounded-4" value="1" min="1">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary-soft btn-sm w-100 rounded-pill mb-3" onclick="addEmergencyMedication()">
                                <i class="bi bi-plus-circle me-1"></i> Add Medication
                            </button>
                        </div>
                        <div class="mb-4">
                            <label class="small text-muted">Dispatch Notes / Instructions</label>
                            <textarea name="dispatch_notes" class="form-control rounded-4 px-3 py-2 small" rows="2" placeholder="e.g. Bring oxygen tank, or gate code is 1234..."></textarea>
                        </div>
                        <button type="button" class="btn btn-danger w-100 rounded-pill" onclick="submitEmergencyDispatch()">Execute Dispatch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT ADMISSION MODAL -->
    <div class="modal fade" id="editAdmissionModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Edit Admission: <span id="edit_adm_patient_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="editAdmissionForm">
                        <input type="hidden" name="admission_id" id="edit_adm_id">
                        <input type="hidden" name="old_ward_id" id="edit_adm_old_ward">
                        <div class="mb-3">
                            <label class="small text-muted">Ward</label>
                            <select name="ward_id" id="edit_adm_ward_select" class="form-select rounded-pill px-3" required>
                                <?php foreach($wards as $w): ?>
                                    <option value="<?php echo $w['id']; ?>">
                                        <?php echo htmlspecialchars($w['ward_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="small text-muted">Bed Number</label>
                            <select name="bed_number" id="edit_adm_bed_number_select" class="form-select rounded-pill px-3" required>
                                <option value="">-- Select Ward --</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-primary w-100 rounded-pill mt-3" onclick="submitBedUpdate()">Save Changes</button>
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
        async function silentRefresh() {
            try {
                const activeSection = document.querySelector('.dashboard-section:not(.d-none)');
                if (activeSection) {
                    const html = await fetch(location.href).then(r => r.text());
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newSection = doc.getElementById(activeSection.id);
                    if (newSection) activeSection.innerHTML = newSection.innerHTML;
                } else {
                    location.reload();
                }
            } catch (e) { location.reload(); }
        }
    </script>
    <script>
        // Reports Logic
        async function refreshReports() {
            const start = document.getElementById('report_start_date').value;
            const end = document.getElementById('report_end_date').value;
            
            // Fetch Inventory Reports
            fetchReports('inventory', start, end);
            // Fetch Ward Reports
            fetchReports('ward', start, end);
        }

        async function fetchReports(type, start, end) {
            try {
                const res = await fetch(`/api/admin/get_reports.php?type=${type}&start_date=${start}&end_date=${end}`);
                const data = await res.json();
                if (data.success) {
                    if (type === 'inventory') renderInventoryReports(data.report);
                    else renderWardReports(data.report);
                }
            } catch (e) { console.error("Report Error:", e); }
        }

        function renderInventoryReports(r) {
            // Most Prescribed — styled ranked list
            const mp = document.getElementById('report_most_prescribed');
            const mpEntries = Object.entries(r.most_prescribed || {});
            if (mpEntries.length > 0) {
                const maxCount = mpEntries[0][1] || 1;
                const colors = ['text-warning','text-secondary','text-danger'];
                const rankIcons = ['bi-trophy-fill','bi-award-fill','bi-patch-check-fill'];
                let html = '<div class="d-flex flex-column gap-2">';
                mpEntries.forEach(([name, count], i) => {
                    const pct = Math.round((count / maxCount) * 100);
                    const iconClass = rankIcons[i] || 'bi-dot';
                    const colorClass = colors[i] || 'text-primary';
                    html += `
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi ${iconClass} ${colorClass}" style="font-size:1rem; min-width:18px;"></i>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small fw-semibold">${name}</span>
                                    <span class="badge bg-primary-soft text-primary rounded-pill px-2">${count} rx</span>
                                </div>
                                <div class="progress" style="height:5px;"><div class="progress-bar bg-primary" style="width:${pct}%"></div></div>
                            </div>
                        </div>`;
                });
                mp.innerHTML = html + '</div>';
            } else mp.innerHTML = '<div class="text-center py-4 text-muted small">No prescriptions in this period.</div>';

            // Per Doctor — styled ranked list
            const pd = document.getElementById('report_per_doctor');
            const pdEntries = Object.entries(r.per_doctor || {});
            if (pdEntries.length > 0) {
                const maxCount = pdEntries[0][1] || 1;
                let html = '<div class="d-flex flex-column gap-2">';
                pdEntries.forEach(([id, count], i) => {
                    const name = (typeof profilesMap !== 'undefined' && profilesMap[id]) ? profilesMap[id] : 'Dr. ' + id.substring(0,8);
                    const pct = Math.round((count / maxCount) * 100);
                    const initial = name.charAt(0).toUpperCase();
                    html += `
                        <div class="d-flex align-items-center gap-2">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold flex-shrink-0"
                                 style="width:28px;height:28px;font-size:0.75rem;">${initial}</div>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small fw-semibold">${name}</span>
                                    <span class="badge bg-light text-dark border rounded-pill px-2">${count} rx</span>
                                </div>
                                <div class="progress" style="height:5px;"><div class="progress-bar bg-info" style="width:${pct}%"></div></div>
                            </div>
                        </div>`;
                });
                pd.innerHTML = html + '</div>';
            } else pd.innerHTML = '<div class="text-center py-4 text-muted small">No prescriptions in this period.</div>';

            // Revenue Table
            const revTable = document.getElementById('report_drug_revenue_table');
            let revHtml = '';
            let totalMed = 0;
            for (const [name, amt] of Object.entries(r.revenue_per_drug || {})) {
                revHtml += `<tr><td class="fw-semibold">${name}</td><td class="fw-bold text-success text-end">&#8373; ${amt.toFixed(2)}</td></tr>`;
                totalMed += amt;
            }
            revTable.innerHTML = revHtml || '<tr><td colspan="2" class="text-center text-muted py-3">No sales data.</td></tr>';
            document.getElementById('report_med_total').innerText = '₵ ' + totalMed.toLocaleString(undefined, {minimumFractionDigits:2});
            updateTotalRevenue();
        }

        function renderWardReports(r) {
            // Occupancy
            const occ = document.getElementById('report_ward_occupancy');
            let html = '';
            (r.occupancy || []).forEach(w => {
                const perc = (w.total_beds > 0) ? (w.occupied_beds / w.total_beds) * 100 : 0;
                html += `
                    <div class="mb-3">
                        <div class="d-flex justify-content-between extra-small mb-1"><span>${w.ward_name}</span><span>${w.occupied_beds}/${w.total_beds}</span></div>
                        <div class="progress" style="height:6px;"><div class="progress-bar" style="width:${perc}%"></div></div>
                    </div>
                `;
            });
            occ.innerHTML = html || 'No ward data.';

            // Ward Revenue
            const revTable = document.getElementById('report_ward_revenue_table');
            let revHtml = '';
            let totalWard = 0;
            for (const [name, amt] of Object.entries(r.ward_revenue || {})) {
                revHtml += `<tr><td>${name}</td><td class="fw-bold text-info text-end">₵ ${amt.toFixed(2)}</td></tr>`;
                totalWard += amt;
            }
            revTable.innerHTML = revHtml || '<tr><td colspan="2" class="text-center text-muted">No revenue data.</td></tr>';
            document.getElementById('report_ward_total').innerText = '₵ ' + totalWard.toLocaleString(undefined, {minimumFractionDigits:2});
            updateTotalRevenue();
        }

        function updateTotalRevenue() {
            const med = parseFloat(document.getElementById('report_med_total').innerText.replace('₵ ', '').replace(/,/g, '')) || 0;
            const ward = parseFloat(document.getElementById('report_ward_total').innerText.replace('₵ ', '').replace(/,/g, '')) || 0;
            document.getElementById('report_total_rev').innerText = '₵ ' + (med + ward).toLocaleString(undefined, {minimumFractionDigits:2});
        }

        // Bed Dropdown Logic
        async function updateBedDropdown(wardId, selectId, currentBed = '') {
            const select = document.getElementById(selectId);
            if (!wardId) {
                select.innerHTML = '<option value="">-- Select Ward First --</option>';
                return;
            }
            
            select.innerHTML = '<option value="">Loading beds...</option>';
            try {
                const res = await fetch(`/api/admin/get_available_beds.php?ward_id=${wardId}`);
                const data = await res.json();
                if (data.success) {
                    let html = '<option value="">-- Select Bed --</option>';
                    if (currentBed) html += `<option value="${currentBed}" selected>${currentBed} (Current)</option>`;
                    data.data.forEach(b => {
                        if (b.bed_number !== currentBed) {
                            html += `<option value="${b.bed_number}">${b.bed_number}</option>`;
                        }
                    });
                    select.innerHTML = html;
                    if (data.data.length === 0 && !currentBed) {
                        select.innerHTML = '<option value="">No available beds</option>';
                    }
                } else {
                    let msg = data.error || 'Error loading beds';
                    if (msg.includes('not found')) {
                        msg = 'Beds not initialized. <a href=\"/api/admin/init_beds\" target=\"_blank\">Fix now</a>';
                        // Since it's a select, we can't easily put a link, so we'll just show the text and maybe alert admins
                        console.error('Beds table missing. Run /api/admin/init_beds');
                        select.innerHTML = '<option value=\"\">Table missing - see console</option>';
                        alert('Bed data is not initialized. Please run the initialization script at /api/admin/init_beds');
                    } else {
                        select.innerHTML = `<option value=\"\">${msg}</option>`;
                    }
                }
            } catch (e) { select.innerHTML = '<option value="">Error</option>'; }
        }

        // Attach listeners
        document.addEventListener('DOMContentLoaded', () => {
            const adminWardSelect = document.getElementById('assign_bed_ward_select');
            if (adminWardSelect) {
                adminWardSelect.addEventListener('change', (e) => updateBedDropdown(e.target.value, 'assign_bed_number_select'));
            }
            const editWardSelect = document.getElementById('edit_adm_ward_select');
            if (editWardSelect) {
                editWardSelect.addEventListener('change', (e) => updateBedDropdown(e.target.value, 'edit_adm_bed_number_select'));
            }

            // Initial report load if section active
            const reportBtn = document.querySelector('[data-target="section-reports"]');
            if (reportBtn) reportBtn.addEventListener('click', () => { 
                setTimeout(refreshReports, 200); 
            });
        });

        // Tab Navigation & Search Logic
        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            
            const globalHeader = document.getElementById('dashboard-global-header');

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

                        // Hide/show global header & health tips for Reports section
                        if (globalHeader) {
                            if (targetId === 'section-reports') {
                                globalHeader.classList.add('d-none');
                            } else {
                                globalHeader.classList.remove('d-none');
                            }
                        }
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
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        async function removeGuardianLink(linkId) {
            if (!confirm("Are you sure you want to remove this guardian-patient link? This cannot be undone.")) return;
            const res = await fetch('/api/admin/remove_guardian_link.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ link_id: linkId })
            });
            const data = await res.json();
            if (data.success) {
                alert("Link removed successfully.");
                silentRefresh();
            } else {
                alert("Error removing link: " + (data.message || 'Unknown error'));
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

        async function viewInvoiceDetails(id, name) {
            document.getElementById('detail_patient_name').innerText = name;
            const container = document.getElementById('invoice_items_container');
            container.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
            
            const modal = new bootstrap.Modal(document.getElementById('invoiceDetailsModal'));
            modal.show();

            try {
                const res = await fetch(`/api/billing/get_invoice_details.php?id=${id}`);
                const data = await res.json();
                
                if (data.items && data.items.length > 0) {
                    let html = '<div class="list-group list-group-flush">';
                    data.items.forEach(item => {
                        html += `
                            <div class="list-group-item d-flex justify-content-between align-items-center border-0 px-0 py-2">
                                <div>
                                    <h6 class="mb-0 small fw-bold">${item.description}</h6>
                                    <small class="text-muted extra-small">${new Date(item.created_at).toLocaleDateString()}</small>
                                </div>
                                <span class="fw-bold text-primary">₵ ${parseFloat(item.amount).toFixed(2)}</span>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="text-center text-muted py-4">No items found for this invoice.</p>';
                }
            } catch (e) {
                container.innerHTML = '<p class="text-center text-danger py-4">Error loading details.</p>';
            }
        }

        function openInventoryModal(action, drug = null) {
            const modalTitle = document.getElementById('invModalTitle');
            const actionInput = document.getElementById('inv_action');
            const idInput = document.getElementById('inv_id');
            const delBtn = document.getElementById('inv_delete_btn');

            if (action === 'add') {
                modalTitle.innerText = 'Add New Drug Stock';
                actionInput.value = 'add';
                idInput.value = '';
                document.getElementById('inv_name').value = '';
                document.getElementById('inv_stock').value = '';
                document.getElementById('inv_price').value = '';
                document.getElementById('inv_category').value = 'General';
                delBtn.classList.add('d-none');
            } else {
                modalTitle.innerText = 'Update Drug: ' + drug.drug_name;
                actionInput.value = 'update';
                idInput.value = drug.id;
                document.getElementById('inv_name').value = drug.drug_name;
                document.getElementById('inv_stock').value = drug.stock_count;
                document.getElementById('inv_price').value = drug.unit_price;
                document.getElementById('inv_category').value = drug.category || 'General';
                delBtn.classList.remove('d-none');
            }

            const modal = new bootstrap.Modal(document.getElementById('inventoryModal'));
            modal.show();
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
                    silentRefresh();
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

        function navigateTo(targetId) {
            document.querySelectorAll('.dashboard-section').forEach(s => s.classList.add('d-none'));
            document.getElementById(targetId).classList.remove('d-none');
            document.querySelectorAll('.nav-link-custom').forEach(l => {
                if(l.getAttribute('data-target') === targetId) l.classList.add('active');
                else l.classList.remove('active');
            });
        }

        function respondToEmergency(id) {
            // Simulate clicking the sidebar link for emergencies
            const emergencyLink = document.querySelector('#sidebarMenu .nav-link-custom[data-target="section-emergencies"]');
            if (emergencyLink) {
                emergencyLink.click(); // This will trigger the section navigation
            }
            
            // Close sidebar if open (for mobile)
            toggleSidebar();

            setTimeout(() => {
                const row = document.getElementById('emerg-row-' + id);
                if (row) {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('bg-primary-soft', 'pulse-highlight');
                    setTimeout(() => row.classList.remove('bg-primary-soft', 'pulse-highlight'), 3000);
                }
            }, 300);
        }

        async function markNotificationRead(el, id) {
            if (el.classList.contains('bg-light')) {
                await fetch('/api/notifications/read.php?id=' + id, {method: 'POST'});
                el.classList.remove('bg-light');
                const p = el.querySelector('p');
                if (p) {
                    p.classList.remove('fw-bold', 'text-dark');
                    p.classList.add('text-muted');
                }
                el.style.cursor = 'default';
                el.onclick = null;
                
                // Update badge count
                document.querySelectorAll('.top-notif-badge').forEach(badge => {
                    let count = (parseInt(badge.innerText) || 0) - 1;
                    if (count <= 0) badge.remove();
                    else badge.innerText = count;
                });
            }
        }

        async function clearEmergencyTask(notificationId, btn) {
            btn.disabled = true;
            btn.innerHTML = '...';
            try {
                const fd = new FormData();
                fd.append('notification_id', notificationId);
                const res = await fetch('/api/emergency/clear_task.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const item = btn.closest('.p-2');
                    if (item) {
                        item.style.transition = 'opacity 0.4s';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 400);
                    }
                    // Update badge count if it was unread
                    const isUnread = btn.closest('.bg-light');
                    if (isUnread) {
                        document.querySelectorAll('.top-notif-badge').forEach(badge => {
                            let count = (parseInt(badge.innerText) || 0) - 1;
                            if (count <= 0) badge.remove();
                            else badge.innerText = count;
                        });
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = 'Clear';
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = 'Clear';
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
        async function dischargePatient(admId, wardId) {
            if (!confirm("Are you sure you want to discharge this patient? This will free up the bed.")) return;
            const fd = new FormData();
            fd.append('admission_id', admId);
            fd.append('ward_id', wardId);
            
            const res = await fetch('/api/admission/discharge.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Patient discharged and bed freed.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        function openAssignBedModal(ptId, ptName) {
            document.getElementById('assign_bed_patient_id').value = ptId;
            document.getElementById('assign_bed_patient_name').innerText = ptName;
            document.getElementById('assign_bed_ward_select').selectedIndex = 0;
            document.getElementById('assign_bed_number_select').innerHTML = '<option value="">-- Select Ward First --</option>';
            new bootstrap.Modal(document.getElementById('assignBedModal')).show();
        }

        async function submitBedAssignment() {
            const form = document.getElementById('assignBedForm');
            const ptId = document.getElementById('assign_bed_patient_id').value;
            const wardId = document.getElementById('assign_bed_ward_select').value;
            const bedNum = document.getElementById('assign_bed_number_select').value;

            if (!wardId || !bedNum) { alert("Please select ward and bed number"); return; }

            const fd = new FormData();
            fd.append('patient_id', ptId);
            fd.append('ward_id', wardId);
            fd.append('bed_number', bedNum);
            
            const res = await fetch('/api/admission/finalize_assignment.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Bed assigned successfully.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
                if (data.debug) console.error("API Debug Info:", data.debug);
            }
        }

        function openEditAdmissionModal(adm) {
            document.getElementById('edit_adm_id').value = adm.id;
            document.getElementById('edit_adm_old_ward').value = adm.ward_id;
            document.getElementById('edit_adm_patient_name').innerText = adm.patient ? adm.patient.name : 'Patient';
            document.getElementById('edit_adm_ward_select').value = adm.ward_id;
            updateBedDropdown(adm.ward_id, 'edit_adm_bed_number_select', adm.bed_number);
            new bootstrap.Modal(document.getElementById('editAdmissionModal')).show();
        }

        async function submitBedUpdate() {
            const fd = new FormData(document.getElementById('editAdmissionForm'));
            const bedNum = document.getElementById('edit_adm_bed_number_select').value;
            fd.set('bed_number', bedNum); // Ensure select value is sent
            const res = await fetch('/api/admission/update_assignment.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Admission details updated.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        // EMERGENCY MANAGEMENT JS
        function openAssignEmergencyModal(e) {
            document.getElementById('assign_emerg_id').value = e.id;
            new bootstrap.Modal(document.getElementById('assignEmergencyModal')).show();
        }

        async function submitEmergencyAssignment() {
            const fd = new FormData(document.getElementById('assignEmergencyForm'));
            const res = await fetch('/api/emergency/assign.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Staff assigned to emergency.");
                silentRefresh();
            } else {
                alert("Error: " + data.error);
            }
        }

        function openDispatchEmergencyModal(e) {
            document.getElementById('dispatch_emerg_id').value = e.id;
            const dispatchModalEl = document.getElementById('dispatchEmergencyModal');
            const typeSelect = dispatchModalEl.querySelector('select[name="dispatch_type"]');
            const medSection = document.getElementById('riderMedicationSection');
            
            typeSelect.onchange = () => {
                if(typeSelect.value === 'rider') {
                    medSection.classList.remove('d-none');
                } else {
                    medSection.classList.add('d-none');
                }
            };
            // Trigger once for initial state
            if(typeSelect.value === 'rider') medSection.classList.remove('d-none');
            else medSection.classList.add('d-none');

            new bootstrap.Modal(dispatchModalEl).show();
        }

        let emergMedCount = 1;
        function addEmergencyMedication() {
            const container = document.getElementById('emergency-med-list');
            const firstItem = container.querySelector('.med-item');
            const newItem = firstItem.cloneNode(true);
            
            newItem.querySelector('.remove-med-btn').classList.remove('d-none');
            newItem.querySelector('.small.fw-bold.text-muted').innerText = 'Medication #' + (container.querySelectorAll('.med-item').length + 1);
            
            newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            newItem.querySelectorAll('input[type="text"]').forEach(input => input.value = '');
            newItem.querySelectorAll('input[type="number"]').forEach(input => input.value = '1');
            
            newItem.querySelectorAll('[name^="meds["]').forEach(el => {
                const newName = el.getAttribute('name').replace(/meds\[\d+\]/, `meds[${emergMedCount}]`);
                el.setAttribute('name', newName);
            });
            
            container.appendChild(newItem);
            emergMedCount++;
        }

        async function submitEmergencyDispatch() {
            const emergId = document.getElementById('dispatch_emerg_id').value;
            const fd = new FormData(document.getElementById('dispatchEmergencyForm'));
            const res = await fetch('/api/emergency/dispatch.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('dispatchEmergencyModal')).hide();
                const row = document.getElementById('emerg-row-' + emergId);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                }
            } else {
                alert("Error: " + data.error);
            }
        }

        async function resolveEmergency(id) {
            if(!confirm("Mark this emergency as resolved/completed?")) return;
            const fd = new FormData();
            fd.append('emergency_id', id);
            fd.append('resolution_notes', 'Resolved by admin.');
            const res = await fetch('/api/emergency/resolve.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                const row = document.getElementById('emerg-row-' + id);
                if (row) {
                    row.style.transition = 'opacity 0.4s';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 400);
                }
            } else {
                alert("Error: " + data.error);
            }
        }
        document.addEventListener('DOMContentLoaded', () => {
            const staffForm = document.getElementById('addStaffForm');
            if (staffForm) {
                staffForm.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const btn = staffForm.querySelector('button[type="submit"]');
                    const oriText = btn.innerHTML;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
                    btn.disabled = true;

                    try {
                        const fd = new FormData(staffForm);
                        const res = await fetch('/api/admin/add_staff.php', { method: 'POST', body: fd });
                        const text = await res.text();
                        
                        let data;
                        try {
                            data = JSON.parse(text);
                        } catch (parseError) {
                            alert("API Error: Received invalid response from server -> " + text.substring(0, 100));
                            btn.innerHTML = oriText;
                            btn.disabled = false;
                            return;
                        }

                        if (data.success) {
                            const modalEl = document.getElementById('addStaffModal');
                            let modal = bootstrap.Modal.getInstance(modalEl);
                            if (!modal) modal = new bootstrap.Modal(modalEl);
                            modal.hide();
                            
                            staffForm.reset();
                            
                            if (typeof silentRefresh === 'function') {
                                silentRefresh();
                            } else {
                                location.reload();
                            }
                        } else {
                            alert("Error: " + (data.error || "Failed to create account."));
                        }
                    } catch (err) {
                        alert("A network error occurred while connecting to the server.");
                    } finally {
                        btn.innerHTML = oriText;
                        btn.disabled = false;
                    }
                });
            }
        });
    </script>
    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form id="addStaffForm">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="fw-bold">Register New Staff Member</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Full Name</label>
                            <input type="text" name="name" class="form-control rounded-pill px-3" required placeholder="Dr. Kwesi Appiah">
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted">Email Address (Login ID)</label>
                            <input type="email" name="email" class="form-control rounded-pill px-3" required placeholder="kwesi@gghms.com">
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted">Role</label>
                                <select name="role" class="form-select rounded-pill px-3" required>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="technician">Technician</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="small fw-bold text-muted">Department</label>
                                <select name="department" class="form-select rounded-pill px-3">
                                    <option value="General OPD">General OPD</option>
                                    <option value="Cardiology">Cardiology</option>
                                    <option value="Pediatrics">Pediatrics</option>
                                    <option value="Pharmacy">Pharmacy</option>
                                    <option value="Laboratory">Laboratory</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="small fw-bold text-muted">Initial Password</label>
                            <input type="password" name="password" class="form-control rounded-pill px-3" required value="GghmsStaff!2024">
                            <small class="text-muted extra-small">Password will be shared with the staff member.</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold">Create Staff Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
