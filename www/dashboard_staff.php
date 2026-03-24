<?php
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user'])) { header('Location: /login'); exit; }

$sb = new Supabase();
$userId = $_SESSION['user']['id'] ?? $_SESSION['user_id'];
// Get role from user metadata and normalize to lowercase
$role = strtolower($_SESSION['user']['user_metadata']['role'] ?? ($_SESSION['role'] ?? 'staff'));

// Fetch staff profile
$profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $userId . '&select=*', null, true);
$profile = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : null;

// Fetch tasks and role-specific data
$tasks = [];
$roleTasks = [];
$roleData = [];
$notifications = [];
$wards = [];
$activeAdmissions = [];
$pendingAdmissions = [];
$vitalsPatients = [];

if ($role === 'nurse') {
    // Nurse: Appointments, Wards, Admissions
    $aptRes = $sb->request('GET', '/rest/v1/appointments?department=eq.General&select=*,patient:profiles(*)', null, true);
    $tasks = ($aptRes['status'] === 200) ? $aptRes['data'] : [];
    
    $wardsRes = $sb->request('GET', '/rest/v1/wards?select=*&order=ward_name.asc', null, true);
    $wards = ($wardsRes['status'] === 200) ? $wardsRes['data'] : [];
    
    $admRes = $sb->request('GET', '/rest/v1/admissions?status=eq.admitted&select=*,patient:profiles(*),ward:wards(*)', null, true);
    $activeAdmissions = ($admRes['status'] === 200) ? $admRes['data'] : [];
        
    $notifRes = $sb->request('GET', '/rest/v1/notifications?type=eq.admission_request&is_read=eq.false&select=*,patient:profiles(*)', null, true);
    $pendingAdmissions = ($notifRes['status'] === 200) ? $notifRes['data'] : [];

    $vitalsRes = $sb->request('GET', '/rest/v1/consultations?created_at=gte.' . date('Y-m-d') . 'T00:00:00&select=patient_id', null, true);
    if ($vitalsRes['status'] === 200) $vitalsPatients = array_column($vitalsRes['data'], 'patient_id');

} elseif ($role === 'pharmacist') {
    $rxRes = $sb->request('GET', '/rest/v1/prescriptions?status=eq.pending&select=*,patient:profiles(*)', null, true);
    $roleTasks = ($rxRes['status'] === 200) ? $rxRes['data'] : [];
    
    $invRes = $sb->request('GET', '/rest/v1/inventory?select=*&order=drug_name.asc', null, true);
    $roleData['inventory'] = ($invRes['status'] === 200) ? $invRes['data'] : [];
    $availableDrugs = $roleData['inventory'];

} elseif ($role === 'technician') {
    $labRes = $sb->request('GET', '/rest/v1/lab_requests?status=eq.pending&select=*,patient:profiles(*)', null, true);
    $roleTasks = ($labRes['status'] === 200) ? $labRes['data'] : [];
}

// EMERGENCY ROUTING
$myEmergencies = [];
if ($role === 'ambulance') {
    $eRes = $sb->request('GET', '/rest/v1/emergencies?status=neq.resolved&emergency_type=in.(car_and_motor_accident,labour,sudden_consciousness_loss,breathing_difficulty)&select=*&order=created_at.desc', null, true);
    $myEmergencies = ($eRes['status'] === 200) ? $eRes['data'] : [];
} elseif ($role === 'dispatch_rider') {
    $eRes = $sb->request('GET', '/rest/v1/emergencies?status=neq.resolved&emergency_type=in.(cardiac_emergencies,diabetic_emergencies,asthmatic_attacks,snake_bite,dog_bite,scorpion_bite)&select=*&order=created_at.desc', null, true);
    $myEmergencies = ($eRes['status'] === 200) ? $eRes['data'] : [];
}

