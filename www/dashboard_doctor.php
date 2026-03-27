<?php
// Doctor Dashboard - Kobby Moore Hospital
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'doctor') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$name = $user['user_metadata']['name'] ?? 'Doctor';

$sb = new Supabase();

// 1. Fetch Today's Queue (Scheduled appointments for today)
$todayStart = date('Y-m-d\T00:00:00');
$todayEnd = date('Y-m-d\T23:59:59');
$queueRes = $sb->request('GET', '/rest/v1/appointments?appointment_date=gte.' . $todayStart . '&appointment_date=lte.' . $todayEnd . '&status=neq.completed&order=created_at.asc', null, true);
$queueRaw = ($queueRes['status'] === 200) ? $queueRes['data'] : [];
$queue = [];
foreach ($queueRaw as $q) {
    if (empty($q['assigned_to']) || $q['assigned_to'] === $userId) {
        $queue[] = $q;
    }
}

// 2. Fetch My Schedule (All appointments assigned to this doctor)
$scheduleRes = $sb->request('GET', '/rest/v1/appointments?assigned_to=eq.' . $userId . '&order=appointment_date.asc', null, true);
$mySchedule = ($scheduleRes['status'] === 200) ? $scheduleRes['data'] : [];

// 3. Fetch Lab Results (Ordered by this doctor)
$labsRes = $sb->request('GET', '/rest/v1/lab_requests?doctor_id=eq.' . $userId . '&order=created_at.desc');
$labResults = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

// 4. Fetch Prescriptions (Created by this doctor)
$rxRes = $sb->request('GET', '/rest/v1/prescriptions?doctor_id=eq.' . $userId . '&order=created_at.desc');
$prescriptions = ($rxRes['status'] === 200) ? $rxRes['data'] : [];

// 5. Fetch Notifications
$notificationsRes = $sb->request('GET', '/rest/v1/notifications?user_id=eq.' . $userId . '&order=created_at.desc&limit=5', null, true);
$notifications = ($notificationsRes['status'] === 200) ? $notificationsRes['data'] : [];
$unreadCount = count(array_filter($notifications, fn($n) => empty($n['is_read'])));

// Fetch Drugs for Emergency Prescription
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];

// 6. Fetch Assigned Emergencies
$myEmergenciesRes = $sb->request('GET', '/rest/v1/emergencies?assigned_to=eq.' . $userId . '&status=in.(active,pending,assigned)&select=*,reporter:reporter_id(name)', null, true);
$myEmergencies = ($myEmergenciesRes['status'] === 200) ? $myEmergenciesRes['data'] : [];

// Stats
$waitingCount = 0;
foreach($queue as $q) if($q['status'] === 'scheduled') $waitingCount++;
$seenToday = count($mySchedule); // Simplification

