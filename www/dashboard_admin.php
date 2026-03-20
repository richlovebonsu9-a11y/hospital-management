<?php
// Admin Dashboard - GGHMS
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';

use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || $_SESSION['user']['user_metadata']['role'] !== 'admin') {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$name = $user['user_metadata']['name'] ?? 'Admin';

// Fetch all staff members from Supabase Auth Using Service Key
$supabase = new Supabase();
$response = $supabase->request('GET', '/auth/v1/admin/users', null, true);
$staffMembers = [];

if ($response['status'] === 200 && isset($response['data']['users'])) {
    $allUsers = $response['data']['users'];
    $staffRoles = ['doctor', 'nurse', 'pharmacist', 'technician', 'admin'];
    
    foreach ($allUsers as $u) {
        $meta = $u['user_metadata'] ?? [];
        $uRole = $meta['role'] ?? 'patient';
        if (in_array(strtolower($uRole), $staffRoles)) {
            $staffMembers[] = [
                'id' => $u['id'],
                'email' => $u['email'],
                'name' => $meta['name'] ?? 'Unknown',
                'role' => $uRole,
                'department' => $meta['department'] ?? 'General',
                'status' => 'Active' // We can expand this later
            ];
        }
    }
}
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

        <nav id="sidebarMenu">
            <a href="#" class="nav-link-custom active" data-target="section-analytics"><i class="bi bi-speedometer2"></i> Analytics</a>
            <a href="#" class="nav-link-custom" data-target="section-staff"><i class="bi bi-people"></i> Staff Management</a>
            <a href="#" class="nav-link-custom" data-target="section-beds"><i class="bi bi-hospital"></i> Bed Management</a>
            <a href="#" class="nav-link-custom" data-target="section-finance"><i class="bi bi-cash-stack"></i> Financial Reports</a>
            <a href="#" class="nav-link-custom" data-target="section-settings"><i class="bi bi-gear"></i> System Settings</a>
            <hr class="my-4">
            <a href="/" class="nav-link-custom"><i class="bi bi-house"></i> Back to Home</a>
            <a href="/api/auth/logout.php" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
                <div class="bg-primary text-white rounded-circle shadow-sm d-flex align-items-center justify-content-center fw-bold fs-5" style="width: 48px; height: 48px;">
                    <?php echo strtoupper(substr($name, 0, 1)); ?>
                </div>
            </div>
        </header>

        <!-- ANALYTICS SECTION -->
        <div id="section-analytics" class="dashboard-section">
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

        <!-- STAFF MANAGEMENT SECTION -->
        <div id="section-staff" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Staff Member Directory</h5>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="bi bi-plus-lg"></i> Add New Staff</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffMembers)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No staff members found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffMembers as $staff): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td><span class="text-capitalize badge bg-secondary"><?php echo htmlspecialchars($staff['role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($staff['status']); ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editStaff('<?php echo htmlspecialchars(json_encode($staff), ENT_QUOTES, 'UTF-8'); ?>')">Edit</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- BED MANAGEMENT SECTION -->
        <div id="section-beds" class="dashboard-section d-none">
            <div class="row g-4 d-flex align-items-stretch">
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">Maternity Ward</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-primary">32 / 40 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 80%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">ICU (Intensive Care)</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-danger">18 / 20 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 90%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">General Ward A</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-success">45 / 100 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 45%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">Pediatrics</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-warning">28 / 40 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 70%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FINANCE SECTION -->
        <div id="section-finance" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Recent Transactions</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Patient/Entity</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>TX-99281</td>
                                <td>NHIS Claim Settlement</td>
                                <td><span class="badge bg-success-soft text-success">Income</span></td>
                                <td>₵ 12,500.00</td>
                                <td>Today, 10:30 AM</td>
                            </tr>
                            <tr>
                                <td>TX-99280</td>
                                <td>Medical Supplies Restock</td>
                                <td><span class="badge bg-danger-soft text-danger">Expense</span></td>
                                <td>₵ 4,250.00</td>
                                <td>Yesterday, 2:15 PM</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SETTINGS SECTION -->
        <div id="section-settings" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm max-w-2xl">
                <h5 class="fw-bold mb-4">System Configuration</h5>
                
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3">General Settings</h6>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="acceptPatients" checked>
                        <label class="form-check-label" for="acceptPatients">Accept New Patient Registrations</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="emergencyMode">
                        <label class="form-check-label text-danger fw-bold" for="emergencyMode">Enable Emergency Operations Mode</label>
                    </div>
                </div>
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

        <!-- STAFF MANAGEMENT SECTION -->
        <div id="section-staff" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="fw-bold mb-0">Staff Member Directory</h5>
                    <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addStaffModal"><i class="bi bi-plus-lg"></i> Add New Staff</button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($staffMembers)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No staff members found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($staffMembers as $staff): ?>
                                <tr>
                                    <td class="fw-bold"><?php echo htmlspecialchars($staff['name']); ?></td>
                                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                                    <td><span class="text-capitalize badge bg-secondary"><?php echo htmlspecialchars($staff['role']); ?></span></td>
                                    <td><?php echo htmlspecialchars($staff['department']); ?></td>
                                    <td><span class="badge bg-success"><?php echo htmlspecialchars($staff['status']); ?></span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-secondary" onclick="editStaff('<?php echo htmlspecialchars(json_encode($staff), ENT_QUOTES, 'UTF-8'); ?>')">Edit</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- BED MANAGEMENT SECTION -->
        <div id="section-beds" class="dashboard-section d-none">
            <div class="row g-4 d-flex align-items-stretch">
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">Maternity Ward</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-primary">32 / 40 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: 80%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">ICU (Intensive Care)</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-danger">18 / 20 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: 90%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">General Ward A</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-success">45 / 100 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 45%"></div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-4">
                    <div class="card border-0 shadow-sm p-4 h-100">
                        <h5 class="fw-bold mb-4">Pediatrics</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Occupancy</span>
                            <span class="fw-bold text-warning">28 / 40 Beds</span>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: 70%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- FINANCE SECTION -->
        <div id="section-finance" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm">
                <h5 class="fw-bold mb-4">Recent Transactions</h5>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Transaction ID</th>
                                <th>Patient/Entity</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>TX-99281</td>
                                <td>NHIS Claim Settlement</td>
                                <td><span class="badge bg-success-soft text-success">Income</span></td>
                                <td>₵ 12,500.00</td>
                                <td>Today, 10:30 AM</td>
                            </tr>
                            <tr>
                                <td>TX-99280</td>
                                <td>Medical Supplies Restock</td>
                                <td><span class="badge bg-danger-soft text-danger">Expense</span></td>
                                <td>₵ 4,250.00</td>
                                <td>Yesterday, 2:15 PM</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- SETTINGS SECTION -->
        <div id="section-settings" class="dashboard-section d-none">
            <div class="card p-4 border-0 shadow-sm max-w-2xl">
                <h5 class="fw-bold mb-4">System Configuration</h5>
                
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3">General Settings</h6>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="acceptPatients" checked>
                        <label class="form-check-label" for="acceptPatients">Accept New Patient Registrations</label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="emergencyMode">
                        <label class="form-check-label text-danger fw-bold" for="emergencyMode">Enable Emergency Operations Mode</label>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <div class="mb-4">
                    <h6 class="fw-bold text-muted mb-3">Security</h6>
                    <button class="btn btn-outline-primary mb-2">Change Admin Password</button>                    <button class="btn btn-outline-secondary mb-2">Review Access Logs</button>
                </div>
            </div>
        </div>

    </div>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-labelledby="addStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="addStaffModalLabel">Add New Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="/api/admin/staff_add" method="POST">
                        <div class="mb-3">
                            <label class="form-label text-muted small">Full Name</label>
                            <input type="text" name="name" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Email Address</label>
                            <input type="email" name="email" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Temporary Password</label>
                            <input type="password" name="password" class="form-control rounded-pill px-3" required minlength="6">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Role</label>
                                <select name="role" class="form-select rounded-pill px-3" required>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="technician">Technician</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Department</label>
                                <input type="text" name="department" class="form-control rounded-pill px-3" placeholder="e.g. Cardiology" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-3">Create Staff Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Staff Modal -->
    <div class="modal fade" id="editStaffModal" tabindex="-1" aria-labelledby="editStaffModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editStaffModalLabel">Edit Staff</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="/api/admin/staff_edit" method="POST">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label text-muted small">Full Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control rounded-pill px-3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Email Address (Read-only)</label>
                            <input type="email" name="email" id="edit_email" class="form-control rounded-pill px-3" readonly>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Role</label>
                                <select name="role" id="edit_role" class="form-select rounded-pill px-3" required>
                                    <option value="doctor">Doctor</option>
                                    <option value="nurse">Nurse</option>
                                    <option value="pharmacist">Pharmacist</option>
                                    <option value="technician">Technician</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Department</label>
                                <input type="text" name="department" id="edit_department" class="form-control rounded-pill px-3" required>
                            </div>
                        </div>
                        <div class="alert alert-warning small mt-3 border-0 rounded-4">
                            <i class="bi bi-info-circle me-1"></i> Passwords must be reset by the user via email.
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-2">Save Changes</button>
                    </form>
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
                    e.preventDefault();
                    
                    // Remove active class from all links
                    links.forEach(l => l.classList.remove('active'));
                    // Add active class to clicked link
                    link.classList.add('active');

                    // Hide all sections
                    sections.forEach(sec => sec.classList.add('d-none'));
                    
                    // Show target section
                    const targetId = link.getAttribute('data-target');
                    const targetSection = document.getElementById(targetId);
                    if (targetSection) {
                        targetSection.classList.remove('d-none');
                    }
                });
            });
            
            // Show status messages
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('staff_added') === '1') {
                alert('Staff account successfully created!');
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('staff_edited') === '1') {
                alert('Staff profile successfully updated!');
                window.history.replaceState({}, document.title, window.location.pathname);
            } else if (urlParams.get('error')) {
                alert('Error: ' + urlParams.get('error'));
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });

        function editStaff(staffJson) {
            const staff = JSON.parse(staffJson);
            document.getElementById('edit_user_id').value = staff.id;
            document.getElementById('edit_name').value = staff.name;
            document.getElementById('edit_email').value = staff.email;
            document.getElementById('edit_role').value = staff.role;
            document.getElementById('edit_department').value = staff.department;
            
            const editModal = new bootstrap.Modal(document.getElementById('editStaffModal'));
            editModal.show();
        }
    </script>
</body>
</html>
