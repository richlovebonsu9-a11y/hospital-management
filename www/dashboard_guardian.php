<?php
// Guardian Dashboard - GGHMS
session_start();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'guardian') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$metadata = $user['user_metadata'] ?? [];
$name = $metadata['name'] ?? 'Guardian';
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
                        <p class="text-muted">No upcoming appointments scheduled for linked patients.</p>
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
                            <tr>
                                <td class="text-muted" colspan="4">No activity found for linked patients.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- LINKED PATIENTS SECTION -->
        <div id="section-linked" class="dashboard-section d-none">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="fw-bold">Linked Patients</h5>
            </div>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-person-plus display-4 mb-3"></i>
                <h5 class="fw-bold">No patients linked yet</h5>
                <p>Ask a patient to add you as their guardian from their dashboard, or contact your hospital administrator.</p>
            </div>
        </div>

        <!-- APPOINTMENTS SECTION -->
        <div id="section-appointments" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-calendar2-x display-4 mb-3"></i>
                <h5 class="fw-bold">No Upcoming Appointments</h5>
                <p>Once linked to a patient, their appointments will appear here.</p>
            </div>
        </div>

        <!-- EMERGENCY SECTION -->
        <div id="section-emergency" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-4 text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Emergency Dispatch</h5>
                <div class="alert border-0 rounded-4 bg-danger text-white mb-4">
                    <strong>⚠ Warning:</strong> Only use this for genuine medical emergencies. Misuse may result in account suspension.
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">GhanaPostGPS Location of Emergency</label>
                    <input type="text" class="form-control rounded-pill px-3" placeholder="e.g. AK-485-9323" value="<?php echo htmlspecialchars($metadata['ghana_post_gps'] ?? ''); ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Brief Description</label>
                    <textarea class="form-control" rows="3" placeholder="e.g. Patient has collapsed and is unresponsive..."></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label text-muted small">Severity</label>
                    <select class="form-select rounded-pill px-3">
                        <option value="high">High - Immediate dispatch needed</option>
                        <option value="medium">Medium - Urgent but stable</option>
                        <option value="low">Low - Non-critical</option>
                    </select>
                </div>
                <button class="btn btn-danger rounded-pill px-5 fw-bold">🚨 Request Emergency Dispatch</button>
            </div>
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
