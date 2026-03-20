<?php
// Guardian Dashboard - GGHMS
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'guardian') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$metadata = $user['user_metadata'] ?? [];
$name = $metadata['name'] ?? 'Guardian';
$linkedPatients = $metadata['linked_patients'] ?? []; // Array of patient IDs

$sb = new Supabase();
// 1. Fetch appointments for linked patients (where guardian_id = this user)
$apptRes = $sb->request('GET', '/rest/v1/appointments?guardian_id=eq.' . $userId . '&order=date.asc');
$appointments = ($apptRes['status'] === 200) ? $apptRes['data'] : [];

// 2. Fetch recent activity (lab requests, prescriptions) for linked patients
// Note: This is simpler if we just loop through linkedPatients or filter the tables by patient_id in linkedPatients
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guardian Dashboard - GGHMS</title>
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
        @media (max-width: 992px) { .sidebar { display: none; } .main-content { margin-left: 0; padding: 20px; } }
    </style>
</head>
<body>
    <div class="sidebar p-4">
        <div class="d-flex align-items-center mb-5">
            <div class="bg-primary rounded-circle me-2" style="width: 32px; height: 32px;"></div>
            <h4 class="fw-bold mb-0 text-secondary">GGHMS</h4>
        </div>

        <nav id="sidebarMenu">
            <a href="#" class="nav-link-custom active" data-target="section-overview"><i class="bi bi-grid-fill"></i> Overview</a>
            <a href="#" class="nav-link-custom" data-target="section-linked"><i class="bi bi-people-fill"></i> Linked Patients</a>
            <a href="#" class="nav-link-custom" data-target="section-appointments"><i class="bi bi-calendar-check"></i> Appointments</a>
            <a href="#" class="nav-link-custom" data-target="section-emergency"><i class="bi bi-exclamation-triangle-fill"></i> Emergency</a>
            <hr class="my-4">
            <a href="/" class="nav-link-custom"><i class="bi bi-house"></i> Back to Home</a>
            <a href="/api/auth/logout.php" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($name); ?>! 👋</h2>
                <p class="text-muted mb-0">You are managing healthcare for a linked patient.</p>
            </div>
            <div class="d-flex align-items-center">
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- OVERVIEW SECTION -->
        <div id="section-overview" class="dashboard-section">
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="bi bi-people"></i>
                        </div>
                        <h5 class="fw-bold">Linked Patients</h5>
                        <p class="text-muted">View and manage the patients linked to your account.</p>
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="navigateTo('section-linked')">View Patients</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3" style="background: #fff4e6; color: #fca311;">
                            <i class="bi bi-calendar-check"></i>
                        </div>
                        <h5 class="fw-bold">Upcoming Appointments</h5>
                        <p class="text-muted">You have <?php echo count($appointments); ?> appointments scheduled for your linked patients.</p>
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="navigateTo('section-appointments')">View Appointments</button>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3" style="background: #fef2f2; color: #ef4444;">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                        <h5 class="fw-bold">Emergency Actions</h5>
                        <p class="text-muted">Request emergency medical dispatch for a linked patient.</p>
                        <button class="btn btn-danger w-100 mt-auto" onclick="navigateTo('section-emergency')">Emergency</button>
                    </div>
                </div>
            </div>

            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Recent Activity for Linked Patients</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light border-0">
                            <tr>
                                <th class="border-0">Date</th>
                                <th class="border-0">Patient</th>
                                <th class="border-0">Service</th>
                                <th class="border-0">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr><td class="text-muted" colspan="4">No recent activity found for linked patients.</td></tr>
                            <?php endif; foreach ($appointments as $appt): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($appt['date'])); ?></td>
                                <td><span class="fw-bold">Patient <?php echo substr($appt['patient_id'], 0, 8); ?></span></td>
                                <td><?php echo htmlspecialchars($appt['department']); ?></td>
                                <td><span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo htmlspecialchars($appt['status']); ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- LINKED PATIENTS SECTION -->
        <div id="section-linked" class="dashboard-section d-none">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold">Linked Patients Management</h5>
                <button class="btn btn-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#linkPatientModal">+ Link New Patient</button>
            </div>
            
            <?php if (empty($linkedPatients)): ?>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-person-plus display-4 mb-3"></i>
                <h5 class="fw-bold">No patients linked yet</h5>
                <p>Link your children or elderly relatives to manage their records and appointments.</p>
            </div>
            <?php else: ?>
            <div class="row g-4">
                <?php foreach ($linkedPatients as $pid): ?>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-primary-soft text-primary rounded-circle me-3 d-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px;">P</div>
                            <div>
                                <h6 class="fw-bold mb-0">Patient <?php echo substr($pid, 0, 8); ?></h6>
                                <small class="text-muted">ID: <?php echo $pid; ?></small>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <a href="/emr.php?patient_id=<?php echo $pid; ?>" class="btn btn-light btn-sm rounded-pill">View Full EMR</a>
                            <button class="btn btn-primary btn-sm rounded-pill" data-bs-toggle="modal" data-bs-target="#bookApptModal" onclick="document.getElementById('appt_patient_id').value='<?php echo $pid; ?>'">Book Appt</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- APPOINTMENTS SECTION -->
        <div id="section-appointments" class="dashboard-section d-none">
            <h5 class="fw-bold mb-4">Upcoming Appointments for Dependants</h5>
            <div class="card border-0 shadow-sm p-4">
                <?php if (empty($appointments)): ?>
                    <p class="text-muted text-center py-4">No upcoming appointments scheduled.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Date</th><th>Patient ID</th><th>Department</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($appointments as $a): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($a['date'])); ?></td>
                                    <td><?php echo $a['patient_id']; ?></td>
                                    <td><?php echo htmlspecialchars($a['department']); ?></td>
                                    <td><span class="badge bg-primary-soft text-primary rounded-pill px-3"><?php echo htmlspecialchars($a['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- EMERGENCY SECTION -->
        <div id="section-emergency" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Emergency Dispatch for Dependants</h5>
                <div class="alert border-0 rounded-4 bg-danger text-white mb-4">
                    <strong>⚠ Warning:</strong> Only use this for genuine medical emergencies. Misuse may result in account suspension.
                </div>
                <form action="/api/emergency/report.php" method="POST">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Select Affected Dependant</label>
                        <select name="patient_id" class="form-select rounded-pill px-3" required>
                            <?php foreach ($linkedPatients as $pid): ?>
                                <option value="<?php echo $pid; ?>">Patient <?php echo substr($pid, 0, 8); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">GhanaPostGPS Location</label>
                        <input type="text" name="location" class="form-control rounded-pill px-3" placeholder="e.g. AK-485-9323" value="<?php echo htmlspecialchars($metadata['ghana_post_gps'] ?? ''); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Brief Description of Emergency</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="e.g. Patient has collapsed..." required></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Severity</label>
                        <select name="severity" class="form-select rounded-pill px-3">
                            <option value="high">High - Immediate dispatch needed</option>
                            <option value="medium">Medium - Urgent but stable</option>
                            <option value="low">Low - Non-critical</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-danger rounded-pill px-5 fw-bold w-100">🚨 Request Emergency Dispatch</button>
                </form>
            </div>
        </div>

    </div>

    <!-- Modals -->
    <div class="modal fade" id="linkPatientModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="/api/profile/update.php" method="POST" class="modal-content border-0 shadow">
                <div class="modal-header border-0"><h5 class="fw-bold">Link New Patient</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <p class="text-muted small">Enter the unique Patient ID of the person you wish to manage.</p>
                    <input type="text" name="new_patient_id" class="form-control rounded-pill px-3 mb-3" placeholder="e.g. uuid-1234-..." required>
                    <input type="hidden" name="action" value="link_patient">
                    <button type="submit" class="btn btn-primary w-100 rounded-pill">Link Patient</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="bookApptModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="/api/appointments/book.php" method="POST" class="modal-content border-0 shadow">
                <input type="hidden" name="patient_id" id="appt_patient_id">
                <div class="modal-header border-0"><h5 class="fw-bold">Book Dependant Appointment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-4">
                    <div class="mb-3"><label class="small text-muted">Department</label><select name="department" class="form-select rounded-pill px-3"><option>General OPD</option><option>Pediatrics</option><option>Cardiology</option><option>Maternity</option></select></div>
                    <div class="mb-3"><label class="small text-muted">Preferred Date</label><input type="date" name="date" class="form-control rounded-pill px-3" required min="<?php echo date('Y-m-d'); ?>"></div>
                    <div class="mb-3"><label class="small text-muted">Reason for Visit</label><textarea name="reason" class="form-control" rows="3" placeholder="Symptoms..."></textarea></div>
                    <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Confirm Appointment</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

        document.addEventListener('DOMContentLoaded', () => {
            const links = document.querySelectorAll('#sidebarMenu .nav-link-custom[data-target]');
            links.forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    navigateTo(link.getAttribute('data-target'));
                });
            });
        });
    </script>
</body>
</html>
