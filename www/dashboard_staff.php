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
    $res = $sb->request('GET', '/rest/v1/prescriptions?status=eq.pending&order=created_at.asc');
    $roleTasks = ($res['status'] === 200) ? $res['data'] : [];
    $invRes = $sb->request('GET', '/rest/v1/drug_inventory?order=drug_name.asc');
    $roleData['inventory'] = ($invRes['status'] === 200) ? $invRes['data'] : [];
} elseif ($role === 'technician') {
    $res = $sb->request('GET', '/rest/v1/lab_requests?status=eq.pending&order=created_at.asc');
    $roleTasks = ($res['status'] === 200) ? $res['data'] : [];
}

// Combine all tasks. Use ID as key to deduplicate (assigned tasks override dept ones)
$tasksMap = [];
foreach ($deptTasks as $t) $tasksMap[$t['id']] = $t;
foreach ($assignedTasks as $t) $tasksMap[$t['id']] = $t;
foreach ($roleTasks as $t) $tasksMap[$t['id']] = $t;

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .sidebar { width: 280px; height: 100vh; position: fixed; left: 0; top: 0; background: white; border-right: 1px solid #eee; z-index: 1000; }
        .main-content { margin-left: 280px; padding: 40px; background: #f8fafc; min-height: 100vh; }
        .nav-link-custom { display: flex; align-items: center; padding: 12px 20px; color: #64748b; text-decoration: none; border-radius: 12px; margin-bottom: 8px; transition: all 0.3s; }
        .nav-link-custom:hover, .nav-link-custom.active { background: var(--primary-soft); color: var(--primary-color); }
        .nav-link-custom i { margin-right: 12px; font-size: 1.2rem; }
    </style>
</head>
<body>
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
                <?php if (!empty($notifications)): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo count($notifications); ?></span>
                <?php endif; ?>
            </a>
            <hr class="my-3">
            <form action="/emr.php" method="GET" class="px-2 mb-3">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0 rounded-start-pill"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" name="patient_id" class="form-control border-start-0 rounded-end-pill" placeholder="Search Patient ID..." required>
                </div>
                <small class="text-muted extra-small mt-1 d-block text-center">Enter UUID or scan card</small>
            </form>
            <hr class="my-4">
            <a href="/api/auth/logout.php" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($name); ?></h2>
                <p class="text-muted mb-0">Role: <span class="text-capitalize fw-bold text-primary"><?php echo htmlspecialchars($role); ?></span></p>
            </div>
            <div class="d-flex align-items-center">
                <?php if (!empty($notifications)): ?>
                    <div class="dropdown me-4">
                        <button class="btn btn-light bg-white border-0 rounded-circle shadow-sm position-relative p-2" data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5 text-secondary"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="padding: 0.35em 0.5em;">
                                <?php echo count($notifications); ?>
                            </span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-3 rounded-4" style="width: 320px;">
                            <h6 class="fw-bold mb-3">Department Alerts</h6>
                            <?php foreach($notifications as $n): ?>
                                <div class="p-2 border-bottom border-light mb-2">
                                    <p class="small mb-1 text-dark"><?php echo htmlspecialchars($n['message']); ?></p>
                                    <small class="text-muted extra-small"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div id="section-queue" class="dashboard-section">
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
                            <?php if (empty($tasks)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">No pending tasks in your queue.</td></tr>
                            <?php endif; foreach ($tasks as $t): 
                                $isAssigned = ($t['assigned_to'] === $user['id']);
                            ?>
                            <tr class="<?php echo $isAssigned ? 'table-primary-soft' : ''; ?>">
                                <td>#<?php echo substr($t['id'], 0, 5); ?></td>
                                <td>
                                    <?php 
                                    if (isset($t['appointment_date'])) {
                                        // It's an appointment
                                        if ($isAssigned) {
                                            echo "<span class='fw-bold text-primary'><i class='bi bi-person-check-fill me-1'></i> [ASSIGNED]</span> Record vitals for " . htmlspecialchars($t['patient']['name'] ?? 'Patient');
                                            echo "<div class='small text-muted'>" . htmlspecialchars($t['reason'] ?? 'Routine Check') . "</div>";
                                        } else {
                                            echo "<i class='bi bi-shield-lock me-1'></i> Departmental Appointment Request (ID: " . substr($t['patient_id'] ?? 'unknown', 0, 8) . ")";
                                            echo "<div class='extra-small text-muted'>Awaiting admin assignment for full details.</div>";
                                        }
                                    } elseif ($role === 'pharmacist' && isset($t['medication_details'])) {
                                        echo "Dispense: " . htmlspecialchars($t['medication_details']);
                                    } elseif ($role === 'technician') {
                                        echo "Test: " . htmlspecialchars($t['test_name']);
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($isAssigned): ?>
                                        <span class="badge bg-danger rounded-pill px-3">Priority</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted rounded-pill px-3">Restricted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($isAssigned): ?>
                                        <?php if ($role === 'nurse'): ?>
                                            <button class="btn btn-sm btn-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#vitalsModal" onclick="setPatientId('<?php echo $t['patient_id']; ?>')">Process Vitals</button>
                                        <?php elseif ($role === 'pharmacist'): ?>
                                            <button class="btn btn-sm btn-success rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#dispenseModal" onclick="setPrescriptionId('<?php echo $t['id']; ?>')">Dispense</button>
                                        <?php elseif ($role === 'technician'): ?>
                                            <button class="btn btn-sm btn-info text-white rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#labResultModal" onclick="setRequestId('<?php echo $t['id']; ?>')">Result</button>
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
                            <h5 class="fw-bold mb-4">Drug Inventory</h5>
                            <div class="table-responsive">
                                <table class="table align-middle">
                                    <thead><tr><th>Drug Name</th><th>Stock</th><th>Expiry</th><th>Status</th></tr></thead>
                                    <tbody>
                                        <?php if (empty($roleData['inventory'])): ?>
                                            <tr><td colspan="4" class="text-center py-3">No inventory data available.</td></tr>
                                        <?php endif; foreach ($roleData['inventory'] as $inv): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($inv['drug_name']); ?></td>
                                                <td><?php echo $inv['stock_count']; ?> units</td>
                                                <td><?php echo date('M d, Y', strtotime($inv['expiry_date'])); ?></td>
                                                <td>
                                                    <?php if ($inv['stock_count'] < 10): ?>
                                                        <span class="badge bg-danger">Low Stock</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">In Stock</span>
                                                    <?php endif; ?>
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
                        <div class="card border-0 shadow-sm p-4">
                            <h5 class="fw-bold mb-4">Lab Workload Overview</h5>
                            <p class="text-muted">Currently processing <?php echo count($tasks); ?> pending lab requests.</p>
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
                        <?php foreach ($notifications as $n): ?>
                            <div class="list-group-item border-0 border-start border-4 border-primary ps-3 mb-3 bg-light rounded-3">
                                <p class="mb-1 fw-bold small text-dark"><?php echo htmlspecialchars($n['message']); ?></p>
                                <small class="text-muted"><i class="bi bi-clock me-1"></i><?php echo date('M d, Y \a\t H:i', strtotime($n['created_at'])); ?></small>
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

        <!-- MODALS -->
        <?php if ($role === 'nurse'): ?>
        <div class="modal fade" id="vitalsModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="/api/consultation/save" method="POST" class="modal-content border-0 shadow">
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
        <?php elseif ($role === 'pharmacist'): ?>
        <div class="modal fade" id="dispenseModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="/api/prescriptions/dispense" method="POST" class="modal-content border-0 shadow">
                    <input type="hidden" name="prescription_id" id="prescription_id_field">
                    <div class="modal-header border-0"><h5 class="fw-bold">Dispense Medication</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body p-4">
                        <div class="mb-3"><label class="small text-muted">Batch Number</label><input type="text" name="batch_number" class="form-control rounded-pill px-3" required></div>
                        <button type="submit" class="btn btn-success w-100 rounded-pill">Confirm Dispense</button>
                    </div>
                </form>
            </div>
        </div>
        <?php elseif ($role === 'technician'): ?>
        <div class="modal fade" id="labResultModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="/api/lab/submit" method="POST" class="modal-content border-0 shadow">
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
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    navigateTo(link.getAttribute('data-target'));
                });
            });
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

        function setPatientId(id) { document.getElementById('patient_id_field').value = id; }
        function setPrescriptionId(id) { document.getElementById('prescription_id_field').value = id; }
        function setRequestId(id) { document.getElementById('request_id_field').value = id; }
    </script>
</body>
</html>
