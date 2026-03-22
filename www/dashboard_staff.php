<?php
// Staff Dashboard - GGHMS (Nurse/Pharmacist/Technician)
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['user_metadata']['role'] ?? '', ['nurse', 'pharmacist', 'technician'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$sb = new Supabase();

// 1. Fetch live Profile data (metadata can be outdated until next login)
$profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $userId . '&select=*', null, true);
$profile = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : null;

$role = $profile['role'] ?? $user['user_metadata']['role'] ?? 'staff';
$name = $profile['name'] ?? $user['user_metadata']['name'] ?? 'Staff Member';
$dept = $profile['department'] ?? 'General OPD';

$tasks = [];
$roleData = [];

// 2. Fetch Departmental Appointment Queue (Limited info for all staff in dept)
$deptApptsRes = $sb->request('GET', '/rest/v1/appointments?status=eq.scheduled&department=eq.' . urlencode($dept) . '&select=*,patient:patient_id(name)&order=appointment_date.asc', null, true);
$deptTasks = ($deptApptsRes['status'] === 200) ? $deptApptsRes['data'] : [];

// 3. Fetch Specifically Assigned Appointments (Full info)
$assignedApptsRes = $sb->request('GET', '/rest/v1/appointments?status=eq.scheduled&assigned_to=eq.' . $userId . '&select=*,patient:patient_id(name)&order=appointment_date.asc', null, true);
$assignedTasks = ($assignedApptsRes['status'] === 200) ? $assignedApptsRes['data'] : [];

// 4. Role-specific items (Prescriptions for Pharmacists, Lab for Techs)
$roleTasks = [];
if ($role === 'pharmacist') {
    // Fetch pending prescriptions WITHOUT join (FK may not be registered in Supabase)
    $res = $sb->request('GET', '/rest/v1/prescriptions?status=eq.pending&select=*&order=created_at.asc', null, true);
    $roleTasks = ($res['status'] === 200) ? ($res['data'] ?? []) : [];

    // Enrich each prescription with the patient name via a separate lookup
    foreach ($roleTasks as &$rx) {
        if (!empty($rx['patient_id'])) {
            $pRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $rx['patient_id'] . '&select=name', null, true);
            $rx['patient_name'] = ($pRes['status'] === 200 && !empty($pRes['data'])) ? $pRes['data'][0]['name'] : 'Patient';
        } else {
            $rx['patient_name'] = 'Patient';
        }
    }
    unset($rx);

    $invRes = $sb->request('GET', '/rest/v1/drug_inventory?select=*&order=drug_name.asc', null, true);
    $roleData['inventory'] = ($invRes['status'] === 200) ? $invRes['data'] : [];
} elseif ($role === 'technician') {
    $res = $sb->request('GET', '/rest/v1/lab_requests?status=eq.pending&select=*&order=created_at.asc', null, true);
    $roleTasks = ($res['status'] === 200) ? ($res['data'] ?? []) : [];
    // Enrich with patient name
    foreach ($roleTasks as &$lr) {
        if (!empty($lr['patient_id'])) {
            $pRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $lr['patient_id'] . '&select=name', null, true);
            $lr['patient_name'] = ($pRes['status'] === 200 && !empty($pRes['data'])) ? $pRes['data'][0]['name'] : 'Patient';
        } else {
            $lr['patient_name'] = 'Patient';
        }
    }
    unset($lr);
}

$vitalsPatients = [];
if ($role === 'nurse') {
    $todayStart = date('Y-m-d') . 'T00:00:00Z';
    $vitalsRes = $sb->request('GET', '/rest/v1/vitals?recorded_at=gte.' . $todayStart . '&select=patient_id', null, true);
    if ($vitalsRes['status'] === 200 && is_array($vitalsRes['data'])) {
        foreach ($vitalsRes['data'] as $v) $vitalsPatients[] = $v['patient_id'];
    }
}

// Combine appointment-based tasks. Prescriptions/lab tasks are kept in $roleTasks separately.
$tasksMap = [];
foreach ($deptTasks as $t) $tasksMap[$t['id']] = $t;
foreach ($assignedTasks as $t) $tasksMap[$t['id']] = $t;

