<?php
// Doctor Dashboard - GGHMS
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

// Stats
$waitingCount = 0;
foreach($queue as $q) if($q['status'] === 'scheduled') $waitingCount++;
$seenToday = count($mySchedule); // Simplification
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - GGHMS</title>
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
                <h2 class="fw-bold mb-1">Dr. <?php echo htmlspecialchars($name); ?> 👩‍⚕️</h2>
                <p class="text-muted mb-0">You have <?php echo count($queue); ?> patients in the queue today.</p>
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
                        <h6 class="fw-bold mb-3">Clinical Alerts</h6>
                        <?php if (empty($notifications)): ?>
                            <p class="text-muted small mb-0">No alerts at this time.</p>
                        <?php endif; ?>
                        <?php foreach($notifications as $n): ?>
                            <div class="p-2 border-bottom border-light mb-2 <?php echo empty($n['is_read']) ? 'bg-light rounded' : ''; ?>" <?php if(empty($n['is_read'])) echo 'onclick="markNotificationRead(this, \''.$n['id'].'\')" style="cursor: pointer;"' ?>>
                                <p class="small mb-1 <?php echo empty($n['is_read']) ? 'fw-bold text-dark' : 'text-muted'; ?>"><?php echo htmlspecialchars($n['message']); ?></p>
                                <small class="text-muted extra-small"><?php echo date('M d, H:i', strtotime($n['created_at'])); ?></small>
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

        <!-- QUEUE SECTION -->
        <div id="section-queue" class="dashboard-section">
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
                                            <div class="fw-bold">Patient <?php echo substr($q['patient_id'], 0, 8); ?></div>
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

                document.querySelectorAll('.top-notif-badge').forEach(badge => {
                    let count = parseInt(badge.innerText) - 1;
                    if (count <= 0) badge.remove();
                    else badge.innerText = count;
                });
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
