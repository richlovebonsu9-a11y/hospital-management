<?php
// Staff Dashboard - GGHMS (Nurse/Pharmacist/Technician)
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
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

        <nav>
            <a href="#" class="nav-link-custom active"><i class="bi bi-list-task"></i> Task Queue</a>
            <?php if ($role === 'nurse'): ?>
                <a href="#" class="nav-link-custom"><i class="bi bi-hospital"></i> Ward Management</a>
            <?php elseif ($role === 'pharmacist'): ?>
                <a href="#" class="nav-link-custom"><i class="bi bi-capsule"></i> Inventory</a>
            <?php elseif ($role === 'technician'): ?>
                <a href="#" class="nav-link-custom"><i class="bi bi-clipboard-pulse"></i> Lab Requests</a>
            <?php endif; ?>
            <a href="#" class="nav-link-custom"><i class="bi bi-chat-dots"></i> Internal Comms</a>
            <hr class="my-4">
            <a href="/api/auth/logout" class="nav-link-custom text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Hello, <?php echo htmlspecialchars($name); ?></h2>
                <p class="text-muted mb-0">Role: <span class="text-capitalize fw-bold text-primary"><?php echo $role; ?></span></p>
            </div>
            <div class="d-flex align-items-center">
                <div class="bg-white rounded-circle shadow-sm" style="width: 48px; height: 48px;"></div>
            </div>
        </header>

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
</body>
</html>