$tasks = array_values($tasksMap);
// Sort by date/created_at
usort($tasks, function($a, $b) {
    $da = $a['appointment_date'] ?? $a['created_at'] ?? '0';
    $db = $b['appointment_date'] ?? $b['created_at'] ?? '0';
    return strtotime($da) - strtotime($db);
});

// 5. Fetch Notifications for Staff
$notificationsRes = $sb->request('GET', '/rest/v1/notifications?user_id=eq.' . $userId . '&order=created_at.desc&limit=5', null, true);
$notifications = ($notificationsRes['status'] === 200) ? $notificationsRes['data'] : [];
$unreadCount = count(array_filter($notifications, fn($n) => empty($n['is_read'])));

// 6. Fetch Assigned Emergencies
$myEmergenciesRes = $sb->request('GET', '/rest/v1/emergencies?assigned_to=eq.' . $userId . '&status=in.(active,pending,assigned)&select=*,reporter:reporter_id(name)', null, true);
$myEmergencies = ($myEmergenciesRes['status'] === 200) ? $myEmergenciesRes['data'] : [];

// Fetch Drugs for Emergency Prescription
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
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
            <a href="#" class="nav-link-custom active" data-target="section-queue"><i class="bi bi-list-task"></i> Task Queue</a>
            <?php if ($role === 'nurse'): ?>
                <a href="#" class="nav-link-custom" data-target="section-role"><i class="bi bi-hospital"></i> Ward Management</a>
            <?php elseif ($role === 'pharmacist'): ?>
                <a href="#" class="nav-link-custom" data-target="section-role"><i class="bi bi-capsule"></i> Inventory</a>
            <?php elseif ($role === 'technician'): ?>
                <a href="#" class="nav-link-custom" data-target="section-role"><i class="bi bi-clipboard-pulse"></i> Lab Requests</a>
            <?php endif; ?>
            <a href="#" class="nav-link-custom" data-target="section-comms"><i class="bi bi-chat-dots"></i> Internal Comms</a>
            <a href="#" class="nav-link-custom" data-target="section-notifications">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto nav-notif-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </a>
            <hr class="my-3">
            <div class="px-2 mb-3">
                <button class="btn btn-primary-soft text-primary w-100 rounded-pill d-flex align-items-center justify-content-center py-2" data-bs-toggle="modal" data-bs-target="#searchModal">
                    <i class="bi bi-search me-2"></i> Find Patient
                </button>
            </div>
            <hr class="my-4">
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
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($name); ?></h2>
                <p class="text-muted mb-0">Role: <span class="text-capitalize fw-bold text-primary"><?php echo htmlspecialchars($role); ?></span></p>
            </div>
            
            <?php if (isset($_GET['inventory_updated'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 py-2 px-3 small mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i> Inventory synchronized successfully.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['dispensed'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 py-2 px-3 small mb-0">
                    <i class="bi bi-capsule-pill me-2"></i> Medication dispensed & billed.
                </div>
            <?php endif; ?>
            <?php if (isset($_GET['error']) && $_GET['error'] === 'out_of_stock'): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 py-2 px-3 small mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Error: Item is out of stock!
                </div>
            <?php endif; ?>

            <div class="d-none d-lg-flex align-items-center">
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
                        <h6 class="fw-bold mb-3">Department Alerts</h6>
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted small mb-0">No notifications.</p>
                        <?php endif; ?>
                        <?php foreach($notifications as $n): ?>
                            <div class="p-2 border-bottom border-light mb-2 <?php echo empty($n['is_read']) ? 'bg-light rounded' : ''; ?>" <?php if(empty($n['is_read'])) echo 'onclick="markNotificationRead(this, \''.$n['id'].'\')" style="cursor: pointer;"' ?>>
                                <p class="small mb-1 <?php echo empty($n['is_read']) ? 'fw-bold text-dark' : 'text-muted'; ?>"><?php echo htmlspecialchars($n['message']); ?></p>
                                <small class="text-muted extra-small"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <?php include 'components/health_tips.php'; ?>

        <div id="section-queue" class="dashboard-section">
            <?php if (!empty($myEmergencies)): ?>
                <div class="row g-4 mb-4">
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm bg-danger text-white p-4 rounded-4 animate-pulse">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="bg-white text-danger rounded-circle p-3 d-flex align-items-center justify-content-center shadow-sm" style="width: 54px; height: 54px;">
                                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                                    </div>
                                    <div>
                                        <h4 class="fw-bold mb-1">Active Emergency Assigned!</h4>
                                        <p class="mb-0 opacity-75 small">You have <?php echo count($myEmergencies); ?> urgent cases requiring immediate coordination.</p>
                                    </div>
                                </div>
                                <button class="btn btn-light rounded-pill px-4 fw-bold" onclick="navigateTo('section-queue')">View My Emergencies</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card p-4 border-0 shadow-sm mb-5 border-start border-danger border-4">
                    <h5 class="fw-bold mb-4 text-danger"><i class="bi bi-lightning-charge-fill me-2"></i>My Emergency Assignments</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr><th>Time</th><th>Patient/Reporter</th><th>Location</th><th>Symptoms</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($myEmergencies as $e): ?>
                                    <tr class="table-danger-soft">
                                        <td class="fw-bold text-danger"><?php echo date('H:i', strtotime($e['created_at'])); ?></td>
                                        <td>
                                            <div class="fw-bold"><?php echo htmlspecialchars($e['reporter']['name'] ?? 'Patient'); ?></div>
                                            <small class="text-muted extra-small">ID: <?php echo substr($e['reporter_id'], 0, 8); ?></small>
                                        </td>
                                        <td><code class="text-primary bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($e['ghana_post_gps'] ?? $e['location'] ?? 'N/A'); ?></code></td>
                                        <td class="small"><?php echo htmlspecialchars($e['symptoms']); ?></td>
                                        <td>
                                            <button class="btn btn-danger btn-sm rounded-pill px-3 fw-bold" onclick='openDispatchEmergencyModal(<?php echo json_encode($e); ?>)'>
                                                <i class="bi bi-truck me-1"></i> Dispatch help
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Assigned Tasks</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light border-0">
                            <tr>
                                <th class="border-0">ID</th>
                                <th class="border-0">Task Description</th>
                                <th class="border-0">Priority</th>
                                <th class="border-0">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Show role-specific tasks (prescriptions / lab requests) first for pharmacists/technicians
                            $allDisplayTasks = (in_array($role, ['pharmacist', 'technician'])) ? $roleTasks : $tasks;

                            if (empty($allDisplayTasks) && empty($tasks)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No pending tasks in your queue.</td></tr>
                            <?php endif; 

                            foreach ($allDisplayTasks as $t):
                                $isAssigned = (($t['assigned_to'] ?? '') === $user['id']);
                                $isUnassignedNurseTask = ($role === 'nurse' && empty($t['assigned_to']) && isset($t['department']) && $t['department'] === ($user['user_metadata']['department'] ?? 'General OPD'));
                                $canProcess = ($isAssigned || $isUnassignedNurseTask || in_array($role, ['pharmacist', 'technician']));
                            ?>
                            <tr class="<?php echo $canProcess ? 'table-primary-soft' : ''; ?>">
                                <td>#<?php echo substr($t['id'], 0, 5); ?></td>
                                <td>
                                    <?php 
                                    if (isset($t['appointment_date'])) {
                                        // Appointment task (nurse)
                                        if ($isAssigned) {
                                            echo "<span class='fw-bold text-primary'><i class='bi bi-person-check-fill me-1'></i> [ASSIGNED]</span> Record vitals for " . htmlspecialchars($t['patient']['name'] ?? 'Patient');
                                            echo "<div class='small text-muted'>" . htmlspecialchars($t['reason'] ?? 'Routine Check') . "</div>";
                                        } elseif ($isUnassignedNurseTask) {
                                            echo "<span class='fw-bold text-warning'><i class='bi bi-exclamation-circle-fill me-1'></i> [URGENT TRIAGE]</span> Record vitals for Patient " . substr($t['patient_id'], 0, 8);
                                            echo "<div class='small text-muted'>" . htmlspecialchars($t['reason'] ?? 'Routine Check') . "</div>";
                                        } else {
                                            echo "<i class='bi bi-shield-lock me-1'></i> Departmental Appointment Request (ID: " . substr($t['patient_id'] ?? 'unknown', 0, 8) . ")";
                                            echo "<div class='extra-small text-muted'>Awaiting admin assignment for full details.</div>";
                                        }
                                    } elseif ($role === 'pharmacist' && isset($t['medication_name'])) {
                                        // Prescription task — use patient_name from server-side enrichment
                                        $pName = $t['patient_name'] ?? ($t['patient']['name'] ?? 'Patient');
                                        $priority = ($t['is_ordered'] ?? false) ? "<span class='badge bg-warning text-dark me-2 small'><i class='bi bi-megaphone-fill me-1'></i> ORDERED</span>" : "";
                                        $drugLabel = !empty($t['medication_name']) ? htmlspecialchars($t['medication_name']) : 'Medication';
                                        echo $priority . "Dispense: <span class='fw-bold'>{$drugLabel}</span> &times; <span class='badge bg-primary rounded-pill'>" . ($t['quantity'] ?? 1) . "</span> for <span class='fw-bold text-primary'>{$pName}</span>";
                                        echo "<div class='extra-small text-muted'>" . htmlspecialchars(($t['dosage'] ?? '') . " | " . ($t['frequency'] ?? '') . " | " . ($t['duration'] ?? '')) . "</div>";
                                    } elseif ($role === 'technician') {
                                        $pName = $t['patient_name'] ?? ($t['patient']['name'] ?? 'Patient');
                                        echo "Test: <span class='fw-bold'>" . htmlspecialchars($t['test_name'] ?? 'Lab Test') . "</span> for <span class='fw-bold text-primary'>{$pName}</span>";
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($canProcess): ?>
                                        <?php if (isset($t['appointment_date']) && $role === 'nurse' && in_array($t['patient_id'], $vitalsPatients)): ?>
                                            <span class="badge bg-success-soft text-success rounded-pill px-3">Resolved</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger rounded-pill px-3">Priority</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted rounded-pill px-3">Restricted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($canProcess): ?>
                                        <?php if (isset($t['appointment_date']) && $role === 'nurse' && ($isAssigned || $isUnassignedNurseTask)): 
                                                global $vitalsPatients;
                                                if (in_array($t['patient_id'], $vitalsPatients ?? [])):
                                        ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Vitals Recorded</span>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#vitalsModal" onclick="setPatientId('<?php echo $t['patient_id']; ?>', this)">Process Vitals</button>
                                        <?php endif; ?>
                                        <?php elseif (isset($t['medication_name']) && $role === 'pharmacist'): ?>
                                            <button class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#dispenseModal" onclick="setPrescriptionId('<?php echo $t['id']; ?>', this)">Dispense</button>
                                        <?php elseif (isset($t['test_name']) && $role === 'technician'): ?>
                                            <button class="btn btn-sm btn-info text-white rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#labResultModal" onclick="setRequestId('<?php echo $t['id']; ?>', this)">Result</button>
                                        <?php else: ?>
                                            <span class="text-muted extra-small">View Details Only</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted small">Awaiting Assignment</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="section-role" class="dashboard-section d-none">
            <div class="row g-4">
                <?php if ($role === 'nurse'): ?>
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-4">
                            <h5 class="fw-bold mb-4">Inpatient Ward Management</h5>
                            <div class="alert alert-info border-0 rounded-4">No patients currently admitted to General Ward.</div>
                        </div>
                    </div>
                <?php elseif ($role === 'pharmacist'): ?>
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-4">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0">Pharmacy Inventory & Stock Control</h5>
                                <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#inventoryModal" onclick="prepareInventoryModal('add')">
                                    <i class="bi bi-plus-lg me-1"></i> Add Stock
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="table-light"><tr><th>Drug Name</th><th>Category</th><th>Stock</th><th>Price (GHS)</th><th>Status</th><th>Actions</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($roleData['inventory'])): ?>
                                            <tr><td colspan="6" class="text-center py-3 text-muted small">No drugs registered in inventory.</td></tr>
                                        <?php endif; foreach ($roleData['inventory'] as $inv): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($inv['drug_name']); ?></td>
                                                <td><span class="badge bg-light text-muted fw-normal"><?php echo htmlspecialchars($inv['category'] ?? 'General'); ?></span></td>
                                                <td><?php echo $inv['stock_count']; ?> units</td>
                                                <td class="fw-bold text-primary">₵ <?php echo number_format($inv['unit_price'] ?? 0, 2); ?></td>
                                                <td>
                                                    <?php if ($inv['stock_count'] <= ($inv['reorder_level'] ?? 10)): ?>
                                                        <span class="badge bg-danger-soft text-danger rounded-pill px-2">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success-soft text-success rounded-pill px-2">Healthy</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-secondary border-0" data-bs-toggle="modal" data-bs-target="#inventoryModal" 
                                                            onclick='prepareInventoryModal("edit", <?php echo json_encode($inv); ?>)'>
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php elseif ($role === 'technician'): ?>
                    <div class="col-md-12">
                        <div class="card border-0 shadow-sm p-4 rounded-5">
                            <div class="d-flex justify-content-between align-items-center mb-4">
                                <h5 class="fw-bold mb-0">Lab Request Management</h5>
                                <span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo count($roleTasks); ?> Pending</span>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead class="small text-muted">
                                        <tr>
                                            <th>Patient Name</th>
                                            <th>Test Type</th>
                                            <th>Specific Test</th>
                                            <th>Date Requested</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($roleTasks)): ?>
                                            <tr><td colspan="5" class="text-center py-4 text-muted small">No pending lab requests.</td></tr>
                                        <?php endif; foreach ($roleTasks as $task): ?>
                                            <tr>
                                                <td><span class="fw-bold"><?php echo htmlspecialchars($task['patient']['name'] ?? 'Unknown'); ?></span></td>
                                                <td><small class="text-uppercase extra-small fw-bold text-muted"><?php echo htmlspecialchars($task['test_type']); ?></small></td>
                                                <td><?php echo htmlspecialchars($task['test_name']); ?></td>
                                                <td class="small"><?php echo date('M d, H:i', strtotime($task['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm text-white rounded-pill px-3" onclick="setRequestId('<?php echo $task['id']; ?>')" data-bs-toggle="modal" data-bs-target="#labResultModal">Record Result</button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-notifications" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4"><i class="bi bi-bell-fill me-2 text-primary"></i>Department Alerts</h5>
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bell-slash display-4 d-block mb-3"></i>
                        <p>No new notifications. When appointments are booked in your department, they will appear here.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $n):
                            $nType = $n['type'] ?? '';
                            if ($nType === 'pharmacy_order') {
                                $borderColor = 'border-warning'; $iconClass = 'bi-capsule text-warning'; $bgClass = 'bg-warning-soft';
                            } elseif ($nType === 'admission_request') {
                                $borderColor = 'border-danger'; $iconClass = 'bi-hospital text-danger'; $bgClass = 'bg-danger-soft';
                            } else {
                                $borderColor = 'border-primary'; $iconClass = 'bi-info-circle text-primary'; $bgClass = 'bg-light';
                            }
                        ?>
            <div class="list-group-item border-0 border-start border-4 <?php echo $borderColor; ?> <?php echo $bgClass; ?> ps-3 mb-3 rounded-3 d-flex align-items-start gap-3"
                         <?php if(empty($n['is_read'])) echo 'onclick="markNotificationRead(this, \''.$n['id'].'\')" style="cursor:pointer;"' ?>>
                            <i class="bi <?php echo $iconClass; ?> fs-5 mt-1 flex-shrink-0"></i>
                            <div class="flex-grow-1">
                                <p class="mb-1 small <?php echo empty($n['is_read']) ? 'fw-bold text-dark' : 'text-muted'; ?>"><?php echo htmlspecialchars($n['message']); ?></p>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('M d, Y \a\t H:i', strtotime($n['created_at'])); ?></small>
                                <?php if (($n['type'] ?? '') === 'emergency_handled_by_admin'): ?>
                                <div class="mt-2">
                                    <button class="btn btn-success btn-sm rounded-pill px-3 fw-bold"
                                            onclick="event.stopPropagation(); clearEmergencyTask('<?php echo $n['id']; ?>', this)">
                                        <i class="bi bi-check2-circle me-1"></i> Clear Task
                                    </button>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-comms" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-4">Internal Communications</h5>
                <div class="alert alert-primary bg-primary-soft border-0 text-primary rounded-4">
                    <strong>Admin Notice:</strong> Staff meeting at 14:00 GMT regarding new triage protocol.
                </div>
            </div>
        </div>

    </div> <!-- End main-content -->

    <!-- MODALS (Moved to root level for better Bootstrap compatibility) -->
    
    <!-- Nurse Vitals Modal -->
    <?php if ($role === 'nurse'): ?>
    <div class="modal fade" id="vitalsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="/api/consultation/save.php" method="POST" class="modal-content border-0 shadow" id="vitalsForm" onsubmit="submitAjaxForm(event, 'vitalsForm', 'vitalsModal')">
                <input type="hidden" name="patient_id" id="patient_id_field">
                <div class="modal-header border-0"><h5 class="fw-bold">Record Vitals</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-6"><label class="small text-muted">Temp (°C)</label><input type="number" step="0.1" name="temperature" class="form-control rounded-pill px-3"></div>
                        <div class="col-6"><label class="small text-muted">BP (mmHg)</label><input type="text" name="blood_pressure" class="form-control rounded-pill px-3" placeholder="120/80"></div>
                        <div class="col-6"><label class="small text-muted">Weight (kg)</label><input type="number" step="0.1" name="weight" class="form-control rounded-pill px-3"></div>
                        <div class="col-6"><label class="small text-muted">Pulse (bpm)</label><input type="number" name="pulse" class="form-control rounded-pill px-3"></div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill mt-4">Save Vitals</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pharmacist Inventory Management Modal -->
    <?php if ($role === 'pharmacist'): ?>
    <div class="modal fade" id="inventoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="/api/inventory/update.php" method="POST" class="modal-content border-0 shadow">
                <input type="hidden" name="action" id="inv_action" value="add">
                <input type="hidden" name="id" id="inv_id">
                <div class="modal-header border-0">
                    <h5 class="fw-bold mb-0" id="invModalTitle">Add New Drug</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">Drug Name / Specification</label>
                        <input type="text" name="drug_name" id="inv_name" class="form-control rounded-pill px-3" required>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="small text-muted fw-bold">Current Stock Level</label>
                            <input type="number" name="stock_count" id="inv_stock" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="col-6">
                            <label class="small text-muted fw-bold">Unit Price (GHS)</label>
                            <input type="number" step="0.01" name="unit_price" id="inv_price" class="form-control rounded-pill px-3" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="small text-muted fw-bold">Category</label>
                        <select name="category" id="inv_category" class="form-select rounded-pill px-3">
                            <option value="General">General</option>
                            <option value="Antibiotics">Antibiotics</option>
                            <option value="Painkillers">Painkillers</option>
                            <option value="Antimalarials">Antimalarials</option>
                            <option value="Injections">Injections</option>
                            <option value="Surgical Supplies">Surgical Supplies</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Commit Inventory Update</button>
                    <button type="submit" name="action" value="delete" id="inv_delete_btn" class="btn btn-link text-danger w-100 mt-2 d-none">Delete Drug Entry</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function prepareInventoryModal(action, data = null) {
            const title = document.getElementById('invModalTitle');
            const actionInput = document.getElementById('inv_action');
            const idInput = document.getElementById('inv_id');
            const delBtn = document.getElementById('inv_delete_btn');
            
            if (action === 'add') {
                title.innerText = 'Add New Drug Stock';
                actionInput.value = 'add';
                idInput.value = '';
                document.getElementById('inv_name').value = '';
                document.getElementById('inv_stock').value = '0';
                document.getElementById('inv_price').value = '0.00';
                delBtn.classList.add('d-none');
            } else {
                title.innerText = 'Edit Drug Stock: ' + data.drug_name;
                actionInput.value = 'update';
                idInput.value = data.id;
                document.getElementById('inv_name').value = data.drug_name;
                document.getElementById('inv_stock').value = data.stock_count;
                document.getElementById('inv_price').value = data.unit_price;
                document.getElementById('inv_category').value = data.category || 'General';
                delBtn.classList.remove('d-none');
            }
        }
    </script>
    <?php endif; ?>

    <?php if ($role === 'pharmacist' || $role === 'nurse'): ?>
    <div class="modal fade" id="dispatchEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Emergency Help Dispatch</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form id="dispatchEmergencyForm">
                        <input type="hidden" name="emergency_id" id="dispatch_emerg_id">
                        <div class="mb-3">
                            <label class="small text-muted">Dispatch Type</label>
                            <select name="dispatch_type" class="form-select rounded-pill px-3" required>
                                <option value="ambulance">🚑 Ambulance (Critical Transfer)</option>
                                <option value="team">🚑 Emergency Response Team</option>
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
                            <label class="small text-muted fw-bold">Dispatch Instructions</label>
                            <textarea name="dispatch_notes" class="form-control rounded-4 px-3 py-2 small" rows="2" placeholder="e.g. Bring oxygen tank..."></textarea>
                        </div>
                        <button type="button" class="btn btn-danger w-100 rounded-pill" onclick="submitEmergencyDispatch()">Execute Dispatch</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pharmacist Dispense Modal -->
    <?php if ($role === 'pharmacist'): ?>
    <div class="modal fade" id="dispenseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="/api/prescriptions/dispense.php" method="POST" class="modal-content border-0 shadow" id="dispenseForm" onsubmit="submitAjaxForm(event, 'dispenseForm', 'dispenseModal')">
                <input type="hidden" name="prescription_id" id="prescription_id_field">
                <div class="modal-header border-0"><h5 class="fw-bold">Dispense Medication</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4 text-center">
                    <i class="bi bi-box-seam display-4 text-success mb-3"></i>
                    <p class="text-muted small px-3 mb-4">You are about to dispense the prescribed quantity of this medication. This will decrement the inventory stock accordingly and total the bill for the patient.</p>
                    <div class="mb-3 text-start"><label class="small text-muted fw-bold">Batch Number / Trace ID</label><input type="text" name="batch_number" class="form-control rounded-pill px-3" placeholder="e.g. BATCH-202X-001" required></div>
                    <div class="mb-3 text-start"><label class="small text-muted fw-bold">Dispensing Notes</label><textarea name="notes" class="form-control rounded-4 px-3 py-2 small" rows="3" placeholder="Additional instructions or recording info..."></textarea></div>
                    <button type="submit" class="btn btn-success w-100 rounded-pill py-2 fw-bold mt-2">Finalize & Dispense</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Technician Lab Result Modal -->
    <?php if ($role === 'technician'): ?>
    <div class="modal fade" id="labResultModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="/api/lab/submit.php" method="POST" class="modal-content border-0 shadow" id="labResultForm" onsubmit="submitAjaxForm(event, 'labResultForm', 'labResultModal')">
                <input type="hidden" name="request_id" id="request_id_field">
                <div class="modal-header border-0"><h5 class="fw-bold">Submit Lab Result</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="small text-muted">Test Result Details</label><textarea name="result_text" class="form-control rounded-4" rows="4" required placeholder="Enter diagnostic findings..."></textarea></div>
                    <button type="submit" class="btn btn-info text-white w-100 rounded-pill">Submit Result</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- DISPATCH EMERGENCY MODAL -->
    <div class="modal fade" id="dispatchEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold text-danger"><i class="bi bi-lightning-fill me-2"></i>Emergency Dispatch Control</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <form id="dispatchEmergencyForm">
                        <input type="hidden" name="emergency_id" id="dispatch_emerg_id">
                        <div class="mb-3">
                            <label class="small text-muted fw-bold">Select Dispatch Asset</label>
                            <select name="dispatch_type" class="form-select rounded-pill px-3" required id="staff_dispatch_select">
                                <option value="ambulance">🚑 Ambulance (Critical Life Support)</option>
                                <option value="team">🚑 Response Team (Emergency Care)</option>
                                <option value="rider" selected>🏍️ Dispatch Rider (Meds/Supplies Only)</option>
                            </select>
                        </div>
                        <div id="riderMedicationSection" class="mb-3">
                            <label class="small text-muted fw-bold text-primary">Prescribed Medications for Delivery</label>
                            <textarea name="medication_notes" class="form-control rounded-4 px-3 py-2 small" rows="3" placeholder="Enter drugs, dosage, and usage instructions..."></textarea>
                        </div>
                        <div class="mb-4">
                            <label class="small text-muted fw-bold">Dispatch Instructions</label>
                            <textarea name="dispatch_notes" class="form-control rounded-4 px-3 py-2 small" rows="2" placeholder="Specific instructions for the dispatch team..."></textarea>
                        </div>
                        <button type="button" class="btn btn-danger w-100 rounded-pill fw-bold py-2" onclick="submitEmergencyDispatch()">Execute Rapid Response</button>
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

        function navigateTo(sectionId) {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            sections.forEach(sec => sec.classList.add('d-none'));
            const target = document.getElementById(sectionId);
            if (target) target.classList.remove('d-none');
            links.forEach(l => {
                l.classList.toggle('active', l.getAttribute('data-target') === sectionId);
            });
        }
        let currentTaskRow = null;
        function setPatientId(id, btn) { document.getElementById('patient_id_field').value = id; currentTaskRow = btn ? btn.closest('tr') : null; }
        function setPrescriptionId(id, btn) { document.getElementById('prescription_id_field').value = id; currentTaskRow = btn ? btn.closest('tr') : null; }
        function setRequestId(id, btn) { document.getElementById('request_id_field').value = id; currentTaskRow = btn ? btn.closest('tr') : null; }

        async function submitAjaxForm(event, formId, modalId) {
            event.preventDefault();
            const form = document.getElementById(formId);
            const btn = form.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';

            try {
                const fd = new FormData(form);
                fd.append('is_ajax', '1');
                const res = await fetch(form.action, { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById(modalId)).hide();
                    if (currentTaskRow) {
                        currentTaskRow.style.transition = 'opacity 0.4s';
                        currentTaskRow.style.opacity = '0';
                        setTimeout(() => currentTaskRow.remove(), 400);
                    }
                    form.reset();
                } else {
                    alert('Error: ' + (data.error || 'Failed to process task.'));
                }
            } catch (e) {
                alert('Request failed. Check console for details.');
                console.error(e);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }

        function markNotificationRead(el, id) {
            if (el.classList.contains('bg-light')) {
                fetch('/api/notifications/read.php?id=' + id, {method: 'POST'});
                el.classList.remove('bg-light', 'rounded');
                const text = el.querySelector('p');
                if (text) {
                    text.classList.remove('fw-bold', 'text-dark');
                    text.classList.add('text-muted');
                }
                el.style.cursor = 'default';
                el.onclick = null;

                document.querySelectorAll('.nav-notif-badge, .top-notif-badge').forEach(badge => {
                    let count = parseInt(badge.innerText) - 1;
                    if (count <= 0) badge.remove();
                    else badge.innerText = count;
                });
            }
        }

        async function clearEmergencyTask(notificationId, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i> Clearing...';
            try {
                const fd = new FormData();
                fd.append('notification_id', notificationId);
                const res = await fetch('/api/emergency/clear_task.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    const item = btn.closest('.list-group-item');
                    if (item) {
                        item.style.transition = 'opacity 0.4s';
                        item.style.opacity = '0';
                        setTimeout(() => item.remove(), 400);
                    }
                    // Update badge count
                    document.querySelectorAll('.nav-notif-badge, .top-notif-badge').forEach(badge => {
                        let count = (parseInt(badge.innerText) || 0) - 1;
                        if (count <= 0) badge.remove();
                        else badge.innerText = count;
                    });
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Clear Task';
                    alert('Failed to clear task. Please try again.');
                }
            } catch (e) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Clear Task';
            }
        }


        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.sidebar-overlay').classList.toggle('show');
        }

        // Emergency Dispatch JS
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
            const fd = new FormData(document.getElementById('dispatchEmergencyForm'));
            const res = await fetch('/api/emergency/dispatch.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Help has been dispatched!");
                location.reload();
            } else {
                alert("Error: " + data.error);
            }
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

        // Emergency Dispatch JS
        function openDispatchEmergencyModal(e) {
            document.getElementById('dispatch_emerg_id').value = e.id;
            const dispatchModalEl = document.getElementById('dispatchEmergencyModal');
            const typeSelect = document.getElementById('staff_dispatch_select');
            const medSection = dispatchModalEl.querySelector('#riderMedicationSection');
            
            typeSelect.onchange = () => {
                if(typeSelect.value === 'rider') medSection.classList.remove('d-none');
                else medSection.classList.add('d-none');
            };

            // Pre-fill if rider selected by default
            if(typeSelect.value === 'rider') medSection.classList.remove('d-none');

            new bootstrap.Modal(dispatchModalEl).show();
        }

        async function submitEmergencyDispatch() {
            const fd = new FormData(document.getElementById('dispatchEmergencyForm'));
            const res = await fetch('/api/emergency/dispatch.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Dispatch initiated successfully!");
                location.reload();
            } else {
                alert("Dispatch Error: " + data.error);
            }
        }
    </script>
</body>
</html>
