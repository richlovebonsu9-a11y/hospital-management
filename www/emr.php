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
$dashboardLink = '/dashboard_patient';
if ($role === 'guardian') $dashboardLink = '/dashboard_guardian';
if ($role === 'admin') $dashboardLink = '/dashboard_admin';
if (in_array($role, ['doctor', 'nurse', 'pharmacist', 'technician'])) $dashboardLink = '/dashboard_staff';

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

// Fetch Drugs for Prescription (for Doctors)
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];

if (!$patient) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Not Found - Kobby Moore Hospital</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body class="bg-light d-flex align-items-center justify-content-center" style="min-height: 100vh;">
        <div class="text-center p-5 card border-0 shadow-lg rounded-5" style="max-width: 500px;">
            <div class="bg-danger-soft text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-4 mx-auto" style="width: 80px; height: 80px;">
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

// 3. Fetch Unified Clinical History
$vitalsRes = $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . $targetPatientId . '&select=*,staff:profiles!recorded_by(name)&order=recorded_at.desc', null, true);
$vitals = ($vitalsRes['status'] === 200) ? $vitalsRes['data'] : [];

$labsRes = $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . $targetPatientId . '&select=*,requester:profiles!requester_id(name,role),doctor:profiles!doctor_id(name),tech:profiles!completed_by_id(name)&order=created_at.desc', null, true);
$labs = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

$consultsRes = $sb->request('GET', '/rest/v1/consultations?patient_id=eq.' . $targetPatientId . '&select=*,doctor:profiles!doctor_id(name)&order=created_at.desc', null, true);
$consults = ($consultsRes['status'] === 200) ? $consultsRes['data'] : [];

$rxRes = $sb->request('GET', '/rest/v1/prescriptions?patient_id=eq.' . $targetPatientId . '&select=*,pharmacist:profiles!dispensed_by(name)&order=created_at.desc', null, true);
$prescriptions = ($rxRes['status'] === 200) ? $rxRes['data'] : [];

// Combine into a unified timeline
foreach ($vitals as &$v) { $v['emr_type'] = 'nursing'; $v['sort_date'] = $v['recorded_at']; }
foreach ($labs as &$l) { $l['emr_type'] = 'laboratory'; $l['sort_date'] = $l['created_at']; }
foreach ($consults as &$c) { $c['emr_type'] = 'opd'; $c['sort_date'] = $c['created_at']; }

