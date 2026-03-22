<?php
// Consultation Interface - Kobby Moore Hospital
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
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

// Fetch available drugs for the prescription list
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];

// Fetch available wards for the admission selection
$wardsRes = $sb->request('GET', '/rest/v1/wards?select=*&order=ward_name.asc', null, true);
$wards = ($wardsRes['status'] === 200) ? $wardsRes['data'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - Kobby Moore Hospital</title>
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
                <form id="consultationForm" action="/api/consultation/save.php" method="POST" class="card border-0 shadow-sm rounded-5 p-4 mb-4">
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
                    
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="recommend_admission" id="admissionCheck" onchange="toggleAdmissionFields()">
                            <label class="form-check-label fw-bold" for="admissionCheck">Recommend Admission</label>
                        </div>

                        <div id="admission-fields" class="d-none bg-light p-3 rounded-4 border">
                            <h6 class="fw-bold mb-3 small text-primary"><i class="bi bi-hospital me-2"></i>Ward & Bed Assignment</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="extra-small text-muted">Select Ward</label>
                                    <select name="ward_id" class="form-select form-select-sm rounded-pill border-0 shadow-sm" onchange="updateBedDisplay()">
                                        <option value="">-- Select Ward --</option>
                                        <?php foreach($wards as $w): 
                                            $isFull = ($w['occupied_beds'] >= $w['total_beds']);
                                        ?>
                                            <option value="<?php echo $w['id']; ?>" <?php echo $isFull ? 'disabled' : ''; ?> data-fee="<?php echo $w['admission_fee']; ?>" data-free="<?php echo $w['total_beds'] - $w['occupied_beds']; ?>">
                                                <?php echo htmlspecialchars($w['ward_name']); ?> (₵<?php echo number_format($w['admission_fee'], 0); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="extra-small text-muted">Bed Number (Auto-assign)</label>
                                    <input type="text" name="bed_number" class="form-control form-control-sm rounded-pill border-0 shadow-sm" placeholder="e.g. BED-A1">
                                </div>
                            </div>
                            <p class="extra-small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i> Occupancy will be updated automatically upon submission.</p>
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
                <div class="card border-0 shadow-sm rounded-5 p-4 sticky-top" style="top: 20px;">
                    <h5 class="fw-bold mb-3 text-primary"><i class="bi bi-capsule me-2"></i>E-Prescription</h5>
                    <p class="text-muted small mb-3">Select medications from live inventory. You can add multiple drugs below.</p>
                    
                    <div id="medication-list-consult">
                        <div class="card bg-light border-0 rounded-4 mb-3 medication-item">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 small"><i class="bi bi-plus-circle-fill text-primary"></i> Med 01</h6>
                                    <button type="button" class="btn btn-sm btn-outline-danger border-0 rounded-circle remove-med-btn d-none" onclick="this.closest('.medication-item').remove()">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                                <div class="mb-2">
                                    <select name="meds[0][drug_id]" form="consultationForm" class="form-select form-select-sm rounded-4 border-0 shadow-sm">
                                        <option value="">-- Select Drug --</option>
                                        <?php 
                                        // We need to fetch drugs in consultation.php too or assume they are available if we add the fetch
                                        foreach ($availableDrugs as $drug): ?>
                                            <option value="<?php echo $drug['id']; ?>" <?php echo ($drug['stock_count'] <= 0 ? 'disabled' : ''); ?>>
                                                <?php echo htmlspecialchars($drug['drug_name']); ?> (<?php echo $drug['stock_count']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="row g-2 mb-2">
                                    <div class="col-12"><input type="text" name="meds[0][dosage]" form="consultationForm" class="form-control form-control-sm rounded-pill" placeholder="Dosage (500mg)"></div>
                                    <div class="col-6"><input type="text" name="meds[0][frequency]" form="consultationForm" class="form-control form-control-sm rounded-pill" placeholder="Freq (2x)"></div>
                                    <div class="col-6"><input type="text" name="meds[0][duration]" form="consultationForm" class="form-control form-control-sm rounded-pill" placeholder="Dur (7d)"></div>
                                </div>
                                <div class="mb-0">
                                    <label class="extra-small text-muted mb-1 d-block ms-2">Total Quantity to Dispense</label>
                                    <input type="number" name="meds[0][quantity]" form="consultationForm" class="form-control form-control-sm rounded-pill" placeholder="Total Qty (e.g. 10)" min="1" value="1">
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="button" id="add-med-consult-btn" class="btn btn-outline-primary w-100 rounded-pill btn-sm mb-3">
                        <i class="bi bi-plus-lg me-1"></i> Add Another
                    </button>
                    
                    <hr>
                    <button type="submit" form="consultationForm" class="btn btn-primary w-100 rounded-pill py-2 shadow-sm">
                        <i class="bi bi-check-circle-fill me-2"></i> Submit Visit
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Lab Request Modal -->
    <div class="modal fade" id="addLabModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold">Order Lab Test</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="/api/lab/create.php" method="POST">
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
    <script>
        let medCount = 1;

        document.getElementById('add-med-consult-btn').addEventListener('click', function() {
            const container = document.getElementById('medication-list-consult');
            const firstItem = container.querySelector('.medication-item');
            const newItem = firstItem.cloneNode(true);
            
            newItem.querySelector('.remove-med-btn').classList.remove('d-none');
            newItem.querySelectorAll('input').forEach(input => input.value = '');
            newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            
            const inputs = newItem.querySelectorAll('[name^="meds[0]"]');
            inputs.forEach(input => {
                const oldName = input.getAttribute('name');
                const newName = oldName.replace('meds[0]', `meds[${medCount}]`);
                input.setAttribute('name', newName);
            });
            
            newItem.querySelector('h6').innerHTML = `<i class="bi bi-plus-circle-fill text-primary"></i> Med ${String(medCount + 1).padStart(2, '0')}`;
            
            container.appendChild(newItem);
            medCount++;
        });

        function toggleAdmissionFields() {
            const check = document.getElementById('admissionCheck');
            const fields = document.getElementById('admission-fields');
            if (check.checked) {
                fields.classList.remove('d-none');
            } else {
                fields.classList.add('d-none');
            }
        }

        function updateBedDisplay() {
            // Optional: Can add logic to suggest next available bed via API
        }
    </script>
</body>
</html>
