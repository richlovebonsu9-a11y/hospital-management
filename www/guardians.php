<?php
// Guardian Management - K.M. General Hospital
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Guardians - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5 mt-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Guardian Management</h2>
                <p class="text-muted">Link and manage your health guardians.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addGuardianModal"><i class="bi bi-plus-lg me-2"></i> Add Guardian</button>
        </header>

        <div class="row g-4">
            <!-- Linked Guardian 1 -->
            <div class="col-md-6">
                <div class="card border-0 shadow-sm rounded-5 p-4 d-flex flex-row align-items-center">
                    <div class="bg-primary-soft text-primary rounded-circle p-3 me-3" style="width: 60px; height: 60px; display: flex; align-items: center; justify-content: center;">
                        <i class="bi bi-person h3 mb-0"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="fw-bold mb-1">Joyce Mensah</h6>
                        <p class="text-muted small mb-0">Relationship: Spouse | Primary</p>
                    </div>
                    <div class="dropdown">
                        <button class="btn btn-light btn-sm rounded-circle" data-bs-toggle="dropdown"><i class="bi bi-three-dots"></i></button>
                        <ul class="dropdown-menu border-0 shadow-sm rounded-3">
                            <li><a class="dropdown-item" href="#">View Phone</a></li>
                            <li><a class="dropdown-item text-danger" href="#">Remove</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Guardian Modal -->
        <div class="modal fade" id="addGuardianModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 rounded-5 shadow-lg">
                    <div class="modal-body p-5">
                        <h4 class="fw-bold text-center mb-4">Link New Guardian</h4>
                        <form action="/api/guardians/add" method="POST">
                            <div class="mb-3">
                                <label class="form-label">Guardian Phone Number</label>
                                <input type="tel" name="phone" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="+233 ...">
                                <small class="text-muted">They will receive an invite to confirm the link.</small>
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Relationship Type</label>
                                <select name="relationship" class="form-select rounded-pill px-4 py-3 border-light bg-light">
                                    <option value="spouse">Spouse</option>
                                    <option value="parent">Parent / Ward</option>
                                    <option value="sibling">Sibling</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">Send Invite</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
