<?php
// Emergency Tracking - GGHMS
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$emergency_id = $_GET['id'] ?? '';
$status = 'pending'; // Default if not found

if ($emergency_id) {
    $supabase = new Supabase();
    // Use direct request with filter as the fluent 'where' isn't implemented in the helper
    $res = $supabase->request('GET', "/rest/v1/emergencies?id=eq.{$emergency_id}&select=*");
    $emergency = $res['data'][0] ?? null;
    if ($emergency) {
        $status = $emergency['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Emergency Status - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .tracking-dot { width: 12px; height: 12px; border-radius: 50%; display: inline-block; margin-right: 10px; }
        .dot-pulse { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5 mt-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card border-0 shadow-lg rounded-5 overflow-hidden">
                    <div class="card-header bg-danger text-white p-4 text-center border-0">
                        <h4 class="fw-bold mb-0">Emergency Tracking</h4>
                        <small>Ref ID: <?php echo htmlspecialchars($emergency_id); ?></small>
                    </div>
                    <div class="card-body p-5">
                        <div class="status-tracker mb-5">
                            <!-- Step 1: Pending -->
                            <div class="d-flex align-items-center mb-4 <?php echo ($status === 'pending' || $status === 'active') ? 'text-danger fw-bold' : 'text-success'; ?>">
                                <div class="bg-<?php echo ($status === 'pending' || $status === 'active') ? 'danger' : 'success'; ?> rounded-circle p-2 me-3 <?php echo ($status === 'pending' || $status === 'active') ? 'dot-pulse' : ''; ?>">
                                    <i class="bi bi-<?php echo ($status === 'pending' || $status === 'active') ? 'exclamation-circle' : 'check-lg'; ?> text-white"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Report Received</h6>
                                    <small><?php echo ($status === 'pending' || $status === 'active') ? 'Help is being coordinated.' : 'Report verified and logged.'; ?></small>
                                </div>
                            </div>

                            <!-- Step 2: Dispatched / Assigned -->
                            <?php $isDispatched = in_array($status, ['assigned', 'dispatched']); ?>
                            <div class="d-flex align-items-center mb-4 <?php echo $isDispatched ? 'text-primary fw-bold' : (in_array($status, ['on_site', 'resolved']) ? 'text-success' : 'text-muted opacity-50'); ?>">
                                <div class="bg-<?php echo $isDispatched ? 'primary' : (in_array($status, ['on_site', 'resolved']) ? 'success' : 'secondary'); ?> rounded-circle p-2 me-3 <?php echo $isDispatched ? 'dot-pulse' : ''; ?>">
                                    <i class="bi bi-truck text-white"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Dispatch in Progress</h6>
                                    <small><?php echo $isDispatched ? 'Assigned Responder is en route.' : 'Responder has been assigned.'; ?></small>
                                </div>
                            </div>

                            <!-- Step 3: On Scene -->
                            <div class="d-flex align-items-center mb-4 <?php echo $status === 'on_site' ? 'text-warning fw-bold' : ($status === 'resolved' ? 'text-success' : 'text-muted opacity-50'); ?>">
                                <div class="bg-<?php echo $status === 'on_site' ? 'warning' : ($status === 'resolved' ? 'success' : 'secondary'); ?> rounded-circle p-2 me-3 <?php echo $status === 'on_site' ? 'dot-pulse' : ''; ?>">
                                    <i class="bi bi-geo-alt-fill text-white"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">On Scene</h6>
                                    <small><?php echo $status === 'on_site' ? 'Responder has arrived at your location.' : 'Help is arriving soon.'; ?></small>
                                </div>
                            </div>

                            <!-- Step 4: Resolved -->
                            <div class="d-flex align-items-center mb-0 <?php echo $status === 'resolved' ? 'text-success fw-bold' : 'text-muted opacity-50'; ?>">
                                <div class="bg-<?php echo $status === 'resolved' ? 'success' : 'secondary'; ?> rounded-circle p-2 me-3">
                                    <i class="bi bi-heart-fill text-white"></i>
                                </div>
                                <div>
                                    <h6 class="fw-bold mb-0">Emergency Resolved</h6>
                                    <small><?php echo $status === 'resolved' ? 'Situation cleared. Stay safe.' : 'Service completion.'; ?></small>
                                </div>
                            </div>
                        </div>

                        <div class="card bg-primary-soft border-0 p-4 rounded-4 mb-4">
                            <h6 class="fw-bold text-primary mb-3">Live Location Tracking</h6>
                            <p class="mb-1 fw-bold">GhanaPostGPS: <?php echo htmlspecialchars($_SESSION['user']['user_metadata']['ghana_post_gps'] ?? 'PENDING'); ?></p>
                            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
                            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
                            <div id="map" class="rounded-3 shadow-sm bg-white overflow-hidden mt-3" style="height: 300px;"></div>
                            <script>
                                var map = L.map('map').setView([5.6037, -0.1870], 13); // Default to Accra
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '© OpenStreetMap'
                                }).addTo(map);
                                L.marker([5.6037, -0.1870]).addTo(map)
                                    .bindPopup('Emergency Location Received.')
                                    .openPopup();
                            </script>
                        </div>

                        <div class="alert bg-warning-soft text-warning border-0 p-3 rounded-4">
                            <i class="bi bi-info-circle-fill me-2"></i> STAY CALM. If the patient is not breathing, ensure their airway is clear and wait for the dispatcher to guide you.
                        </div>
                    </div>
                    <div class="card-footer bg-white border-0 text-center p-4">
                         <button class="btn btn-primary rounded-pill px-5 py-3 fw-bold">Call Emergency Line</button>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="/dashboard" class="text-muted text-decoration-none small">&larr; Return to Dashboard</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