$timeline = array_merge($vitals, $labs, $consults); // Note: Prescriptions are linked inside consults for cleaner display
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
    <title>Electronic Medical Record - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    
    <style>
        body { background-color: #F8FAFC; }
        .emr-header-banner {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            border-radius: 2rem;
            color: white;
            padding: 3rem;
            margin-bottom: 2rem;
            box-shadow: 0 15px 35px rgba(15, 23, 42, 0.1);
        }
        
        .timeline-node {
            width: 14px; height: 14px; border-radius: 50%;
            background: #cbd5e1; border: 3px solid #f8fafc;
            position: absolute; left: -7px; top: 30px;
            z-index: 2;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2.5rem;
            border-left: 2px solid #e2e8f0;
            margin-bottom: 2rem;
            margin-left: 1rem;
        }
        
        .timeline-card {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
            border: 1px solid #f1f5f9;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        
        .timeline-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
        }

        .card-accent { position: absolute; left: 0; top: 0; width: 6px; height: 100%; }
        .accent-nursing { background: #0EA5E9; }
        .accent-opd { background: #10B981; }
        .accent-lab { background: #8B5CF6; }

        .patient-avatar-lg {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: white; font-size: 2.5rem; font-weight: 700;
            border-radius: 1.5rem;
            display: flex; align-items: center; justify-content: center;
        }

        .vital-box {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 px-lg-5 py-4">
        
        <!-- Beautiful Header Banner -->
        <div class="emr-header-banner d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="d-flex align-items-center mb-4 mb-md-0">
                <div class="patient-avatar-lg me-4 shadow">
                    <?php echo strtoupper(substr($patient['name'] ?? 'P', 0, 1)); ?>
                </div>
                <div>
                    <h2 class="fw-bold mb-1 display-6"><?php echo htmlspecialchars($patient['name']); ?></h2>
                    <div class="d-flex gap-3 text-white-50 small mt-2">
                        <span><i class="bi bi-person-badge"></i> ID: <?php echo substr($targetPatientId, 0, 8); ?></span>
                        <span><i class="bi bi-droplet-half text-danger"></i> Blood: <?php echo htmlspecialchars($patient['blood_group'] ?? 'Unknown'); ?></span>
                        <span><i class="bi bi-card-heading text-info"></i> NHIS: <?php echo htmlspecialchars($patient['nhis_membership_number'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>
            
            <div class="d-flex flex-column align-items-md-end">
                <div class="d-flex gap-2 mb-3">
                    <?php if (!empty($searchList)): ?>
                        <div class="dropdown">
                            <button class="btn btn-outline-light rounded-pill px-4 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-search me-2"></i> Switch Patient
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4" style="max-height: 300px; overflow-y: auto;">
                                <?php foreach($searchList as $sp): ?>
                                    <li><a class="dropdown-item" href="/emr.php?patient_id=<?php echo $sp['id']; ?>"><?php echo htmlspecialchars($sp['name']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                    <a href="<?php echo $dashboardLink; ?>" class="btn btn-primary bg-white text-dark border-0 rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-house-door-fill me-2 text-primary"></i> Dashboard
                    </a>
                </div>
                
                <div class="d-flex gap-2">
                    <?php if ($role === 'nurse'): ?>
                        <button type="button" class="btn btn-info bg-opacity-25 border-0 text-white rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#vitalsModal">
                            <i class="bi bi-heart-pulse-fill me-1"></i> Log Vitals
                        </button>
                    <?php elseif ($role === 'doctor'): ?>
                        <button type="button" class="btn btn-success border-0 rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#consultationModal">
                            <i class="bi bi-journal-medical me-1"></i> Quick Consult
                        </button>
                    <?php endif; ?>
                    <?php if (in_array($role, ['doctor', 'patient', 'guardian'])): ?>
                        <button type="button" class="btn btn-light bg-opacity-25 border-0 text-white rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#labRequestModal">
                            <i class="bi bi-virus me-1"></i> Request Lab Test
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Clinical Timeline -->
        <div class="row g-5">
            <div class="col-lg-12">
                <h4 class="fw-bold mb-5 ps-3 border-start border-4 border-primary rounded-1">Clinical Timeline</h4>
                
                <div class="ps-2">
                    <?php if (empty($timeline)): ?>
                        <div class="text-center py-5 glass rounded-5">
                            <i class="bi bi-folder-x display-1 text-muted opacity-50 mb-3"></i>
                            <h4 class="text-muted fw-bold">No Records Found</h4>
                            <p class="text-secondary">This patient's timeline is currently empty.</p>
                        </div>
                    <?php else: foreach ($timeline as $entry): 
                        $date = strtotime($entry['sort_date'] ?? 'now');
                    ?>
                        <div class="timeline-item">
                            
                            <!-- Display Logic based on Entry Type -->
                            <?php if (($entry['emr_type'] ?? '') === 'nursing'): ?>
                                <div class="timeline-node" style="background: #0EA5E9;"></div>
                                <div class="timeline-card ps-4">
                                    <div class="card-accent accent-nursing"></div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-heart-pulse-fill text-info me-2"></i> Nursing Observation</h6>
                                        <div class="text-end">
                                            <small class="fw-bold text-dark d-block"><?php echo date('M d, Y', $date); ?></small>
                                            <small class="text-muted"><?php echo date('h:i A', $date); ?> by <?php echo htmlspecialchars($entry['staff']['name'] ?? 'Staff'); ?></small>
                                        </div>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-6 col-md-3">
                                            <div class="vital-box">
                                                <small class="text-secondary fw-bold text-uppercase d-block mb-1">Temperature</small>
                                                <span class="fs-5 fw-bold text-dark"><?php echo !empty($entry['temperature']) ? $entry['temperature'].'°C' : '--'; ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="vital-box">
                                                <small class="text-secondary fw-bold text-uppercase d-block mb-1">Blood Pressure</small>
                                                <span class="fs-5 fw-bold text-dark"><?php echo !empty($entry['blood_pressure']) ? $entry['blood_pressure'] : '--'; ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="vital-box">
                                                <small class="text-secondary fw-bold text-uppercase d-block mb-1">Pulse</small>
                                                <span class="fs-5 fw-bold text-danger"><?php echo !empty($entry['pulse']) ? $entry['pulse'].' bpm' : '--'; ?></span>
                                            </div>
                                        </div>
                                        <div class="col-6 col-md-3">
                                            <div class="vital-box">
                                                <small class="text-secondary fw-bold text-uppercase d-block mb-1">Weight</small>
                                                <span class="fs-5 fw-bold text-dark"><?php echo !empty($entry['weight']) ? $entry['weight'].' kg' : '--'; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php elseif (($entry['emr_type'] ?? '') === 'opd'): ?>
                                <div class="timeline-node" style="background: #10B981;"></div>
                                <div class="timeline-card ps-4">
                                    <div class="card-accent accent-opd"></div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-journal-medical text-success me-2"></i> Medical Consultation</h6>
                                        <div class="text-end">
                                            <small class="fw-bold text-dark d-block"><?php echo date('M d, Y', $date); ?></small>
                                            <small class="text-muted"><?php echo date('h:i A', $date); ?> by Dr. <?php echo htmlspecialchars($entry['doctor']['name'] ?? 'Staff'); ?></small>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-light rounded-4 p-4 border border-light">
                                        <?php if (!empty($entry['diagnosis'])): ?>
                                            <div class="mb-3">
                                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-2 rounded-pill fw-bold mb-2">
                                                    Diagnosis: <?php echo htmlspecialchars($entry['diagnosis']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <p class="mb-0 text-dark lh-lg" style="white-space: pre-wrap;"><?php echo htmlspecialchars($entry['notes']); ?></p>
                                    </div>

                                    <!-- Attached Prescriptions -->
                                    <?php 
                                    $linkedPrescriptions = array_filter($prescriptions, fn($pr) => $pr['consultation_id'] === $entry['id']);
                                    if (!empty($linkedPrescriptions)): ?>
                                        <div class="mt-4 pt-4 border-top">
                                            <h6 class="fw-bold text-secondary mb-3"><i class="bi bi-prescription me-2"></i> Prescribed Medications</h6>
                                            <div class="row g-2">
                                                <?php foreach($linkedPrescriptions as $pr): ?>
                                                    <div class="col-md-6 col-lg-4">
                                                        <div class="border rounded-4 p-3 <?php echo ($pr['status'] === 'dispensed') ? 'border-success bg-success-soft' : 'border-primary bg-primary-soft'; ?>">
                                                            <div class="fw-bold mb-1 <?php echo ($pr['status'] === 'dispensed') ? 'text-success' : 'text-primary'; ?>">
                                                                <i class="bi bi-capsule me-1"></i> <?php echo htmlspecialchars($pr['medication_name']); ?>
                                                            </div>
                                                            <div class="d-flex gap-2 small text-muted mb-2">
                                                                <span><i class="bi bi-clock"></i> <?php echo htmlspecialchars($pr['frequency']); ?></span>
                                                                <span><i class="bi bi-calendar3"></i> <?php echo htmlspecialchars($pr['duration']); ?></span>
                                                            </div>
                                                            <?php if ($pr['status'] === 'dispensed'): ?>
                                                                <div class="badge bg-success rounded-pill fw-normal shadow-sm px-3"><i class="bi bi-check-circle me-1"></i> Dispensed by <?php echo htmlspecialchars($pr['pharmacist']['name'] ?? 'Staff'); ?></div>
                                                            <?php else: ?>
                                                                <div class="badge bg-white text-primary rounded-pill border border-primary fw-normal shadow-sm px-3"><i class="bi bi-hourglass-split me-1"></i> Pharmacy Pending</div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                            <?php elseif (($entry['emr_type'] ?? '') === 'laboratory'): ?>
                                <div class="timeline-node" style="background: #8B5CF6;"></div>
                                <div class="timeline-card ps-4">
                                    <div class="card-accent accent-lab"></div>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-virus text-info me-2" style="color:#8B5CF6!important"></i> Diagnostic Laboratory Report</h6>
                                        <div class="text-end">
                                            <small class="fw-bold text-dark d-block"><?php echo date('M d, Y', $date); ?></small>
                                            <small class="text-muted"><?php echo date('h:i A', $date); ?> - 
                                            <?php if ($entry['status'] === 'completed') echo "by " . htmlspecialchars($entry['tech']['name'] ?? 'Tech'); else echo "Requested"; ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center mb-3">
                                        <div class="bg-light p-3 rounded-4 me-3 border font-monospace text-dark fw-bold">
                                            <?php echo htmlspecialchars($entry['test_name']); ?>
                                        </div>
                                        <?php if ($entry['status'] === 'completed'): ?>
                                            <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-all me-1"></i> Report Ready</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2"><i class="bi bi-cone-striped me-1"></i> Awaiting Lab Processing</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($entry['result_text']): ?>
                                        <div class="bg-dark text-white p-4 rounded-4 mt-3">
                                            <h6 class="text-info fw-bold mb-2 small text-uppercase letter-spacing-1">Pathologist Findings</h6>
                                            <p class="mb-0 font-monospace small" style="white-space: pre-wrap;"><?php echo htmlspecialchars($entry['result_text']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                        </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- VITALS MODAL (For Nurses) -->
    <div class="modal fade" id="vitalsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden glass">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-heart-pulse-fill text-info me-2"></i> Record Daily Vitals</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <form action="/api/consultation/save" method="POST">
                        <input type="hidden" name="patient_id" value="<?php echo $targetPatientId; ?>">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Temperature (°C)</label>
                                <input type="number" step="0.1" name="temperature" class="form-control rounded-4 bg-light border-0 py-2" placeholder="36.5">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Blood Pressure</label>
                                <input type="text" name="blood_pressure" class="form-control rounded-4 bg-light border-0 py-2" placeholder="120/80">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Weight (kg)</label>
                                <input type="number" step="0.1" name="weight" class="form-control rounded-4 bg-light border-0 py-2" placeholder="70.5">
                            </div>
                            <div class="col-6">
                                <label class="small fw-bold text-muted mb-1">Pulse (bpm)</label>
                                <input type="number" name="pulse" class="form-control rounded-4 bg-light border-0 py-2" placeholder="72">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-info w-100 rounded-pill mt-4 fw-bold py-3 shadow text-white">Save Vitals</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- CONSULTATION MODAL (For Doctors) -->
    <div class="modal fade" id="consultationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header border-0 pb-0 pt-4 px-4 bg-white">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-journal-medical text-success me-2"></i> Quick Clinical Setup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <p class="text-muted small mb-4">You are initiating a new clinical encounter in the EMR. Please use the Studio interface for long-form entry.</p>
                    <div class="d-grid gap-3">
                        <a href="/consultation.php?patient_id=<?php echo urlencode($targetPatientId); ?>" class="btn btn-primary btn-lg rounded-pill fw-bold shadow-sm py-3">
                            <i class="bi bi-display me-2"></i> Launch Telemedicine Studio
                        </a>
                        <button type="button" class="btn btn-light rounded-pill border fw-bold text-secondary py-3" data-bs-dismiss="modal">Cancel Encounter</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LAB REQUEST MODAL -->
    <div class="modal fade" id="labRequestModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-5 overflow-hidden">
                <div class="modal-header bg-white border-0 pb-0 pt-4 px-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-droplet-half text-info me-2"></i> Order Diagnostics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <form action="/api/lab/create" method="POST">
                        <input type="hidden" name="patient_id" value="<?php echo $targetPatientId; ?>">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Diagnostic Test Type</label>
                            <select name="test_type" class="form-select rounded-4 p-3 bg-light border-0 fw-bold" required>
                                <option value="Blood Test">Blood Test / Serology</option>
                                <option value="Blood Group test">Blood Type Cross-Match</option>
                                <option value="Urinalysis">Urinalysis</option>
                                <option value="Imaging">Imaging (X-Ray / MRI / CT)</option>
                                <option value="Pathology">Pathology / Biopsy</option>
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Clinical Description / Target</label>
                            <input type="text" name="test_name" class="form-control rounded-4 p-3 bg-light border-0" placeholder="e.g. Complete Blood Count (CBC)" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 rounded-pill fw-bold py-3 shadow">Issue Requisition</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
