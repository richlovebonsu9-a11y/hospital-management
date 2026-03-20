<?php
// Staff Dashboard - GGHMS (Nurse/Pharmacist/Technician)
session_start();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || !in_array($_SESSION['user']['user_metadata']['role'] ?? '', ['nurse', 'pharmacist', 'technician'])) {
    exit;
}

$user = $_SESSION['user'];
$role = $user['user_metadata']['role'] ?? 'staff';
$name = $user['user_metadata']['name'] ?? 'Staff Member';
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
                            <tr>
                                <td>#T102</td>
                                <td>Verify vitals for Patient #001 (Kwame Mensah)</td>
                                <td><span class="badge bg-danger-soft text-danger rounded-pill px-3">Urgent</span></td>
                                <td><button class="btn btn-sm btn-primary rounded-pill px-3">Update</button></td>
                            </tr>
                            <?php if ($role === 'pharmacist'): ?>
                                <tr>
                                    <td>#P405</td>
                                    <td>Dispense Prescription #RD-993</td>
                                    <td><span class="badge bg-primary-soft text-primary rounded-pill px-3">Normal</span></td>
                                    <td><button class="btn btn-sm btn-primary rounded-pill px-3">Dispense</button></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="section-role" class="dashboard-section d-none">
            <div class="card border-0 shadow-sm p-5 text-center text-muted">
                <i class="bi bi-person-workspace display-4 mb-3"></i>
                <h5 class="fw-bold">Role-Specific Workspace</h5>
                <p>Features for your specific department will appear here.</p>
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
