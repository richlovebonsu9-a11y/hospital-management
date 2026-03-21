<?php
// Patient Dashboard - GGHMS
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'patient') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$metadata = $user['user_metadata'] ?? [];
$name = $metadata['name'] ?? 'Patient';

$sb = new Supabase();

// 1. Fetch Appointments (join assigned staff so we can show their name)
$apptsRes = $sb->request('GET', '/rest/v1/appointments?patient_id=eq.' . $userId . '&select=*,assigned_staff:assigned_to(name)&order=appointment_date.asc', null, true);
$appointments = ($apptsRes['status'] === 200) ? $apptsRes['data'] : [];

// 2. Fetch Vitals (Health Summary)
$vitalsRes = $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . $userId . '&select=*&order=recorded_at.desc&limit=1', null, true);
$latestVitals = ($vitalsRes['status'] === 200 && !empty($vitalsRes['data'])) ? $vitalsRes['data'][0] : null;

// 3. Fetch Lab Results
$labsRes = $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . $userId . '&select=*&order=created_at.desc', null, true);
$labResults = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

// 4. Fetch Prescriptions (Using direct patient_id filter)
$rxRes = $sb->request('GET', '/rest/v1/prescriptions?patient_id=eq.' . $userId . '&select=*&order=created_at.desc', null, true);
$prescriptions = ($rxRes['status'] === 200) ? $rxRes['data'] : [];

// 5. Fetch Pending Guardian Links
$pendingLinksRes = $sb->request('GET', '/rest/v1/guardians?patient_id=eq.' . $userId . '&status=eq.pending&select=*,guardian:guardian_id(*)', null, true);
$pendingLinks = ($pendingLinksRes['status'] === 200) ? $pendingLinksRes['data'] : [];

// 6. Fetch System Notifications (New Assignments, etc.)
$notificationsRes = $sb->request('GET', '/rest/v1/notifications?user_id=eq.' . $userId . '&order=created_at.desc&limit=5', null, true);
$notifications = ($notificationsRes['status'] === 200) ? $notificationsRes['data'] : [];

