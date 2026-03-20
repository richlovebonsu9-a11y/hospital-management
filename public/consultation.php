<?php
// Consultation Interface - GGHMS
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'doctor') {
    header('Location: /login');
    exit;
}

$patient_id = $_GET['patient_id'] ?? 'P-1002';
$patient_name = $_GET['name'] ?? 'Kwame Mensah';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div class="d-flex align-items-center">
                <a href="/dashboard_doctor.php" class="btn btn-light rounded-circle me-3"><i class="bi bi-arrow-left"></i></a>
                <div>
                    <h2 class="fw-bold mb-0">Consultation: <?php echo htmlspecialchars($patient_name); ?></h2>
                    <p class="text-muted mb-0">ID: <?php echo htmlspecialchars($patient_id); ?> | M, 42 yrs</p>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-danger rounded-pill px-4"><i class="bi bi-heart-fill me-2"></i> View EMR</button>
                <button class="btn btn-primary rounded-pill px-4">Finish Visit</button>
            </div>
        </header>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Vitals & Notes -->
                <div class="card border-0 shadow-sm rounded-5 p-4 mb-4">
                    <h5 class="fw-bold mb-4">Patient Vitals & Examination</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Temp (°C)</label>
                            <input type="text" class="form-control rounded-pill border-light bg-light" value="37.2">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">BP (mmHg)</label>
                            <input type="text" class="form-control rounded-pill border-light bg-light" value="120/80">
                        </div>
                        <div class="col-md-3">
                             <label class="form-label small text-muted">Weight (kg)</label>
                            <input type="text" class="form-control rounded-pill border-light bg-light" value="75">
                        </div>
                        <div class="col-md-3">
                             <label class="form-label small text-muted">Pulse (bpm)</label>
                            <input type="text" class="form-control rounded-pill border-light bg-light" value="72">
                        </div>
                    </div>
                    <label class="form-label fw-bold">Clinical Notes</label>
                    <textarea class="form-control rounded-4 p-4 border-light bg-light" rows="10" placeholder="Type patient symptoms, history, and examination findings here..."></textarea>
                </div>

                <!-- Orders (Lab & Radiology) -->
                <div class="card border-0 shadow-sm rounded-5 p-4">
                     <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Diagnostic Orders</h5>
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3">+ Add Request</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="small text-muted">
                                <tr>
                                    <th>Service Type</th>
                                    <th>Specific Test</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Laboratory</td>
                                    <td>Full Blood Count (FBC)</td>
                                    <td><span class="badge bg-primary-soft text-primary rounded-pill px-3">Ordered</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- E-Prescription -->
                <div class="card border-0 shadow-sm rounded-5 p-4 mb-4 bg-white">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0 text-primary">Electronic Prescription</h5>
                        <i class="bi bi-capsule h4 text-primary mb-0"></i>
                    </div>
                    <form>
                        <div class="mb-3">
                            <input type="text" class="form-control rounded-pill px-3 py-2 border-light bg-light small" placeholder="Search medication...">
                        </div>
                        <div class="prescription-list mb-4">
                            <div class="p-3 bg-light rounded-4 mb-2 d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold small">Amoxicillin 500mg</div>
                                    <small class="text-muted">1x3 daily | 5 Days</small>
                                </div>
                                <button class="btn btn-sm text-danger"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <button class="btn btn-primary w-100 py-3 fw-bold rounded-pill">Send to Pharmacy &rarr;</button>
                    </form>
                </div>

                <!-- Diagnosis -->
                <div class="card border-0 shadow-sm rounded-5 p-4">
                    <h5 class="fw-bold mb-4">Diagnosis</h5>
                    <div class="mb-3">
                         <label class="form-label small text-muted">ICD-10 / Common Name</label>
                         <input type="text" class="form-control rounded-pill border-light bg-light" placeholder="e.g. Malaria, URTI">
                    </div>
                    <div class="form-check form-switch mb-3">
                      <input class="form-check-input" type="checkbox" id="admissionCheck">
                      <label class="form-check-label" for="admissionCheck">Recommend Admission</label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
