<?php
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$sb = new Supabase();
$currentUser = $_SESSION['user'];
$role = $currentUser['user_metadata']['role'] ?? 'patient';
$targetPatientId = $_GET['patient_id'] ?? $currentUser['id'];

// Determine redirect dashboard
$dashboardLink = '/dashboard_patient.php';
if ($role === 'guardian') $dashboardLink = '/dashboard_guardian.php';
if ($role === 'admin') $dashboardLink = '/dashboard_admin.php';
if (in_array($role, ['doctor', 'nurse', 'pharmacist', 'technician'])) $dashboardLink = '/dashboard_staff.php';

// 1. Permission Check
$canView = ($targetPatientId === $currentUser['id']); // Self
if (!$canView && $role === 'admin') $canView = true; // Admin
if (!$canView && in_array($role, ['doctor', 'nurse', 'pharmacist', 'technician'])) $canView = true; // Staff

if (!$canView && $role === 'guardian') {
    // Check for an APPROVED link in the guardians table
    $linkRes = $sb->request('GET', '/rest/v1/guardians?guardian_id=eq.' . $currentUser['id'] . '&patient_id=eq.' . $targetPatientId . '&status=eq.approved&select=id', null, true);
    if ($linkRes['status'] === 200 && !empty($linkRes['data'])) {
        $canView = true;
    }
}

if (!$canView) {
    header('Location: ' . $dashboardLink . '?error=unauthorized_access');
    exit;
}

// 2. Fetch Patient Profile (Use service key to ensure authorized viewers can see metadata)
$profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . urlencode($targetPatientId) . '&select=*', null, true);
$patient = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : null;

if (!$patient) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Not Found - GGHMS</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center p-5 card border-0 shadow-sm rounded-5" style="max-width: 500px;">
            <div class="bg-danger-soft text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 80px; height: 80px;">
                <i class="bi bi-person-x fs-1"></i>
            </div>
            <h2 class="fw-bold mb-3">Record Not Found</h2>
            <p class="text-muted mb-4">We couldn't find a medical record for the requested patient ID. Please verify the link or contact administration.</p>
            <a href="<?php echo $dashboardLink; ?>" class="btn btn-primary rounded-pill px-5">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 3. Fetch Unified Clinical History (Raw fetches for debugging)
// Vitals (Nursing)
$vitalsRes = $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . $targetPatientId . '&select=*&order=recorded_at.desc', null, true);
$vitals = ($vitalsRes['status'] === 200) ? $vitalsRes['data'] : [];

// Lab Requests (Laboratory)
$labsRes = $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . $targetPatientId . '&select=*&order=created_at.desc', null, true);
$labs = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

// Consultations (General OPD)
$consultsRes = $sb->request('GET', '/rest/v1/consultations?patient_id=eq.' . $targetPatientId . '&select=*&order=created_at.desc', null, true);
$consults = ($consultsRes['status'] === 200) ? $consultsRes['data'] : [];

// Prescriptions
$prescriptions = []; // Temporary bypass

// Combine into a unified timeline
foreach ($vitals as &$v) { $v['emr_type'] = 'nursing'; $v['sort_date'] = $v['recorded_at']; }
foreach ($labs as &$l) { $l['emr_type'] = 'laboratory'; $l['sort_date'] = $l['created_at']; }
foreach ($consults as &$c) { $c['emr_type'] = 'opd'; $c['sort_date'] = $c['created_at']; }

$timeline = array_merge($vitals, $labs, $consults);
usort($timeline, function($a, $b) {
    return strtotime($b['sort_date'] ?? 'now') - strtotime($a['sort_date'] ?? 'now');
});

