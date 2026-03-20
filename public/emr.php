<?php
// Patient Electronic Medical Record (EMR) - GGHMS
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$name = $user['user_metadata']['name'] ?? 'Patient';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My EMR - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5 mt-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Electronic Medical Record</h2>
                <p class="text-muted">Comprehensive history of your visits and treatments.</p>
            </div>
            <button class="btn btn-outline-primary rounded-pill px-4"><i class="bi bi-download me-2"></i> Export PDF</button>
        </header>

        <div class="row g-4">
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm rounded-5 p-4 sticky-top" style="top: 100px;">
                    <h6 class="fw-bold mb-4">Patient Metadata</h6>
                    <div class="mb-3">
                        <small class="text-muted d-block">Full Name</small>
                        <span class="fw-bold"><?php echo htmlspecialchars($name); ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Ghana Card</small>
                        <span class="fw-bold"><?php echo htmlspecialchars($user['user_metadata']['ghana_card'] ?? 'Not Linked'); ?></span>
                    </div>
                     <div class="mb-3">
                        <small class="text-muted d-block">NHIS #</small>
                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($user['user_metadata']['nhis_membership_number'] ?? 'Not Linked'); ?></span>
                    </div>
                    <hr>
                    <small class="text-muted small">Data protected under Data Protection Act 2012.</small>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-5 p-4 mb-4">
                    <h5 class="fw-bold mb-4">Visit History</h5>
                    <div class="timeline">
                        <!-- Visit 1 -->
                        <div class="d-flex mb-5">
                            <div class="text-center me-4" style="min-width: 80px;">
                                <h4 class="fw-bold mb-0">15</h4>
                                <small class="text-muted uppercase">MAR '26</small>
                            </div>
                            <div class="flex-grow-1 border-start ps-4">
                                <h6 class="fw-bold mb-1">General OPD Consultation</h6>
                                <p class="text-muted small mb-3">Diagnostic: Upper Respiratory Tract Infection (URTI)</p>
                                <div class="card p-3 bg-light border-0 rounded-4">
                                    <h6 class="fw-bold small mb-2 text-primary">Doctor Notes</h6>
                                    <p class="small mb-0">Patient presented with mild fever and sore throat. Pulse stable. Prescribed Amoxicillin and Paracetamol.</p>
                                </div>
                            </div>
                        </div>
                        <!-- Visit 2 -->
                        <div class="d-flex mb-5">
                            <div class="text-center me-4" style="min-width: 80px;">
                                <h4 class="fw-bold mb-0">12</h4>
                                <small class="text-muted uppercase">MAR '26</small>
                            </div>
                            <div class="flex-grow-1 border-start ps-4">
                                <h6 class="fw-bold mb-1">Laboratory Unit</h6>
                                <p class="text-muted small mb-3">Service: Malaria Parasite (mRDT)</p>
                                <div class="badge bg-danger-soft text-danger rounded-pill px-3">Result: POSITIVE (+)</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
