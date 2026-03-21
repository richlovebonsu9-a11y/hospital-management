<?php
// Consultation Interface - GGHMS
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'doctor') {
    header('Location: /login');
    exit;
}

$patient_id = $_GET['patient_id'] ?? '';
if (!$patient_id) { header('Location: /dashboard_doctor.php'); exit; }

$sb = new Supabase();
// Fetch existing lab requests for this patient to show in the table
$labsRes = $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . $patient_id . '&order=created_at.desc');
$labRequests = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

// Fetch latest vitals for this patient
$vitalsRes = $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . $patient_id . '&order=recorded_at.desc&limit=1', null, true);
$latestVitals = ($vitalsRes['status'] === 200 && !empty($vitalsRes['data'])) ? $vitalsRes['data'][0] : null;

$patient_name = "Patient " . substr($patient_id, 0, 8);
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
                <a href="/emr.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-outline-danger rounded-pill px-4"><i class="bi bi-heart-fill me-2"></i> View EMR</a>
                <button type="submit" form="consultationForm" class="btn btn-primary rounded-pill px-4">Finish Visit</button>
            </div>
        </header>

        <div class="row g-4">
            <div class="col-lg-8">
                <!-- Vitals & Notes -->
                <form id="consultationForm" action="/api/consultation/save" method="POST" class="card border-0 shadow-sm rounded-5 p-4 mb-4">
                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
                    
                    <?php if (isset($_GET['vitals_requested'])): ?>
                        <div class="alert alert-success border-0 rounded-4 small py-2 mb-4"><i class="bi bi-check-circle-fill me-2"></i>Nurse triage requested successfully.</div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Patient Vitals & Examination</h5>
                        <button type="submit" form="requestVitalsForm" class="btn btn-outline-primary btn-sm rounded-pill"><i class="bi bi-bell-fill me-1"></i>Request Fresh Vitals</button>
                    </div>

                    <!-- Nurse Triage Vitals Read-Only Display -->
                    <?php if ($latestVitals): ?>
                    <div class="bg-primary-soft text-primary rounded-4 p-3 mb-4 small">
                        <div class="d-flex justify-content-between mb-2">
                            <strong><i class="bi bi-activity me-1"></i>Latest recorded vitals</strong>
                            <span class="opacity-75"><?php echo date('M d, H:i', strtotime($latestVitals['recorded_at'])); ?></span>
                        </div>
                        <div class="row g-3">
                            <div class="col-3">Temp: <strong><?php echo htmlspecialchars($latestVitals['temperature'] ?? '-'); ?>°C</strong></div>
                            <div class="col-3">BP: <strong><?php echo htmlspecialchars($latestVitals['blood_pressure'] ?? '-'); ?></strong></div>
                            <div class="col-3">Weight: <strong><?php echo htmlspecialchars($latestVitals['weight'] ?? '-'); ?>kg</strong></div>
                            <div class="col-3">Pulse: <strong><?php echo htmlspecialchars($latestVitals['pulse'] ?? '-'); ?>bpm</strong></div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="bg-light text-muted rounded-4 p-3 mb-4 small border">
                        <i class="bi bi-exclamation-triangle me-1"></i>No vitals recorded by nurse yet for this visit.
                    </div>
                    <?php endif; ?>
                    <div class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label small text-muted">Temp (°C)</label>
                            <input type="number" step="0.1" name="temperature" class="form-control rounded-pill border-light bg-light">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small text-muted">BP (mmHg)</label>
                            <input type="text" name="blood_pressure" class="form-control rounded-pill border-light bg-light" placeholder="120/80">
                        </div>
                        <div class="col-md-3">
                             <label class="form-label small text-muted">Weight (kg)</label>
                            <input type="number" step="0.1" name="weight" class="form-control rounded-pill border-light bg-light">
                        </div>
                        <div class="col-md-3">
                             <label class="form-label small text-muted">Pulse (bpm)</label>
                            <input type="number" name="pulse" class="form-control rounded-pill border-light bg-light">
                        </div>
                    </div>
                    <label class="form-label fw-bold">Clinical Notes</label>
                    <textarea name="notes" class="form-control rounded-4 p-4 border-light bg-light" rows="6" placeholder="Type patient symptoms, history, and examination findings here..."></textarea>
                    
                    <div class="mt-4">
                        <h5 class="fw-bold mb-3">Diagnosis</h5>
                        <input type="text" name="diagnosis" class="form-control rounded-pill border-light bg-light mb-3" placeholder="e.g. Malaria, URTI">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="recommend_admission" id="admissionCheck">
                            <label class="form-check-label" for="admissionCheck">Recommend Admission</label>
                        </div>
                    </div>
                </form>

                <!-- Orders (Lab & Radiology) -->
                <div class="card border-0 shadow-sm rounded-5 p-4">
                     <div class="d-flex justify-content-between align-items-center mb-4">
                        <h5 class="fw-bold mb-0">Diagnostic Orders</h5>
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addLabModal">+ Add Request</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead class="small text-muted">
                                <tr>
                                    <th>Date</th>
                                    <th>Service Type</th>
                                    <th>Specific Test</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($labRequests)): ?>
                                    <tr><td colspan="4" class="text-center text-muted small py-3">No orders placed yet.</td></tr>
                                <?php endif; foreach ($labRequests as $lr): ?>
                                <tr>
                                    <td><?php echo date('M d', strtotime($lr['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($lr['test_type']); ?></td>
                                    <td><?php echo htmlspecialchars($lr['test_name']); ?></td>
                                    <td><span class="badge <?php echo ($lr['status'] === 'completed') ? 'bg-success' : 'bg-primary'; ?>-soft text-<?php echo ($lr['status'] === 'completed') ? 'success' : 'primary'; ?> rounded-pill px-3"><?php echo htmlspecialchars($lr['status']); ?></span></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- E-Prescription -->
                    <div class="mt-4">
                        <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-capsule me-2"></i>E-Prescription</h5>
                        <p class="text-muted small mb-3">Describe medications, dosage, and frequency below. This will be transmitted to the pharmacy immediately.</p>
                        <textarea name="medication_details" form="consultationForm" class="form-control rounded-4 p-4 border-light bg-light small" rows="5" placeholder="Enter medication details... e.g. Amoxicillin 500mg, 1x3 daily for 5 days"></textarea>
                    </div>
                </form>

    <!-- Add Lab Request Modal -->
    <div class="modal fade" id="addLabModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Order Lab Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="/api/lab/create" method="POST">
                    <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label text-muted small">Service Type</label>
                            <select name="test_type" class="form-select rounded-pill px-3">
                                <option>Laboratory</option>
                                <option>Radiology (X-Ray/Scan)</option>
                                <option>Cardiology Test</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label text-muted small">Test Name</label>
                            <input type="text" name="test_name" class="form-control rounded-pill px-3" placeholder="e.g. Malaria RDT, Full Blood Count" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill">Place Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Requesting Vitals -->
    <form id="requestVitalsForm" action="/api/consultation/request_vitals.php" method="POST" class="d-none">
        <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
    </form>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
