<?php
// Admin Dashboard - GGHMS
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'admin') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$name = $user['user_metadata']['name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - GGHMS</title>
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

        <nav>
            <a href="#" class="nav-link-custom active"><i class="bi bi-speedometer2"></i> Analytics</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-people"></i> Staff Management</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-hospital"></i> Bed Management</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-cash-stack"></i> Financial Reports</a>
            <a href="#" class="nav-link-custom"><i class="bi bi-gear"></i> System Settings</a>
            <hr class="my-4">
            <a href="/api/auth/logout" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Administrative Center</h2>
                <p class="text-muted mb-0">Overview of Ghanaian General Hospital operations.</p>
            </div>
            <div class="d-flex align-items-center">
                 <div class="me-4 text-end">
                    <p class="mb-0 fw-bold">Hospital Status</p>
                    <span class="badge bg-success-soft text-success rounded-pill px-3">Normal Operations</span>
                </div>
                <div class="bg-white rounded-circle shadow-sm" style="width: 48px; height: 48px;"></div>
            </div>
        </header>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm">
                    <h6 class="text-muted mb-3">Total Patients</h6>
                    <h2 class="fw-bold mb-1 text-primary">1,280</h2>
                    <small class="text-success"><i class="bi bi-arrow-up"></i> 12% from last month</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm">
                    <h6 class="text-muted mb-3">Bed Occupancy</h6>
                    <h2 class="fw-bold mb-1 text-warning">82%</h2>
                    <small class="text-muted">164 / 200 Beds filled</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm">
                    <h6 class="text-muted mb-3">Revenue (MTD)</h6>
                    <h2 class="fw-bold mb-1">₵ 45.2K</h2>
                    <small class="text-success"><i class="bi bi-arrow-up"></i> 8% increase</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm">
                    <h6 class="text-muted mb-3">Avg Wait Time</h6>
                    <h2 class="fw-bold mb-1 text-info">24 min</h2>
                    <small class="text-danger"><i class="bi bi-arrow-down"></i> 5 min target skip</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card p-4 border-0 shadow-sm h-100">
                    <h5 class="fw-bold mb-4">Department Performance</h5>
                    <div class="table-responsive">
                         <table class="table table-hover align-middle">
                            <thead class="table-light border-0">
                                <tr>
                                    <th class="border-0">Department</th>
                                    <th class="border-0">Patients</th>
                                    <th class="border-0">Avg Visit</th>
                                    <th class="border-0">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="fw-bold">General OPD</span></td>
                                    <td>450</td>
                                    <td>1.2 hrs</td>
                                    <td><span class="badge bg-success-soft text-success rounded-pill px-3">High</span></td>
                                </tr>
                                <tr>
                                    <td><span class="fw-bold">Maternity</span></td>
                                    <td>120</td>
                                    <td>4.5 days</td>
                                    <td><span class="badge bg-primary-soft text-primary rounded-pill px-3">Stable</span></td>
                                </tr>
                                <tr>
                                    <td><span class="fw-bold">Pharmacy</span></td>
                                    <td>890</td>
                                    <td>15 min</td>
                                    <td><span class="badge bg-danger-soft text-danger rounded-pill px-3">Overloaded</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                 <div class="card p-4 border-0 shadow-sm h-100">
                    <h5 class="fw-bold mb-4">Emergency Alerts</h5>
                    <div class="alert bg-danger-soft text-danger border-0 rounded-4 mb-3">
                        <div class="d-flex">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <div>
                                <div class="fw-bold">Emergency High Severity</div>
                                <small>Patient at AK-485-9323 requested dispatch.</small>
                                <div class="mt-2 text-decoration-underline small">View Details</div>
                            </div>
                        </div>
                    </div>
                    <div class="alert bg-warning-soft text-warning border-0 rounded-4">
                        <div class="d-flex">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <div>
                                <div class="fw-bold">System Maintenance</div>
                                <small>Backup schedule for 2:00 AM UTC.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