// Find next appointment for the overview card
$nextAppt = null;
foreach ($appointments as $a) {
    if ($a['status'] === 'scheduled' && strtotime($a['appointment_date']) >= strtotime('today')) {
        $nextAppt = $a;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Dashboard - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .nav-link-custom {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #64748b;
            text-decoration: none;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.3s;
        }
        .nav-link-custom:hover, .nav-link-custom.active {
            background: var(--primary-soft);
            color: var(--primary-color);
        }
        .nav-link-custom i {
            margin-right: 12px;
            font-size: 1.2rem;
        }
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
            <a href="#" class="nav-link-custom active" data-target="section-dashboard"><i class="bi bi-grid-fill"></i> Dashboard</a>
            <a href="#" class="nav-link-custom" data-target="section-appointments"><i class="bi bi-calendar-check"></i> Appointments</a>
            <a href="#" class="nav-link-custom" data-target="section-records"><i class="bi bi-file-earmark-medical"></i> Medical Records</a>
            <a href="#" class="nav-link-custom" data-target="section-invoices"><i class="bi bi-credit-card"></i> Invoices</a>
            <a href="#" class="nav-link-custom" data-target="section-profile"><i class="bi bi-person"></i> Profile</a>
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
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($name); ?>! 👋</h2>
                <p class="text-muted mb-0">Welcome back to your health portal.</p>
            </div>
            <div class="d-flex align-items-center">
                <a href="/emergency" class="btn btn-danger rounded-pill px-4 me-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> EMERGENCY</a>
                <?php if (!empty($notifications)): ?>
                    <div class="dropdown me-4">
                        <button class="btn btn-light bg-white border-0 rounded-circle shadow-sm position-relative p-2" data-bs-toggle="dropdown">
                            <i class="bi bi-bell fs-5 text-secondary"></i>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="padding: 0.35em 0.5em;">
                                <?php echo count($notifications); ?>
                            </span >
                        </button>
                        <div class="dropdown-menu dropdown-menu-end border-0 shadow-lg p-3 rounded-4" style="width: 320px;">
                            <h6 class="fw-bold mb-3">Notifications</h6>
                            <?php foreach($notifications as $n): ?>
                                <div class="p-2 border-bottom border-light mb-2">
                                    <p class="small mb-1"><?php echo htmlspecialchars($n['message']); ?></p>
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

        <!-- Feedback Alerts -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success border-0 rounded-4 shadow-sm mb-4">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php 
                    echo ($_GET['success'] === 'link_approved') ? 'Relationship request approved successfully!' : 
                         (($_GET['success'] === 'link_declined') ? 'Relationship request declined.' : 'Success!');
                ?>
            </div>
        <?php endif; ?>
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger border-0 rounded-4 shadow-sm mb-4">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <?php 
                    echo ($_GET['error'] === 'update_failed') ? 'Failed to update relationship status. Please try again.' : 'An error occurred.';
                ?>
            </div>
        <?php endif; ?>

        <!-- Guardian Link Requests -->
        <?php foreach ($pendingLinks as $link): ?>
            <div class="alert alert-primary border-0 rounded-4 shadow-sm mb-4 p-4 d-flex align-items-center justify-content-between">
                <div>
                    <h6 class="fw-bold mb-1"><i class="bi bi-person-plus-fill me-2"></i> Relationship Request</h6>
                    <p class="mb-0 text-secondary">
                        <strong><?php echo htmlspecialchars($link['guardian']['name'] ?? 'Someone'); ?></strong> wants to link to your profile as a <strong><?php echo htmlspecialchars($link['relationship']); ?></strong>.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <form action="/api/guardian/manage.php" method="POST">
                        <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                        <input type="hidden" name="action" value="approve">
                        <button type="submit" class="btn btn-success rounded-pill px-4 btn-sm">Approve</button>
                    </form>
                    <form action="/api/guardian/manage.php" method="POST">
                        <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                        <input type="hidden" name="action" value="decline">
                        <button type="submit" class="btn btn-outline-danger rounded-pill px-4 btn-sm">Decline</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>

        <div id="section-dashboard" class="dashboard-section">
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="bi bi-calendar2-plus"></i>
                        </div>
                        <h5 class="fw-bold">Next Appointment</h5>
                        <?php if ($nextAppt): ?>
                            <h6 class="fw-bold text-primary mb-1"><?php echo date('M d, Y', strtotime($nextAppt['appointment_date'])); ?></h6>
                            <p class="text-muted small mb-0"><?php echo htmlspecialchars($nextAppt['department']); ?></p>
                            <p class="text-muted extra-small">Status: <span class="text-capitalize"><?php echo $nextAppt['status']; ?></span></p>
                        <?php else: ?>
                            <p class="text-muted">You have no upcoming appointments scheduled.</p>
                        <?php endif; ?>
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="navigateTo('section-appointments')">Book Now</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3" style="background: #fff4e6; color: #fca311;">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                        <h5 class="fw-bold">Health Summary</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Blood Group</span>
                            <span class="fw-bold">O+</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Last Vitals</span>
                            <span class="fw-bold"><?php echo $latestVitals ? date('M d, Y', strtotime($latestVitals['recorded_at'])) : 'N/A'; ?></span>
                        </div>
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="location.href='/emr.php'">View Medical Records</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3" style="background: #eef2ff; color: #4f46e5;">
                            <i class="bi bi-wallet2"></i>
                        </div>
                        <h5 class="fw-bold">Pending Invoices</h5>
                        <h3 class="fw-bold mt-2">₵ 0.00</h3>
                        <p class="text-muted small">No outstanding payments at this time.</p>
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="navigateTo('section-invoices')">View Billing</button>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card p-4 border-0 shadow-sm h-100">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0">Recent Activity</h5>
                            <a href="/emr.php" class="text-primary text-decoration-none small fw-bold">See All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light border-0">
                                    <tr>
                                        <th class="border-0">Date</th>
                                        <th class="border-0">Service</th>
                                        <th class="border-0">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $combined = array_merge($labResults, $appointments);
                                    usort($combined, function($a, $b) { 
                                        $da = $a['created_at'] ?? $a['date'];
                                        $db = $b['created_at'] ?? $b['date'];
                                        return strtotime($db) - strtotime($da); 
                                    });
                                    $recent = array_slice($combined, 0, 5);
                                    foreach ($recent as $item): 
                                        $date = isset($item['created_at']) ? $item['created_at'] : $item['appointment_date'];
                                        $type = isset($item['test_name']) ? 'Lab: '.$item['test_name'] : 'Appt: '.$item['department'];
                                        $status = $item['status'];
                                        $badgeClass = ($status === 'completed' || $status === 'dispensed') ? 'bg-success-soft text-success' : 'bg-primary-soft text-primary';
                                    ?>
                                    <tr>
                                        <td><?php echo date('M d, Y', strtotime($date)); ?></td>
                                        <td><?php echo htmlspecialchars($type); ?></td>
                                        <td><span class="badge <?php echo $badgeClass; ?> rounded-pill px-3"><?php echo htmlspecialchars($status); ?></span></td>
                                    </tr>
                                    <?php endforeach; if (empty($recent)): ?>
                                        <tr><td colspan="3" class="text-center text-muted py-4">No recent activity found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card p-4 border-0 shadow-sm bg-primary text-white h-100 hero-card">
                        <h5 class="fw-bold mb-3">NHIS Status</h5>
                        <div class="mb-4">
                            <?php if (isset($metadata['nhis_membership_number']) && !empty($metadata['nhis_membership_number'])): ?>
                                <p class="mb-1 opacity-75">Membership Number</p>
                                <h4 class="fw-bold"><?php echo htmlspecialchars($metadata['nhis_membership_number']); ?></h4>
                                <span class="badge bg-white text-primary rounded-pill px-3 mt-2">Verified</span>
                            <?php else: ?>
                                <p class="mb-4 opacity-75">Connect your NHIS card to benefit from subsidized healthcare costs.</p>
                                <button class="btn btn-light rounded-pill px-4 text-primary fw-bold" onclick="navigateTo('section-profile')">Link Ghana Card/NHIS</button>
                            <?php endif; ?>
                        </div>
                        <hr class="bg-white opacity-25">
                        <p class="small mb-0 opacity-75">Your GhanaPostGPS (<?php echo htmlspecialchars($metadata['ghana_post_gps'] ?? 'N/A'); ?>) is used for rapid emergency response.</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-appointments" class="dashboard-section d-none">
            <div class="d-flex justify-content-between align-items-center mb-4">
                 <h5 class="fw-bold">My Appointments</h5>
                 <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">Book New Appointment</button>
            </div>
            <div class="card border-0 shadow-sm p-4 text-center">
                <?php if (empty($appointments)): ?>
                    <i class="bi bi-calendar2-x display-4 mb-3 text-muted"></i>
                    <h5 class="fw-bold">No Appointments Found</h5>
                    <p class="text-muted">You have no upcoming or past appointments.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle text-start">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Department</th>
                                    <th>Reason</th>
                                    <th>Assigned To</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments as $a): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($a['department']); ?></td>
                                    <td><?php echo htmlspecialchars($a['reason']); ?></td>
                                    <td>
                                        <?php if (!empty($a['assigned_staff']['name'])): ?>
                                            <span class="text-success fw-bold"><i class="bi bi-person-check-fill me-1"></i><?php echo htmlspecialchars($a['assigned_staff']['name']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?php echo ($a['status'] === 'completed') ? 'bg-success' : 'bg-primary'; ?> rounded-pill px-3"><?php echo htmlspecialchars($a['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="section-records" class="dashboard-section d-none">
            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-clipboard2-pulse me-2"></i> Lab Results</h6>
                        <?php if (empty($labResults)): ?>
                            <p class="text-muted small">No lab results found.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($labResults as $lr): ?>
                                    <div class="list-group-item bg-transparent px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($lr['test_name']); ?></div>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($lr['created_at'])); ?></small>
                                            </div>
                                            <span class="badge <?php echo ($lr['status'] === 'completed') ? 'bg-success' : 'bg-warning'; ?> rounded-pill px-2"><?php echo $lr['status']; ?></span>
                                        </div>
                                        <?php if ($lr['result_text']): ?>
                                            <div class="bg-light p-2 rounded mt-2 small"><?php echo htmlspecialchars($lr['result_text']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold mb-3"><i class="bi bi-capsule me-2"></i> Prescriptions</h6>
                        <?php if (empty($prescriptions)): ?>
                            <p class="text-muted small">No prescriptions found.</p>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($prescriptions as $p): ?>
                                    <div class="list-group-item bg-transparent px-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($p['medication_name'] ?? 'Medication Info'); ?></div>
                                                <small class="text-muted"><?php echo date('M d, Y', strtotime($p['created_at'])); ?></small>
                                            </div>
                                            <span class="badge bg-primary rounded-pill px-2"><?php echo $p['status']; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div id="section-invoices" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4 text-center">
                <i class="bi bi-receipt display-4 text-muted mb-3"></i>
                <h5 class="fw-bold">Billing & Invoices</h5>
                <p class="text-muted">All clear. No pending invoices.</p>
            </div>
        </div>

        <div id="section-profile" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-4">Patient Profile</h5>
                <form action="/api/profile/update" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Full Name</label>
                            <input type="text" name="name" class="form-control rounded-pill px-4" value="<?php echo htmlspecialchars($name); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">GhanaPostGPS Address</label>
                            <input type="text" name="ghana_post_gps" class="form-control rounded-pill px-4" value="<?php echo htmlspecialchars($metadata['ghana_post_gps'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Contact Phone</label>
                            <input type="text" name="phone" class="form-control rounded-pill px-4" value="<?php echo htmlspecialchars($metadata['phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Date of Birth</label>
                            <input type="date" name="dob" class="form-control rounded-pill px-4" value="<?php echo htmlspecialchars($metadata['dob'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">Ghana Card</label>
                            <input type="text" name="ghana_card" class="form-control rounded-pill px-4" value="<?php echo htmlspecialchars($metadata['ghana_card'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted small">NHIS Number</label>
                            <input type="text" name="nhis_membership_number" class="form-control rounded-pill px-4" value="<?php echo htmlspecialchars($metadata['nhis_membership_number'] ?? ''); ?>">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary rounded-pill px-5 mt-3">Update Profile</button>
                </form>
            </div>
        </div>

    </div>

    <!-- Book Appointment Modal -->
    <div class="modal fade" id="bookAppointmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Book an Appointment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="/api/appointments/book" method="POST">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-muted small">Select Department</label>
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
                        <div class="mb-3">
                            <label class="form-label text-muted small">Preferred Date</label>
                            <input type="date" name="date" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Reason for Visit</label>
                            <textarea name="reason" class="form-control" rows="3" placeholder="Briefly describe your symptoms or reason..."></textarea>
                        </div>
                        <div class="alert alert-info border-0 rounded-4 small"><i class="bi bi-info-circle me-1"></i> A doctor will confirm your appointment and may call you.</div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function navigateTo(sectionId) {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            // Hide all sections
            sections.forEach(sec => sec.classList.add('d-none'));
            // Show target
            const target = document.getElementById(sectionId);
            if (target) target.classList.remove('d-none');
            // Update active link
            links.forEach(l => {
                if (l.getAttribute('data-target') === sectionId) {
                    l.classList.add('active');
                } else {
                    l.classList.remove('active');
                }
            });
        }

        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            const sections = document.querySelectorAll('.dashboard-section');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    navigateTo(link.getAttribute('data-target'));
                    if (window.innerWidth < 992) {
                        toggleSidebar();
                    }
                });
            });
        });

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('show');
            document.querySelector('.sidebar-overlay').classList.toggle('show');
        }
    </script>
</body>
</html>