// Notifications
$notifRes = $sb->request('GET', '/rest/v1/notifications?user_id=eq.' . $userId . '&select=*&order=created_at.desc&limit=10', null, true);
$notifications = ($notifRes['status'] === 200 && !empty($notifRes['data'])) ? $notifRes['data'] : [];

if (empty($notifications)) {
    $notifRes = $sb->request('GET', '/rest/v1/notifications?or=(role.eq.' . $role . ',type.eq.emergency_alert)&select=*&order=created_at.desc&limit=10', null, true);
    $notifications = ($notifRes['status'] === 200) ? $notifRes['data'] : [];
}

$unreadCount = 0;
foreach($notifications as $n) if(empty($n['is_read'])) $unreadCount++;

// Helper map
$profilesMap = [];
if (in_array($role, ['nurse', 'ambulance', 'dispatch_rider'])) {
    $pMapRes = $sb->request('GET', '/rest/v1/profiles?select=id,name', null, true);
    if ($pMapRes['status'] === 200) {
        foreach($pMapRes['data'] as $p) $profilesMap[$p['id']] = $p['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Hospital Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="/assets/css/dashboard.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
            --emergency-red: #ff4757;
            --success-green: #2ecc71;
            --soft-bg: #f8faff;
        }
        body { background: var(--soft-bg); font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        .sidebar { background: #fff; border-right: 1px solid #edf2f7; width: 280px; height: 100vh; position: fixed; z-index: 1000; transition: all 0.3s; }
        .main-content { margin-left: 280px; padding: 40px; transition: all 0.3s; }
        .nav-link-custom { display: flex; align-items: center; padding: 12px 20px; color: #64748b; text-decoration: none; border-radius: 12px; margin: 4px 15px; transition: all 0.2s; font-weight: 500; }
        .nav-link-custom:hover, .nav-link-custom.active { background: #f0f7ff; color: #1a73e8; }
        .nav-link-custom i { font-size: 1.25rem; margin-right: 12px; }
        .stat-card { border: none; border-radius: 20px; box-shadow: 0 10px 25px rgba(0,0,0,0.02); transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .bg-primary-soft { background-color: #eef2ff !important; color: #4338ca !important; }
        .bg-success-soft { background-color: #ecfdf5 !important; color: #065f46 !important; }
        .bg-warning-soft { background-color: #fffbeb !important; color: #92400e !important; }
        .bg-danger-soft { background-color: #fef2f2 !important; color: #991b1b !important; }
        .extra-small { font-size: 0.75rem; }
        .animate-fade-in { animation: fadeIn 0.4s ease-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        @media (max-width: 991.98px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.show { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px; }
            .sidebar-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 999; }
            .sidebar-overlay.show { display: block; }
        }

        .table-danger-soft { background-color: #fff5f5; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    
    <div class="sidebar">
        <div class="p-4 mb-3 d-flex align-items-center">
            <div class="bg-primary text-white rounded-circle p-2 me-3 shadow-sm">
                <i class="bi bi-hospital-fill fs-4"></i>
            </div>
            <h5 class="fw-bold mb-0 tracking-tight">HealthCore</h5>
        </div>
        
        <nav>
            <a href="#" class="nav-link-custom active" data-target="section-queue">
                <i class="bi bi-list-task"></i> Task Queue
                <?php if (count($roleTasks) + count($tasks) + count($myEmergencies) > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto px-2"><?php echo count($roleTasks) + count($tasks) + count($myEmergencies); ?></span>
                <?php endif; ?>
            </a>
            <a href="#" class="nav-link-custom" data-target="section-role">
                <i class="bi bi-layers"></i> <?php echo ucfirst($role); ?> Area
            </a>
            <a href="#" class="nav-link-custom" data-target="section-notifications">
                <i class="bi bi-bell"></i> Notifications
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-warning text-dark rounded-pill ms-auto nav-notif-badge"><?php echo $unreadCount; ?></span>
                <?php endif; ?>
            </a>
            <div class="mt-5 px-4 pt-4 border-top">
                <p class="small text-muted text-uppercase fw-bold mb-3">Quick Actions</p>
                <button class="btn btn-outline-primary w-100 rounded-pill btn-sm mb-2" data-bs-toggle="modal" data-bs-target="#searchModal">
                    <i class="bi bi-search me-1"></i> Patient Lookup
                </button>
                <a href="/api/auth/logout" class="btn btn-light w-100 rounded-pill btn-sm text-danger mt-3">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </a>
            </div>
        </nav>
    </div>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div class="d-flex align-items-center">
                <button class="btn btn-light rounded-pill p-2 me-3 d-lg-none" onclick="toggleSidebar()">
                    <i class="bi bi-list fs-4"></i>
                </button>
                <div>
                    <h3 class="fw-bold mb-1">Welcome, <?php echo htmlspecialchars($profile['name'] ?? 'Staff Member'); ?></h3>
                    <p class="text-muted mb-0"><i class="bi bi-shield-check text-success me-1"></i> Logged in as <span class="fw-bold text-dark"><?php echo ucfirst($role); ?></span></p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <p class="small text-muted mb-0"><?php echo date('l, M d'); ?></p>
                    <p class="small fw-bold mb-0"><?php echo date('H:i'); ?> GMT</p>
                </div>
                <div class="bg-white rounded-circle p-1 shadow-sm border">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($profile['name'] ?? 'Staff'); ?>&background=random" class="rounded-circle" width="45" height="45">
                </div>
            </div>
        </header>

        <div id="section-queue" class="dashboard-section animate-fade-in">
            <?php if (!empty($myEmergencies)): ?>
                <div class="row g-4 mb-4">
                    <div class="col-12">
                        <div class="alert alert-danger border-0 shadow-lg rounded-5 p-4 d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="bg-white text-danger rounded-circle p-3 d-flex align-items-center justify-content-center shadow-sm me-4" style="width: 60px; height: 60px;">
                                    <i class="bi bi-lightning-charge-fill fs-2"></i>
                                </div>
                                <div>
                                    <h4 class="fw-bold mb-1">Emergency Requests Assigned!</h4>
                                    <p class="mb-0 opacity-75">You have <?php echo count($myEmergencies); ?> urgent cases requiring immediate attention.</p>
                                </div>
                            </div>
                            <button class="btn btn-light rounded-pill px-4 fw-bold shadow-sm" onclick="window.scrollTo({top: 500, behavior: 'smooth'})">Go to Emergency Desk</button>
                        </div>
                    </div>
                </div>

                <div class="card p-4 border-0 shadow-sm mb-5 border-start border-danger border-4">
                    <h5 class="fw-bold mb-4 text-danger"><i class="bi bi-activity me-2"></i>Urgent Emergency Tasks</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr><th>Time</th><th>Patient/Reporter</th><th>Type</th><th>Location</th><th>Status</th><th>Actions</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach($myEmergencies as $e): 
                                    $readableType = str_replace(['_', 'bites', 'attacks', 'emergencies'], [' ', 'bite', 'attack', 'emergency'], $e['emergency_type'] ?? 'General');
                                ?>
                                    <tr class="table-danger-soft">
                                        <td class="fw-bold text-danger"><?php echo date('H:i', strtotime($e['created_at'])); ?></td>
                                        <td>
                                            <div class="fw-bold"><?php 
                                                $reporterName = $e['reporter']['name'] ?? ($profilesMap[$e['reporter_id']] ?? 'Patient');
                                                echo htmlspecialchars($reporterName); 
                                            ?></div>
                                            <small class="text-muted extra-small">ID: <?php echo substr($e['reporter_id'], 0, 8); ?></small>
                                        </td>
                                        <td><span class="badge bg-danger text-uppercase p-2 rounded-3 small"><?php echo htmlspecialchars($readableType); ?></span></td>
                                        <td><code class="text-primary bg-light px-2 py-1 rounded small"><?php echo htmlspecialchars($e['ghana_post_gps'] ?? $e['location'] ?? 'N/A'); ?></code></td>
                                        <td>
                                            <span class="badge bg-<?php echo ($e['status'] === 'pending') ? 'warning text-dark' : 'info'; ?> rounded-pill px-3">
                                                <?php echo ucfirst($e['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <?php if($e['status'] === 'pending'): ?>
                                                    <button class="btn btn-danger btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick='openDispatchEmergencyModal(<?php echo json_encode($e); ?>)'>
                                                        <i class="bi bi-truck me-1"></i> Dispatch
                                                    </button>
                                                <?php else: ?>
                                                    <button class="btn btn-success btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="resolveEmergency('<?php echo $e['id']; ?>', this)">
                                                        <i class="bi bi-check-lg me-1"></i> Resolve
                                                    </button>
                                                    <?php if($role === 'dispatch_rider' && empty($e['escalation_required'])): ?>
                                                        <button class="btn btn-dark btn-sm rounded-pill px-3 fw-bold shadow-sm" onclick="escalateToAmbulance('<?php echo $e['id']; ?>', this)">
                                                            <i class="bi bi-megaphone me-1"></i> Escalate
                                                        </button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card p-0 border-0 shadow-sm overflow-hidden mb-5">
                <div class="bg-white p-4 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">Assigned Task Queue</h5>
                    <span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo count($roleTasks) + count($tasks); ?> Active</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Patient</th>
                                <th>Task Details</th>
                                <th>Priority</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $allActiveTasks = array_merge($tasks, $roleTasks);
                            if (empty($allActiveTasks)): 
                            ?>
                                <tr><td colspan="4" class="text-center py-5 text-muted">No pending tasks for your current role.</td></tr>
                            <?php endif; foreach($allActiveTasks as $t): 
                                $canProcess = true; // Simplified for this view
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center gap-3">
                                        <div class="bg-light rounded-circle p-2 text-primary fw-bold" style="width: 35px; height: 35px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem;">
                                            <?php echo substr($t['patient']['name'] ?? 'P', 0, 1); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($t['patient']['name'] ?? 'Patient'); ?></div>
                                            <div class="extra-small text-muted">ID: <?php echo substr($t['patient_id'] ?? 'unknown', 0, 8); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    if ($role === 'nurse' && isset($t['appointment_date'])) {
                                        echo "Vitals Collection: <span class='badge bg-light text-dark fw-normal'>" . htmlspecialchars($t['appointment_type'] ?? 'General') . "</span>";
                                        echo "<div class='small text-muted'>" . htmlspecialchars($t['reason'] ?? 'Routine Check') . "</div>";
                                    } elseif ($role === 'pharmacist' && isset($t['medication_name'])) {
                                        $drugLabel = !empty($t['medication_name']) ? htmlspecialchars($t['medication_name']) : 'Medication';
                                        echo "Dispense: <span class='fw-bold'>{$drugLabel}</span> &times; <span class='badge bg-primary rounded-pill'>" . ($t['quantity'] ?? 1) . "</span>";
                                        echo "<div class='extra-small text-muted'>" . htmlspecialchars(($t['dosage'] ?? '') . " | " . ($t['frequency'] ?? '')) . "</div>";
                                    } elseif ($role === 'technician' && isset($t['test_name'])) {
                                        echo "Record Result: <span class='fw-bold'>" . htmlspecialchars($t['test_name'] ?? 'Lab Test') . "</span>";
                                    }
                                    ?>
                                </td>
                                <td><span class="badge bg-danger-soft text-danger rounded-pill px-3">High Priority</span></td>
                                <td class="text-end pe-4">
                                    <?php if (isset($t['appointment_date']) && $role === 'nurse' && in_array($t['patient_id'], $vitalsPatients)): ?>
                                        <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> Recorded</span>
                                    <?php elseif (isset($t['appointment_date']) && $role === 'nurse'): ?>
                                        <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#vitalsModal" onclick="setPatientId('<?php echo $t['patient_id']; ?>')">Process Vitals</button>
                                    <?php elseif (isset($t['medication_name']) && $role === 'pharmacist'): ?>
                                        <button class="btn btn-sm btn-success rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#dispenseModal" onclick="setPrescriptionId('<?php echo $t['id']; ?>')">Dispense</button>
                                    <?php elseif (isset($t['test_name']) && $role === 'technician'): ?>
                                        <button class="btn btn-sm btn-info text-white rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#labResultModal" onclick="setRequestId('<?php echo $t['id']; ?>')">Add Result</button>
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
                 <div class="col-12">
                     <div class="card p-5 text-center border-0 shadow-sm rounded-5">
                         <i class="bi bi-stack display-1 text-primary opacity-25 mb-4"></i>
                         <h3>Role Specialized Workspace</h3>
                         <p class="text-muted">Specialized tools and data management for the <?php echo ucfirst($role); ?> department.</p>
                     </div>
                 </div>
             </div>
        </div>

        <div id="section-notifications" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm rounded-4">
                <h5 class="fw-bold mb-4"><i class="bi bi-bell-fill me-2 text-primary"></i>Alert Center</h5>
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-bell-slash display-4 d-block mb-3"></i>
                        <p>No new notifications at this time.</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($notifications as $n):
                            $bgClass = (empty($n['is_read'])) ? 'bg-light border-primary' : 'bg-white text-muted';
                        ?>
                            <div class="list-group-item border-start border-4 mb-3 rounded-4 shadow-sm p-4 <?php echo $bgClass; ?>"
                                 <?php if(empty($n['is_read'])) echo 'onclick="markNotificationRead(this, \''.$n['id'].'\')"' ?>>
                                <div class="d-flex justify-content-between align-items-center">
                                    <p class="mb-1 small <?php echo empty($n['is_read']) ? 'fw-bold text-dark' : ''; ?>"><?php echo htmlspecialchars($n['message']); ?></p>
                                    <small class="extra-small opacity-75"><?php echo date('H:i', strtotime($n['created_at'])); ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODALS -->
    <div class="modal fade" id="dispatchEmergencyModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header bg-danger text-white border-0 py-3">
                    <h5 class="modal-title fw-bold mb-0"><i class="bi bi-lightning-fill me-2"></i>Emergency Dispatch</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="dispatchEmergencyForm">
                        <input type="hidden" name="emergency_id" id="dispatch_emerg_id">
                        <div id="dispatch_info_banner" class="alert alert-warning border-0 rounded-4 mb-4 small d-none">
                            <i class="bi bi-info-circle-fill me-2"></i> Supply kits for this emergency type:
                            <div id="suggested_items_list" class="fw-bold mt-2"></div>
                            <div class="extra-small mt-1 opacity-75">* Items will be billed once help arrives and supplies are used.</div>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Assigned Unit</label>
                            <div class="p-3 bg-light rounded-4 border"><span id="dispatch_team_label" class="fw-bold text-primary"></span></div>
                        </div>
                        <div class="mb-4">
                            <label class="small fw-bold text-muted mb-1">Dispatch Notes</label>
                            <textarea name="dispatch_notes" id="dispatch_notes" class="form-control rounded-4 p-3 border-light bg-light" rows="3"></textarea>
                        </div>
                        <button type="button" class="btn btn-danger w-100 rounded-pill py-3 fw-bold" onclick="submitEmergencyDispatch()">Rapid Dispatch Help</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="searchModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="fw-bold mb-0">Patient Search</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="text" id="patientSearchInput" class="form-control rounded-pill border-light bg-light px-4 py-2 mb-4" placeholder="Type name or ID...">
                    <div id="searchResults" class="list-group list-group-flush"></div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const emergencyAssetsMap = {
            'car_and_motor_accident': { type: 'Ambulance Team', items: [] },
            'labour': { type: 'Ambulance Team', items: [] },
            'sudden_consciousness_loss': { type: 'Ambulance Team', items: [] },
            'breathing_difficulty': { type: 'Ambulance Team', items: [] },
            'cardiac_emergencies': { type: 'Dispatch Rider', items: ['Aspirin', 'Oxygen', 'Defibrillator', 'Nitroglycerin'] },
            'diabetic_emergencies': { type: 'Dispatch Rider', items: ['Glucose gel', 'Glucagon injection', 'Insulin'] },
            'asthmatic_attacks': { type: 'Dispatch Rider', items: ['Ventolin Inhaler', 'Nebulizer Set'] },
            'snake_bite': { type: 'Dispatch Rider', items: ['Snake Antivenom', 'Immobilisation Bandage', 'Splint', 'Paracetamol'] },
            'dog_bite': { type: 'Dispatch Rider', items: ['Antibiotic Cream', 'Rabies Vaccine', 'Tetanus Shot'] },
            'scorpion_bite': { type: 'Dispatch Rider', items: ['Scorpion Antivenom', 'Paracetamol', 'Lidocaine', 'Antihistamine'] }
        };

        function openDispatchEmergencyModal(e) {
            document.getElementById('dispatch_emerg_id').value = e.id;
            const assetInfo = emergencyAssetsMap[e.emergency_type] || { type: 'Emergency Response', items: [] };
            document.getElementById('dispatch_team_label').innerText = assetInfo.type;
            const banner = document.getElementById('dispatch_info_banner');
            const itemsList = document.getElementById('suggested_items_list');
            
            if (assetInfo.items.length > 0) {
                banner.classList.remove('d-none');
                itemsList.innerHTML = assetInfo.items.map(i => `<span class="badge bg-white text-danger border border-danger me-1 mb-1">${i}</span>`).join('');
                document.getElementById('dispatch_notes').value = "Assigned for delivery of life-saving medical supplies.";
            } else {
                banner.classList.add('d-none');
                document.getElementById('dispatch_notes').value = "Ambulance team dispatched for critical transport.";
            }
            new bootstrap.Modal(document.getElementById('dispatchEmergencyModal')).show();
        }

        async function submitEmergencyDispatch() {
            const fd = new FormData(document.getElementById('dispatchEmergencyForm'));
            const res = await fetch('/api/emergency/dispatch', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { alert("Dispatch success!"); location.reload(); }
            else { alert("Dispatch Error: " + data.error); }
        }

        async function resolveEmergency(id, btn) {
            if (!confirm("Are you sure this emergency is resolved?")) return;
            const res = await fetch('/api/emergency/resolve', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            if (data.success) { btn.closest('tr').remove(); }
            else { alert("Error: " + data.error); }
        }

        async function escalateToAmbulance(id, btn) {
            if (!confirm("Request critical ambulance transport for this case?")) return;
            const res = await fetch('/api/emergency/escalate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const data = await res.json();
            if (data.success) { alert("Escalated to Ambulance!"); location.reload(); }
            else { alert("Error: " + data.error); }
        }

        document.querySelectorAll('.nav-link-custom[data-target]').forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                document.querySelectorAll('.dashboard-section').forEach(s => s.classList.add('d-none'));
                document.getElementById(link.getAttribute('data-target')).classList.remove('d-none');
                document.querySelectorAll('.nav-link-custom').forEach(l => l.classList.remove('active'));
                link.classList.add('active');
            });
        });

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.sidebar-overlay').classList.toggle('show');
        }

        function setPatientId(id) { /* for vitals */ }
        function setPrescriptionId(id) { /* for dispense */ }
        function setRequestId(id) { /* for lab */ }
    </script>
</body>
</html>
