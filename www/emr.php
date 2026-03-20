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
$targetPatientId = $_GET['patient_id'] ?? $currentUser['id'];

// Fetch Patient Profile
$profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . urlencode($targetPatientId) . '&select=*');
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
            <a href="/dashboard_admin" class="btn btn-primary rounded-pill px-5">Back to Dashboard</a>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Fetch Clinical Data (Vitals, Lab Results, Prescriptions)
$vitalsRes = $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . urlencode($targetPatientId) . '&order=recorded_at.desc');
$vitals = ($vitalsRes['status'] === 200) ? $vitalsRes['data'] : [];

$labsRes = $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . urlencode($targetPatientId) . '&select=*,recorded_at:created_at&order=created_at.desc');
$labs = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

// Combine into a timeline
$timeline = array_merge($vitals, $labs);
usort($timeline, function($a, $b) {
    return strtotime($b['recorded_at'] ?? $b['created_at']) - strtotime($a['recorded_at'] ?? $a['created_at']);
});
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
                            $date = strtotime($entry['recorded_at'] ?? $entry['created_at']);
                        ?>
                            <div class="d-flex mb-5">
                                <div class="text-center me-4" style="min-width: 80px;">
                                    <h4 class="fw-bold mb-0"><?php echo date('d', $date); ?></h4>
                                    <small class="text-muted text-uppercase"><?php echo date('M \'y', $date); ?></small>
                                </div>
                                <div class="flex-grow-1 border-start ps-4">
                                    <?php if (isset($entry['temperature'])): // It's a Vitals entry ?>
                                        <h6 class="fw-bold mb-1 text-primary">Vitals Recorded</h6>
                                        <div class="row g-2 mt-2">
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 bg-light rounded-3 small">
                                                    <span class="d-block text-muted">Temp</span>
                                                    <span class="fw-bold"><?php echo $entry['temperature']; ?>°C</span>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 bg-light rounded-3 small">
                                                    <span class="d-block text-muted">BP</span>
                                                    <span class="fw-bold"><?php echo $entry['blood_pressure']; ?></span>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 bg-light rounded-3 small">
                                                    <span class="d-block text-muted">Pulse</span>
                                                    <span class="fw-bold"><?php echo $entry['pulse']; ?> bpm</span>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="p-2 bg-light rounded-3 small">
                                                    <span class="d-block text-muted">Weight</span>
                                                    <span class="fw-bold"><?php echo $entry['weight']; ?> kg</span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: // It's a Lab Request ?>
                                        <h6 class="fw-bold mb-1 text-info"><?php echo htmlspecialchars($entry['test_name']); ?> (<?php echo htmlspecialchars($entry['test_type']); ?>)</h6>
                                        <p class="text-muted small mb-2">Status: <span class="text-capitalize"><?php echo htmlspecialchars($entry['status']); ?></span></p>
                                        <?php if ($entry['result_text']): ?>
                                            <div class="card p-3 bg-light border-0 rounded-4">
                                                <h6 class="fw-bold small mb-2 text-danger">Result Notes</h6>
                                                <p class="small mb-0"><?php echo nl2br(htmlspecialchars($entry['result_text'])); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
