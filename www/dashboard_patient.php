<?php
// Patient Dashboard - GGHMS
session_start();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'patient') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$metadata = $user['user_metadata'] ?? [];
$name = $metadata['name'] ?? 'Patient';
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
        .sidebar {
            width: 280px;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: white;
            border-right: 1px solid #eee;
            z-index: 1000;
        }
        .main-content {
            margin-left: 280px;
            padding: 40px;
            background: #f8fafc;
            min-height: 100vh;
        }
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
        @media (max-width: 992px) {
            .sidebar { display: none; }
            .main-content { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
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
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($name); ?>! 👋</h2>
                <p class="text-muted mb-0">Welcome back to your health portal.</p>
            </div>
            <div class="d-flex align-items-center">
                <button class="btn btn-danger rounded-pill px-4 me-3"><i class="bi bi-exclamation-triangle-fill me-2"></i> EMERGENCY</button>
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <div id="section-dashboard" class="dashboard-section">
            <div class="row g-4 mb-5">
                <div class="col-md-4">
                    <div class="card h-100 p-4 border-0 shadow-sm">
                        <div class="feature-icon-wrapper mb-3">
                            <i class="bi bi-calendar2-plus"></i>
                        </div>
                        <h5 class="fw-bold">Next Appointment</h5>
                        <p class="text-muted">You have no upcoming appointments scheduled.</p>
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
                            <span class="text-muted">Last Checkup</span>
                            <span class="fw-bold">Mar 12, 2026</span>
                        </div>
                        <button class="btn btn-outline-primary w-100 mt-auto" onclick="navigateTo('section-records')">Full Record</button>
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
                            <a href="#" class="text-primary text-decoration-none small fw-bold" onclick="navigateTo('section-records'); return false;">See All</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light border-0">
                                    <tr>
                                        <th class="border-0">Date</th>
                                        <th class="border-0">Service</th>
                                        <th class="border-0">Status</th>
                                        <th class="border-0">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Mar 15, 2026</td>
                                        <td>General Consultation</td>
                                        <td><span class="badge bg-success-soft text-success rounded-pill px-3">Completed</span></td>
                                        <td><a href="#" class="btn btn-sm btn-light rounded-pill"><i class="bi bi-eye"></i></a></td>
                                    </tr>
                                    <tr>
                                        <td>Mar 12, 2026</td>
                                        <td>Lab Test (Malaria)</td>
                                        <td><span class="badge bg-success-soft text-success rounded-pill px-3">Completed</span></td>
                                        <td><a href="#" class="btn btn-sm btn-light rounded-pill"><i class="bi bi-eye"></i></a></td>
                                    </tr>
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
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-calendar2-x display-4 mb-3"></i>
                <h5 class="fw-bold">No Upcoming Appointments</h5>
                <p>You can book a new consultation to see a specialist.</p>
                <button class="btn btn-primary rounded-pill px-4 mt-3 mx-auto" style="width: fit-content;" data-bs-toggle="modal" data-bs-target="#bookAppointmentModal">Book Appointment</button>
            </div>
        </div>

        <div id="section-records" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-4">
                <h5 class="fw-bold mb-4">Your Medical Records</h5>
                <p class="text-muted">You have no uploaded records.</p>
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
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Full Name</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($name); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">GhanaPostGPS Address</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($metadata['ghana_post_gps'] ?? 'Not set'); ?></p>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small">Contact Phone</label>
                        <p class="fw-bold"><?php echo htmlspecialchars($metadata['phone'] ?? 'Not set'); ?></p>
                    </div>
                </div>
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
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label text-muted small">Select Department</label>
                        <select class="form-select rounded-pill px-3">
                            <option>General OPD</option>
                            <option>Cardiology</option>
                            <option>Pediatrics</option>
                            <option>Maternity</option>
                            <option>Dental</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Preferred Date</label>
                        <input type="date" class="form-control rounded-pill px-3">
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small">Reason for Visit</label>
                        <textarea class="form-control" rows="3" placeholder="Briefly describe your symptoms or reason..."></textarea>
                    </div>
                    <div class="alert alert-info border-0 rounded-4 small"><i class="bi bi-info-circle me-1"></i> A doctor will confirm your appointment and may call you.</div>
                    <button class="btn btn-primary w-100 rounded-pill">Submit Request</button>
                </div>
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
                });
            });
        });
    </script>
</body>
</html>