// 7. Humanize IDs: Fetch all profiles for Name Mapping
$profilesMap = [];
$pMapRes = $sb->request('GET', '/rest/v1/profiles?select=id,name', null, true);
if ($pMapRes['status'] === 200) {
    foreach ($pMapRes['data'] as $pr) $profilesMap[$pr['id']] = $pr['name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .dashboard-section { min-height: 400px; }
    </style>
</head>
<body>
    <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
    <div class="sidebar p-4">
        <div class="d-flex align-items-center mb-5">
            <img src="/assets/img/logo.png" alt="KM Logo" style="width: 36px; height: 36px; object-fit: contain;" class="me-2 rounded-3 shadow-sm">
            <h4 class="fw-bold mb-0 text-white">Kobby Moore Hospital</h4>
        </div>

        <nav id="sidebarMenu">
            <a href="#" class="nav-link-custom active" data-target="section-queue"><i class="bi bi-people-fill"></i> Patient Queue</a>
            <a href="#" class="nav-link-custom" data-target="section-schedule"><i class="bi bi-calendar-event"></i> My Schedule</a>
            <a href="#" class="nav-link-custom" data-target="section-consults"><i class="bi bi-file-earmark-medical-fill"></i> Consultations</a>
            <a href="#" class="nav-link-custom" data-target="section-prescripts"><i class="bi bi-capsule"></i> E-Prescriptions</a>
            <a href="#" class="nav-link-custom" data-target="section-labs"><i class="bi bi-clipboard-pulse"></i> Lab Results</a>
            <hr class="my-3">
            <div class="px-2 mb-3">
                <button class="btn btn-primary-soft text-primary w-100 rounded-pill d-flex align-items-center justify-content-center py-2" data-bs-toggle="modal" data-bs-target="#searchModal">
                    <i class="bi bi-search me-2"></i> Find Patient
                </button>
            </div>
            <hr class="my-4">
            <a href="/" class="nav-link-custom"><i class="bi bi-house"></i> Back to Home</a>
            <a href="/api/auth/logout" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
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

        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Dr. <?php echo htmlspecialchars($name); ?> 👩‍⚕️</h2>
                <p class="text-muted mb-0">You have <?php echo count($queue); ?> patients in the queue today.</p>
            </div>
            <div class="d-flex align-items-center">
                <div class="dropdown me-4">
                    <button class="btn btn-light bg-white border-0 rounded-circle shadow-sm position-relative p-2" data-bs-dropdown="dropdown">
                        <i class="bi bi-bell fs-5 text-secondary"></i>
                        <?php if ($unreadCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger top-notif-badge" style="padding: 0.35em 0.5em;">
                            <?php echo $unreadCount; ?>
                        </span>
                        <?php endif; ?>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-3 rounded-4" style="width: 320px;">
                        <h6 class="fw-bold mb-3">Clinical Alerts</h6>
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted small mb-0">No alerts at this time.</p>
                        <?php endif; ?>
                        <?php foreach($notifications as $n): 
                            $msg = $n['message'];
                            foreach($profilesMap as $pid => $pname) {
                                $msg = str_replace($pid, $pname, $msg);
                            }
                        ?>
                            <div class="p-2 border-bottom border-light mb-2 <?php echo empty($n['is_read']) ? 'bg-light rounded' : ''; ?>" <?php if(empty($n['is_read'])) echo 'onclick="markNotificationRead(this, \''.$n['id'].'\')" style="cursor: pointer;"' ?>>
                                <p class="small mb-1 <?php echo empty($n['is_read']) ? 'fw-bold text-dark' : 'text-muted'; ?>"><?php echo htmlspecialchars($msg); ?></p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted extra-small"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
                                    <?php if (($n['type'] ?? '') === 'emergency_handled_by_admin'): ?>
                                        <button class="btn btn-success btn-xs py-0 px-2 rounded-pill fw-bold extra-small"
                                                onclick="event.stopPropagation(); clearEmergencyTask('<?php echo $n['id']; ?>', this)">
                                            Clear
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="me-4 text-end">
                    <p class="mb-0 fw-bold"><?php echo htmlspecialchars($user['user_metadata']['department'] ?? 'OPD'); ?> Shift</p>
                    <span class="badge bg-success-soft text-success rounded-pill px-3">Active Now</span>
                </div>
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <?php if (isset($_GET['visit_finished'])): ?>
        <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4 d-flex align-items-center gap-3">
            <i class="bi bi-check-circle-fill fs-4"></i>
            <div>
                <strong>Consultation Complete!</strong> The patient record has been saved, prescriptions sent to pharmacy, and billing updated.
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
            <i class="bi bi-exclamation-circle-fill me-2"></i>
            <strong>Error:</strong> <?php echo htmlspecialchars($_GET['details'] ?? 'Could not save the consultation. Please try again.'); ?>
        </div>
        <?php endif; ?>

        <?php include 'components/health_tips.php'; ?>

        <!-- QUEUE SECTION -->
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
                                            <div class="fw-bold"><?php 
                                                $reporterName = $e['reporter']['name'] ?? $profilesMap[$e['reporter_id']] ?? 'Patient';
                                                echo htmlspecialchars($reporterName); 
                                            ?></div>
                                            <small class="text-muted extra-small">ID: <?php echo substr($e['reporter_id'], 0, 8); ?></small>
                                        </td>
                                        <td><code class="text-primary bg-light px-2 py-1 rounded"><?php echo htmlspecialchars($e['ghana_post_gps'] ?? $e['location'] ?? 'N/A'); ?></code></td>
                                        <td class="small"><?php echo htmlspecialchars($e['symptoms']); ?></td>
                                        <td>
                                            <button class="btn btn-danger btn-sm rounded-pill px-3 fw-bold" 
                                                    onclick='const cleanData = <?php 
                                                        $stripped = $e;
                                                        if (isset($stripped["symptoms"]) && strpos($stripped["symptoms"], "||VOICE_NOTE||") !== false) {
                                                            $parts = explode("||VOICE_NOTE||", $stripped["symptoms"]);
                                                            $stripped["symptoms"] = trim($parts[0]) . " (Voice Note Available)";
                                                        }
                                                        echo json_encode($stripped); 
                                                    ?>; openDispatchEmergencyModal(cleanData)'>
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

            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Waitlisted</h6>
                        <h2 class="fw-bold mb-0 text-primary"><?php echo sprintf('%02d', $waitingCount); ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">My Appointments</h6>
                        <h2 class="fw-bold mb-0 text-success"><?php echo sprintf('%02d', count($mySchedule)); ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Lab Orders</h6>
                        <h2 class="fw-bold mb-0 text-warning"><?php echo sprintf('%02d', count($labResults)); ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">My Prescriptions</h6>
                        <h2 class="fw-bold mb-0 text-danger"><?php echo sprintf('%02d', count($prescriptions)); ?></h2>
                    </div>
                </div>
            </div>

            <div class="card p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Active Patient Queue</h5>
                    <div class="d-flex gap-2">
                        <input type="text" class="form-control form-control-sm rounded-pill px-3" placeholder="Search patient...">
                        <button class="btn btn-primary btn-sm rounded-pill px-3">Refresh</button>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light border-0">
                            <tr>
                                <th class="border-0">Queue #</th>
                                <th class="border-0">Patient Name</th>
                                <th class="border-0">Vitals</th>
                                <th class="border-0">Status</th>
                                <th class="border-0">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($queue)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Queue is empty.</td></tr>
                            <?php endif; foreach ($queue as $index => $q): ?>
                            <tr>
                                <td class="fw-bold">#<?php echo sprintf('%03d', $index + 1); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-primary-soft text-primary rounded-circle me-2 d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px;">P</div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($profilesMap[$q['patient_id']] ?? ('Patient ' . substr($q['patient_id'], 0, 8))); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($q['reason']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><small>Waiting for Vitals...</small></td>
                                <td><span class="badge bg-warning-soft text-warning rounded-pill px-3"><?php echo htmlspecialchars($q['status']); ?></span></td>
                                <td>
                                    <a href="/consultation.php?patient_id=<?php echo $q['patient_id']; ?>" class="btn btn-primary btn-sm rounded-pill px-3">Start Call</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- PLACEHOLDERS FOR OTHER SECTIONS -->
        <!-- SCHEDULE SECTION -->
        <div id="section-schedule" class="dashboard-section d-none">
            <h5 class="fw-bold mb-4">My Appointments</h5>
            <div class="card border-0 shadow-sm p-4">
                <?php if (empty($mySchedule)): ?>
                    <p class="text-muted">No appointments assigned directly to you yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Date</th><th>Patient ID</th><th>Reason</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mySchedule as $s): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($s['appointment_date'])); ?></td>
                                        <td><?php echo substr($s['patient_id'], 0, 8); ?></td>
                                        <td><?php echo htmlspecialchars($s['reason']); ?></td>
                                        <td><span class="badge bg-success-soft text-success rounded-pill px-3"><?php echo $s['status']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div id="section-consults" class="dashboard-section d-none">
            <h5 class="fw-bold mb-4">Past Consultations</h5>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-folder2-open display-4 mb-3"></i>
                <p>Select a patient from the queue to start a consultation.</p>
            </div>
        </div>
        <!-- PRESCRIPTIONS SECTION -->
        <div id="section-prescripts" class="dashboard-section d-none">
            <h5 class="fw-bold mb-4">Electronic Prescriptions</h5>
            <div class="card border-0 shadow-sm p-4">
                <?php if (empty($prescriptions)): ?>
                    <p class="text-muted text-center py-4">You haven't created any prescriptions yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Created At</th><th>Patient ID</th><th>Medication</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prescriptions as $p): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y H:i', strtotime($p['created_at'])); ?></td>
                                        <td><?php echo substr($p['patient_id'], 0, 8); ?></td>
                                        <td><?php echo htmlspecialchars($p['medication_details']); ?></td>
                                        <td><span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo $p['status']; ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <!-- LABS SECTION -->
        <div id="section-labs" class="dashboard-section d-none">
            <h5 class="fw-bold mb-4">Diagnostic Lab Results</h5>
            <div class="card border-0 shadow-sm p-4">
                <?php if (empty($labResults)): ?>
                    <p class="text-muted text-center py-4">No lab results found for your requests.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead>
                                <tr><th>Requested At</th><th>Patient ID</th><th>Test</th><th>Status</th><th>Result</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($labResults as $lr): ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($lr['created_at'])); ?></td>
                                        <td><?php echo substr($lr['patient_id'], 0, 8); ?></td>
                                        <td><?php echo htmlspecialchars($lr['test_name']); ?></td>
                                        <td><span class="badge <?php echo ($lr['status'] === 'completed') ? 'bg-success-soft text-success' : 'bg-warning-soft text-warning'; ?> rounded-pill px-3"><?php echo $lr['status']; ?></span></td>
                                        <td><?php echo $lr['result_text'] ? '<span class="text-truncate d-inline-block" style="max-width: 150px;">'.htmlspecialchars($lr['result_text']).'</span>' : '<i class="text-muted small">Pending...</i>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

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
                            <select name="dispatch_type" class="form-select rounded-pill px-3" required id="doctor_dispatch_select">
                                <option value="ambulance">🚑 Ambulance (Critical Life Support)</option>
                                <option value="team">🚑 Response Team (Emergency Care)</option>
                                <option value="rider" selected>🏍️ Dispatch Rider (Meds/Supplies Only)</option>
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

    <!-- Bootstrap 5 JS Bundle -->
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

        document.addEventListener('DOMContentLoaded', () => {
            // CLEANUP: Remove stray Bootstrap modal-backdrop divs
            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';

            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    const targetId = link.getAttribute('data-target');
                    if (targetId) {
                        e.preventDefault();
                        navigateTo(targetId);
                        
                        // Auto-close sidebar on mobile
                        if (window.innerWidth < 992) {
                            toggleSidebar();
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

        function navigateTo(sectionId) {
            console.log('Navigating to:', sectionId);
            const target = document.getElementById(sectionId);
            if (!target) {
                console.error('Target section not found:', sectionId);
                return;
            }
            
            const sections = document.querySelectorAll('.dashboard-section');
            sections.forEach(sec => sec.classList.add('d-none'));
            target.classList.remove('d-none');
            
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            links.forEach(l => {
                l.classList.toggle('active', l.getAttribute('data-target') === sectionId);
            });
        }

        function markNotificationRead(el, id) {
            if (el.classList.contains('bg-light')) {
                fetch('/api/notifications/read?id=' + id, {method: 'POST'});
                el.style.transition = 'all 0.3s ease';
                el.style.opacity = '0';
                el.style.transform = 'scale(0.95)';
                setTimeout(() => el.remove(), 300);

                document.querySelectorAll('.top-notif-badge, .nav-notif-badge').forEach(badge => {
                    let count = (parseInt(badge.innerText) || 0) - 1;
                    if (count <= 0) badge.classList.add('d-none');
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
                const res = await fetch('/api/emergency/clear_task', { method: 'POST', body: fd });
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
                            if (count <= 0) badge.classList.add('d-none');
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

        // Emergency Dispatch JS
        function openDispatchEmergencyModal(e) {
            document.getElementById('dispatch_emerg_id').value = e.id;
            const dispatchModalEl = document.getElementById('dispatchEmergencyModal');
            const typeSelect = document.getElementById('doctor_dispatch_select');
            const medSection = document.getElementById('riderMedicationSection');
            
            typeSelect.onchange = () => {
                if(typeSelect.value === 'rider') medSection.classList.remove('d-none');
                else medSection.classList.add('d-none');
            };

            // Pre-fill if rider selected by default
            if(typeSelect.value === 'rider') medSection.classList.remove('d-none');

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
            const res = await fetch('/api/emergency/dispatch', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                alert("Dispatch initiated successfully!");
                silentRefresh();
            } else {
                alert("Dispatch Error: " + data.error);
            }
        }
    </script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
