<?php
// Doctor Dashboard - GGHMS
session_start();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'doctor') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$name = $user['user_metadata']['name'] ?? 'Doctor';
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
            <a href="#" class="nav-link-custom active" data-target="section-queue"><i class="bi bi-people-fill"></i> Patient Queue</a>
            <a href="#" class="nav-link-custom" data-target="section-schedule"><i class="bi bi-calendar-event"></i> My Schedule</a>
            <a href="#" class="nav-link-custom" data-target="section-consults"><i class="bi bi-file-earmark-medical-fill"></i> Consultations</a>
            <a href="#" class="nav-link-custom" data-target="section-prescripts"><i class="bi bi-capsule"></i> E-Prescriptions</a>
            <a href="#" class="nav-link-custom" data-target="section-labs"><i class="bi bi-clipboard-pulse"></i> Lab Results</a>
            <hr class="my-4">
            <a href="/" class="nav-link-custom"><i class="bi bi-house"></i> Back to Home</a>
            <a href="/api/auth/logout.php" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Dr. <?php echo htmlspecialchars($name); ?> 👩‍⚕️</h2>
                <p class="text-muted mb-0">You have 8 patients in your queue today.</p>
            </div>
            <div class="d-flex align-items-center">
                <div class="me-4 text-end">
                    <p class="mb-0 fw-bold">OPD Shift</p>
                    <span class="badge bg-success-soft text-success rounded-pill px-3">Active Now</span>
                </div>
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- QUEUE SECTION -->
        <div id="section-queue" class="dashboard-section">
            <div class="row g-4 mb-5">
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Patients Waiting</h6>
                        <h2 class="fw-bold mb-0 text-primary">05</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Seen Today</h6>
                        <h2 class="fw-bold mb-0 text-success">12</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Lab Requests</h6>
                        <h2 class="fw-bold mb-0 text-warning">03</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card p-3 border-0 shadow-sm text-center">
                        <h6 class="text-muted mb-2">Emergency Calls</h6>
                        <h2 class="fw-bold mb-0 text-danger">01</h2>
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
                            <tr>
                                <td class="fw-bold">#001</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle me-2" style="width: 32px; height: 32px;"></div>
                                        <div>
                                            <div class="fw-bold">Kwame Mensah</div>
                                            <small class="text-muted">M, 42 yrs</small>
                                        </div>
                                    </div>
                                </td>
                                <td><small>BP: 120/80 | Temp: 37°C</small></td>
                                <td><span class="badge bg-warning-soft text-warning rounded-pill px-3">Waiting</span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm rounded-pill px-3">Start Call</button>
                                    <button class="btn btn-light btn-sm rounded-circle shadow-sm ms-1"><i class="bi bi-three-dots-vertical"></i></button>
                                </td>
                            </tr>
                            <tr>
                                <td class="fw-bold">#002</td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="bg-light rounded-circle me-2" style="width: 32px; height: 32px;"></div>
                                        <div>
                                            <div class="fw-bold">Abena Osei</div>
                                            <small class="text-muted">F, 28 yrs</small>
                                        </div>
                                    </div>
                                </td>
                                <td><small>BP: 110/70 | Temp: 38.5°C</small></td>
                                <td><span class="badge bg-danger-soft text-danger rounded-pill px-3">High Fever</span></td>
                                <td>
                                    <button class="btn btn-primary btn-sm rounded-pill px-3">Start Call</button>
                                    <button class="btn btn-light btn-sm rounded-circle shadow-sm ms-1"><i class="bi bi-three-dots-vertical"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- PLACEHOLDERS FOR OTHER SECTIONS -->
        <div id="section-schedule" class="dashboard-section d-none">
            <div class="alert alert-info border-0 rounded-4"><i class="bi bi-info-circle me-2"></i> Your schedule for this week is clear. You are currently assigned to General OPD.</div>
        </div>
        <div id="section-consults" class="dashboard-section d-none">
            <h5 class="fw-bold mb-4">Past Consultations</h5>
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-folder2-open display-4 mb-3"></i>
                <p>Select a patient from the queue to start a consultation.</p>
            </div>
        </div>
        <div id="section-prescripts" class="dashboard-section d-none">
            <div class="row">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm p-4">
                        <h6 class="fw-bold mb-3">Create E-Prescription</h6>
                        <input type="text" class="form-control mb-3" placeholder="Patient Name or ID">
                        <textarea class="form-control mb-3" rows="3" placeholder="Medication Details..."></textarea>
                        <button class="btn btn-primary rounded-pill">Send to Pharmacy</button>
                    </div>
                </div>
            </div>
        </div>
        <div id="section-labs" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4 text-center">
                <i class="bi bi-clipboard-pulse display-4 text-muted mb-3"></i>
                <p class="text-muted">No pending lab results to review.</p>
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
                    e.preventDefault();
                    links.forEach(l => l.classList.remove('active'));
                    link.classList.add('active');
                    sections.forEach(sec => sec.classList.add('d-none'));
                    const targetId = link.getAttribute('data-target');
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) targetSection.classList.remove('d-none');
                });
            });
        });
    </script>
</body>
</html>