// Helper for Search
$allPatientsRes = ($role !== 'patient' && $role !== 'guardian') ? $sb->request('GET', '/rest/v1/profiles?role=eq.patient&select=id,name', null, true) : null;
$searchList = ($allPatientsRes && $allPatientsRes['status'] === 200) ? $allPatientsRes['data'] : [];
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
                <p class="text-muted">Comprehensive history of clinical visits and treatments.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if (!empty($searchList)): ?>
                    <form class="d-flex" action="/emr.php" method="GET">
                        <select name="patient_id" class="form-select rounded-pill me-2 border-primary" onchange="this.form.submit()" style="min-width: 250px;">
                            <option value="">🔍 Search Patient Profile...</option>
                            <?php foreach($searchList as $sp): ?>
                                <option value="<?php echo $sp['id']; ?>" <?php echo ($sp['id'] === $targetPatientId ? 'selected' : ''); ?>>
                                    <?php echo htmlspecialchars($sp['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <button class="btn btn-outline-primary rounded-pill px-4" onclick="window.print()"><i class="bi bi-printer me-2"></i> Print Record</button>
            </div>
        </header>

        <div class="row g-4">
            <div class="col-lg-3">
                <div class="card border-0 shadow-sm rounded-5 p-4 sticky-top" style="top: 100px;">
                    <h6 class="fw-bold mb-4">Patient Metadata</h6>
                    <div class="mb-3">
                        <small class="text-muted d-block">Full Name</small>
                        <span class="fw-bold"><?php echo htmlspecialchars($patient['name']); ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Ghana Card</small>
                        <span class="fw-bold"><?php echo htmlspecialchars($patient['ghana_card'] ?? 'Not Linked'); ?></span>
                    </div>
                     <div class="mb-3">
                        <small class="text-muted d-block">NHIS #</small>
                        <span class="fw-bold text-primary"><?php echo htmlspecialchars($patient['nhis_membership_number'] ?? 'Not Linked'); ?></span>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted d-block">Blood Group</small>
                        <span class="fw-bold text-danger"><?php echo htmlspecialchars($patient['blood_group'] ?? 'Unknown'); ?></span>
                    </div>
                    <hr>
                    <div class="d-grid gap-2">
                        <?php if ($role === 'nurse'): ?>
                            <button class="btn btn-primary rounded-pill" data-bs-toggle="modal" data-bs-target="#vitalsModal">
                                <i class="bi bi-thermometer-half me-2"></i> Log Daily Vitals
                            </button>
                        <?php elseif ($role === 'doctor'): ?>
                            <button class="btn btn-success rounded-pill" data-bs-toggle="modal" data-bs-target="#consultationModal">
                                <i class="bi bi-journal-medical me-2"></i> Start Consultation
                            </button>
                        <?php endif; ?>
                    </div>
                    <hr>
                    <small class="text-muted small">Data protected under Data Protection Act 2012.</small>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="card border-0 shadow-sm rounded-5 p-4 mb-4">
                    <h5 class="fw-bold mb-4">Visit History</h5>
                    <div class="timeline">
                        <?php if (empty($timeline)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-folder2-open display-1 text-light mb-3"></i>
                                <p class="text-muted">No medical records found for this patient.</p>
                            </div>
                        <?php else: foreach ($timeline as $entry): 
                            $date = strtotime($entry['sort_date'] ?? 'now');
                        ?>
                            <div class="d-flex mb-5">
                                <div class="text-center me-4" style="min-width: 80px;">
                                    <h4 class="fw-bold mb-0"><?php echo date('d', $date); ?></h4>
                                    <small class="text-muted text-uppercase"><?php echo date('M \'y', $date); ?></small>
                                </div>
                                <div class="flex-grow-1 border-start ps-4">
                                     <?php if (($entry['emr_type'] ?? '') === 'nursing'): // Nursing / Vitals ?>
                                         <div class="d-flex justify-content-between align-items-center mb-1">
                                             <h6 class="fw-bold text-primary mb-0"><i class="bi bi-thermometer-half me-1"></i> NURSING: Vitals Recorded</h6>
                                             <small class="text-muted small">Recorded by: <?php echo htmlspecialchars($entry['staff']['name'] ?? 'Staff'); ?></small>
                                         </div>
                                         <div class="row g-2 mt-2">
                                             <div class="col-6 col-md-3">
                                                 <div class="p-2 bg-light rounded-3 small">
                                                     <span class="d-block text-muted text-uppercase extra-small font-monospace" style="font-size: 0.65rem;">Temp</span>
                                                     <span class="fw-bold"><?php echo !empty($entry['temperature']) ? $entry['temperature'].'°C' : '--'; ?></span>
                                                 </div>
                                             </div>
                                             <div class="col-6 col-md-3">
                                                 <div class="p-2 bg-light rounded-3 small">
                                                     <span class="d-block text-muted text-uppercase extra-small font-monospace" style="font-size: 0.65rem;">BP</span>
                                                     <span class="fw-bold"><?php echo !empty($entry['blood_pressure']) ? $entry['blood_pressure'] : '--'; ?></span>
                                                 </div>
                                             </div>
                                             <div class="col-6 col-md-3">
                                                 <div class="p-2 bg-light rounded-3 small">
                                                     <span class="d-block text-muted text-uppercase extra-small font-monospace" style="font-size: 0.65rem;">Pulse</span>
                                                     <span class="fw-bold"><?php echo !empty($entry['pulse']) ? $entry['pulse'].' bpm' : '--'; ?></span>
                                                 </div>
                                             </div>
                                             <div class="col-6 col-md-3">
                                                 <div class="p-2 bg-light rounded-3 small">
                                                     <span class="d-block text-muted text-uppercase extra-small font-monospace" style="font-size: 0.65rem;">Weight</span>
                                                     <span class="fw-bold"><?php echo !empty($entry['weight']) ? $entry['weight'].' kg' : '--'; ?></span>
                                                 </div>
                                             </div>
                                         </div>
                                     <?php elseif (($entry['emr_type'] ?? '') === 'opd'): // General OPD / Consultation ?>
                                         <div class="d-flex justify-content-between align-items-center mb-1">
                                             <h6 class="fw-bold text-success mb-0"><i class="bi bi-journal-check me-1"></i> GENERAL OPD: Consultation</h6>
                                             <small class="text-muted small">Dr. <?php echo htmlspecialchars($entry['doctor']['name'] ?? 'Medical Staff'); ?></small>
                                         </div>
                                         <div class="card p-3 bg-white border rounded-4 mt-2">
                                             <?php if (!empty($entry['diagnosis'])): ?>
                                                <h6 class="fw-bold small text-danger mb-2">Diagnosis: <?php echo htmlspecialchars($entry['diagnosis']); ?></h6>
                                             <?php endif; ?>
                                             <p class="small mb-0" style="white-space: pre-wrap;"><?php echo htmlspecialchars($entry['notes']); ?></p>
                                         </div>
                                         
                                         <?php 
                                         $linkedPrescriptions = array_filter($prescriptions, fn($pr) => $pr['consultation_id'] === $entry['id']);
                                         if (!empty($linkedPrescriptions)): ?>
                                             <div class="mt-3">
                                                 <small class="fw-bold text-muted d-block mb-2">Prescriptions & Pharmacy Flow:</small>
                                                 <?php foreach($linkedPrescriptions as $pr): ?>
                                                     <div class="p-3 rounded-4 mb-2 border <?php echo ($pr['status'] === 'dispensed') ? 'bg-success-soft border-success' : 'bg-primary-soft border-primary'; ?>">
                                                         <div class="d-flex justify-content-between align-items-start">
                                                             <div class="flex-grow-1">
                                                                <span class="small fw-bold <?php echo ($pr['status'] === 'dispensed') ? 'text-success' : 'text-primary'; ?>">
                                                                    <i class="bi bi-capsule me-1"></i> <?php echo htmlspecialchars($pr['medication_name']); ?>
                                                                </span>
                                                                <?php if (!empty($pr['dispense_notes'])): ?>
                                                                    <p class="extra-small text-muted mt-2 mb-0 border-top pt-2">
                                                                        <i class="bi bi-info-circle me-1"></i> <strong>Pharmacist Note:</strong> <?php echo htmlspecialchars($pr['dispense_notes']); ?>
                                                                    </p>
                                                                <?php endif; ?>
                                                             </div>
                                                             <?php if ($pr['status'] === 'dispensed'): ?>
                                                                 <div class="text-end">
                                                                    <span class="badge bg-success rounded-pill extra-small"><i class="bi bi-check-circle-fill me-1"></i> Dispensed</span>
                                                                    <small class="d-block text-muted extra-small mt-1">by <?php echo htmlspecialchars($pr['pharmacist']['name'] ?? 'Pharmacist'); ?></small>
                                                                 </div>
                                                             <?php else: ?>
                                                                 <span class="badge bg-primary rounded-pill extra-small px-3">Pending</span>
                                                             <?php endif; ?>
                                                         </div>
                                                     </div>
                                                 <?php endforeach; ?>
                                             </div>
                                         <?php endif; ?>
 
                                     <?php elseif (($entry['emr_type'] ?? '') === 'laboratory'): // Laboratory / Tests ?>
                                         <div class="d-flex justify-content-between align-items-center mb-1">
                                             <h6 class="fw-bold text-info mb-0"><i class="bi bi-clipboard2-pulse me-1"></i> LABORATORY: <?php echo htmlspecialchars($entry['test_name']); ?></h6>
                                             <?php if ($entry['status'] === 'completed'): ?>
                                                 <small class="text-muted small">Recorded by: <?php echo htmlspecialchars($entry['tech']['name'] ?? 'Technician'); ?></small>
                                             <?php else: ?>
                                                 <small class="text-muted small">Status: <span class="badge bg-warning text-dark">Pending Result</span></small>
                                             <?php endif; ?>
                                         </div>
                                         <p class="text-muted small mb-2">Requested by: Dr. <?php echo htmlspecialchars($entry['doctor']['name'] ?? 'Medical Staff'); ?></p>
                                         <?php if ($entry['result_text']): ?>
                                             <div class="card p-3 bg-light border-0 rounded-4">
                                                 <h6 class="fw-bold small mb-2 text-danger">Diagnostic Finding</h6>
                                                 <p class="small mb-0"><?php echo nl2br(htmlspecialchars($entry['result_text'])); ?></p>
                                             </div>
                                         <?php endif; ?>
                                     <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- VITALS MODAL (For Nurses) -->
    <div class="modal fade" id="vitalsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header bg-primary text-white border-0 py-3">
                    <h5 class="fw-bold mb-0">Record Daily Vitals</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="/api/consultation/save.php" method="POST">
                        <input type="hidden" name="patient_id" value="<?php echo $targetPatientId; ?>">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Temperature (°C)</label>
                                <input type="number" step="0.1" name="temperature" class="form-control rounded-4" placeholder="36.5">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Blood Pressure</label>
                                <input type="text" name="blood_pressure" class="form-control rounded-4" placeholder="120/80">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Weight (kg)</label>
                                <input type="number" step="0.1" name="weight" class="form-control rounded-4" placeholder="70.5">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Pulse (bpm)</label>
                                <input type="number" name="pulse" class="form-control rounded-4" placeholder="72">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill mt-4">Save Vitals</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CONSULTATION MODAL (For Doctors) -->
    <div class="modal fade" id="consultationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header bg-success text-white border-0 py-3">
                    <h5 class="fw-bold mb-0">Start Clinical Consultation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <form action="/api/consultation/save.php" method="POST">
                        <input type="hidden" name="patient_id" value="<?php echo $targetPatientId; ?>">
                        
                        <div class="row g-3 mb-4">
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1">Temp (°C)</label>
                                <input type="number" step="0.1" name="temperature" class="form-control rounded-4">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1">BP</label>
                                <input type="text" name="blood_pressure" class="form-control rounded-4">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1">Pulse (bpm)</label>
                                <input type="number" name="pulse" class="form-control rounded-4">
                            </div>
                            <div class="col-md-3">
                                <label class="small fw-bold text-muted mb-1">Weight (kg)</label>
                                <input type="number" step="0.1" name="weight" class="form-control rounded-4">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control rounded-4" placeholder="Primary diagnosis..." required>
                        </div>
                        <div class="mb-3">
                            <label class="small fw-bold text-muted mb-1">Clinical Notes</label>
                            <textarea name="notes" class="form-control rounded-4" rows="4" placeholder="Detailed symptoms and observations..." required></textarea>
                        </div>
                        
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="recommend_admission" id="admCheck">
                            <label class="form-check-label ms-2" for="admCheck">Recommend Immediate Admission</label>
                        </div>

                        <button type="submit" class="btn btn-success w-100 rounded-pill">Complete Consultation & Save Record</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
