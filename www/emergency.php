<?php
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
$user = $_SESSION['user'] ?? null;
$role = $user['user_metadata']['role'] ?? 'patient';
$userId = $user['id'] ?? null;

$guardianLinks = [];
if ($role === 'guardian' && $userId) {
    $sb = new Supabase();
    $linkedRes = $sb->request('GET', '/rest/v1/guardians?guardian_id=eq.' . $userId . '&status=eq.approved&select=*,patient:patient_id(*)', null, true);
    if ($linkedRes['status'] === 200) {
        $guardianLinks = $linkedRes['data'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMERGENCY NOW - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --rescue-orange: #F97316;
            --rescue-orange-dark: #C2410C;
            --rescue-orange-light: #FFF7ED;
            --slate-950: #020617;
            --slate-800: #1E293B;
            --slate-600: #475569;
            --warm-bg: #FAF9F6;
            --industrial-glass: rgba(255, 255, 255, 0.98);
        }
        body {
            background-color: var(--warm-bg);
            background-image: 
                linear-gradient(rgba(249, 115, 22, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(249, 115, 22, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--slate-800);
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
        }

        /* Industrial Geometric Accents */
        .corner-accent {
            position: fixed;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(249, 115, 22, 0.05) 0%, transparent 70%);
            z-index: 0;
            pointer-events: none;
        }
        .accent-tl { top: -100px; left: -100px; }
        .accent-br { bottom: -100px; right: -100px; }

        .emergency-header {
            position: relative;
            z-index: 10;
            padding: 90px 0 40px;
        }
        
        .emergency-header h1 {
            font-weight: 900;
            letter-spacing: -2px;
            color: var(--slate-950);
            text-transform: uppercase;
        }
        .emergency-header .rescue-line {
            width: 80px;
            height: 6px;
            background: var(--rescue-orange);
            margin: 15px auto;
            border-radius: 3px;
        }

        .hope-message {
            font-size: 1.15rem;
            color: var(--slate-600);
            font-weight: 600;
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
            letter-spacing: -0.2px;
        }

        .glass-card {
            background: var(--industrial-glass);
            border: 2px solid #fff;
            border-radius: 2rem;
            box-shadow: 0 30px 60px -12px rgba(15, 23, 42, 0.12), 0 18px 36px -18px rgba(15, 23, 42, 0.15);
            position: relative;
            z-index: 10;
        }

        .form-label {
            color: var(--slate-950);
            font-weight: 800;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            margin-bottom: 0.75rem;
            text-transform: uppercase;
        }

        .form-select, .form-control {
            background: #F8FAFC !important;
            border: 1px solid #E2E8F0 !important;
            color: var(--slate-950) !important;
            border-radius: 0.75rem;
            padding: 1.25rem 1.5rem !important;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--rescue-orange) !important;
            box-shadow: 0 0 0 5px rgba(249, 115, 22, 0.15) !important;
            background: #fff !important;
        }

        /* Active Severity Buttons */
        .severity-btn {
            background: #fff;
            border: 1px solid #E2E8F0;
            border-radius: 1.25rem;
            transition: all 0.3s;
            cursor: pointer;
            min-width: 140px;
            padding: 1.75rem 1rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03);
        }
        
        .severity-btn i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #CBD5E1; transition: 0.3s; }
        .severity-btn .fw-bold { font-size: 0.75rem; letter-spacing: 1px; color: #94A3B8; text-transform: uppercase; transition: 0.3s; }

        .severity-btn:hover { border-color: #CBD5E1; transform: translateY(-3px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }

        #sev_medium:checked + .severity-btn {
            border-color: #2563EB;
            background: #EFF6FF;
            border-width: 2px;
        }
        #sev_medium:checked + .severity-btn i, #sev_medium:checked + .severity-btn .fw-bold { color: #2563EB; }

        #sev_high:checked + .severity-btn {
            border-color: var(--rescue-orange);
            background: var(--rescue-orange-light);
            border-width: 2px;
        }
        #sev_high:checked + .severity-btn i, #sev_high:checked + .severity-btn .fw-bold { color: var(--rescue-orange); }

        #sev_critical:checked + .severity-btn {
            border-color: #F43F5E;
            background: #FFF1F2;
            border-width: 2px;
            animation: industrial-pulse 2s infinite;
        }
        #sev_critical:checked + .severity-btn i, #sev_critical:checked + .severity-btn .fw-bold { color: #F43F5E; }

        @keyframes industrial-pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }

        .btn-rescue {
            background: var(--rescue-orange);
            border: none;
            color: white;
            box-shadow: 0 10px 25px rgba(249, 115, 22, 0.3);
            transition: all 0.4s;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 800;
            padding: 1.5rem;
            border-radius: 1rem;
        }
        .btn-rescue:hover {
            background: var(--rescue-orange-dark);
            transform: translateY(-4px);
            box-shadow: 0 15px 35px rgba(249, 115, 22, 0.45);
            color: white;
        }

        .mission-alert {
            background: var(--slate-950);
            color: #fff;
            border-radius: 1.25rem;
            border: none !important;
            padding: 1.5rem;
        }

        .info-card-industrial {
            background: #F1F5F9;
            border: 1px solid #E2E8F0;
            border-radius: 1.25rem;
        }

        .orange-text { color: var(--rescue-orange); }

        /* Location Status Styles */
        .location-indicator {
            font-size: 0.8rem;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
        }
        .status-searching { background: rgba(255,255,255,0.1); color: #94A3B8; }
        .status-secured { background: rgba(34,197,94,0.1); color: #22C55E; border: 1px solid rgba(34,197,94,0.2); }
        .status-denied { background: rgba(239,68,68,0.1); color: #EF4444; border: 1px solid rgba(239,68,68,0.2); }
        
        .pulse-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .pulse-searching { background: #94A3B8; animation: pulse-gray 1.5s infinite; }
        .pulse-secured { background: #22C55E; animation: pulse-green 1.5s infinite; }
        
        @keyframes pulse-gray {
            0% { box-shadow: 0 0 0 0 rgba(148, 163, 184, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(148, 163, 184, 0); }
            100% { box-shadow: 0 0 0 0 rgba(148, 163, 184, 0); }
        }
        @keyframes pulse-green {
            0% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        /* Camera UI Elements */
        #cameraModal .modal-content {
            background: #020617;
            border-radius: 2rem;
            border: 2px solid rgba(255,255,255,0.1);
            color: white;
            overflow: hidden;
        }
        #cameraStream {
            width: 100%;
            height: auto;
            max-height: 70vh;
            border-radius: 1.5rem;
            background: #000;
            object-fit: cover;
        }
        #cameraCanvas {
            display: none;
        }
        .camera-btn {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            border: 5px solid rgba(255,255,255,0.3);
            background: #EF4444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            transition: all 0.2s;
            margin: 0 auto;
        }
        .camera-btn:hover {
            transform: scale(0.95);
            background: #DC2626;
        }
        .camera-btn:active {
            transform: scale(0.9);
        }
        .camera-controls {
            padding: 1.5rem;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
        }
        #snapshotPreview {
            width: 100%;
            border-radius: 1.5rem;
            display: none;
        }
    </style>
</head>
<body>
    <div class="corner-accent accent-tl"></div>
    <div class="corner-accent accent-br"></div>

    <div class="emergency-header text-center">
        <div class="container">
            <div class="animate-float">
                <i class="bi bi-broadcast display-1 mb-3" style="color: var(--rescue-orange);"></i>
            </div>
            <h1>RESCUE MISSION INITIALIZED</h1>
            <div class="rescue-line"></div>
            <p class="hope-message">Help is on the way. Our specialized rapid-response units are mobilizing to your precise coordinates for immediate clinical support.</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="alert mission-alert border-0 shadow-lg p-4 mb-4">
                    <div class="d-flex align-items-center">
                        <div class="p-3 rounded-3 me-3" style="background: rgba(249, 115, 22, 0.15); border: 2px solid var(--rescue-orange);">
                            <i class="bi bi-shield-fill-exclamation fs-3" style="color: var(--rescue-orange);"></i>
                        </div>
                        <div>
                            <strong class="d-block mb-1 text-uppercase letter-spacing-2">ACTIVE MONITORING SESSION</strong>
                            <p class="small mb-0 opacity-80">This channel is being prioritized for life-saving dispatch. Please provide accurate details to ensure our team arrives with the correct medical assets.</p>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-4 p-md-5 mt-4">
                    <form action="/api/emergency/report" method="POST">
                        <?php if ($role === 'guardian' && !empty($guardianLinks)): ?>
                        <div class="mb-5 info-card-industrial p-4 shadow-sm">
                            <label class="form-label mb-3"><i class="bi bi-person-check-fill orange-text me-2"></i> TARGET PATIENT IDENTIFICATION</label>
                            <select name="patient_id" class="form-select border-0 shadow-sm" required>
                                <option value="<?php echo $userId; ?>">User (Primary Subject)</option>
                                <?php foreach ($guardianLinks as $link): ?>
                                    <option value="<?php echo $link['patient_id']; ?>" 
                                            data-gps="<?php echo htmlspecialchars($link['patient']['user_metadata']['ghana_post_gps'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($link['patient']['name']); ?> (<?php echo htmlspecialchars($link['relationship']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-secondary mt-3 d-block"><i class="bi bi-link-45deg me-1"></i> Data integration active: Fetching specialized clinical history.</small>
                        </div>
                        <?php endif; ?>
                        <div class="mb-5 text-center">
                            <label class="form-label fw-bold mb-4 text-uppercase">1. Assess Severity</label>
                            <div class="d-flex justify-content-center gap-3 flex-wrap">
                                <label class="severity-option">
                                    <input type="radio" name="severity" value="medium" id="sev_medium" class="d-none" required checked>
                                    <div class="severity-btn p-3 text-center">
                                        <i class="bi bi-heart-pulse"></i>
                                        <div class="fw-bold text-uppercase">Medium</div>
                                    </div>
                                </label>
                                <label class="severity-option">
                                    <input type="radio" name="severity" value="high" id="sev_high" class="d-none">
                                    <div class="severity-btn p-3 text-center">
                                        <i class="bi bi-activity"></i>
                                        <div class="fw-bold text-uppercase">High</div>
                                    </div>
                                </label>
                                <label class="severity-option">
                                    <input type="radio" name="severity" value="critical" id="sev_critical" class="d-none">
                                    <div class="severity-btn p-3 text-center">
                                        <i class="bi bi-shield-fill-exclamation"></i>
                                        <div class="fw-bold text-uppercase">Critical</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase">2. Nature of Emergency</label>
                            <select name="emergency_type" class="form-select px-4 py-3 fw-bold" required>
                                <option value="">-- Choose Situation --</option>
                                <optgroup label="Ambulance Dispatch Required">
                                    <option value="car_and_motor_accident">Car and Motor Accident</option>
                                    <option value="labour">Labour / Maternity</option>
                                    <option value="sudden_consciousness_loss">Sudden Consciousness Loss</option>
                                    <option value="breathing_difficulty">Breathing Difficulty</option>
                                </optgroup>
                                <optgroup label="Dispatch Rider Specialist Needed">
                                    <option value="cardiac_emergencies">Cardiac Emergency</option>
                                    <option value="diabetic_emergencies">Diabetic Emergency</option>
                                    <option value="asthmatic_attacks">Asthmatic Attack</option>
                                    <option value="snake_bite">Snake Bite</option>
                                    <option value="dog_bite">Dog Bite</option>
                                    <option value="scorpion_bite">Scorpion Bite</option>
                                </optgroup>
                                <option value="other">Other / Describe below</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label id="symptomsLabel" class="form-label fw-bold text-uppercase">3. Primary Symptoms / Details</label>
                            <textarea name="symptoms" id="symptomsTextarea" class="form-control p-4" rows="3" placeholder="Briefly describe what is happening... Stay calm, type clearly."></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase d-flex justify-content-between align-items-center">
                                4. Voice Note (Optional)
                                <span id="recordingIndicator" class="badge bg-danger rounded-pill px-2 d-none animate-pulse" style="font-size: 0.65rem;">REC</span>
                            </label>
                            <div class="d-flex align-items-center gap-3 p-3 glass-card bg-opacity-10 border border-dashed border-secondary rounded-4 shadow-sm">
                                <button type="button" id="recordBtn" class="btn btn-outline-danger rounded-circle p-0 d-flex align-items-center justify-content-center shadow-sm transition-all" style="width: 52px; height: 52px; min-width: 52px;">
                                    <i class="bi bi-mic-fill fs-4"></i>
                                </button>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div id="recordStatus" class="text-secondary extra-small fw-semibold mb-1">
                                        Click to capture audio context
                                    </div>
                                    <div id="playbackContainer" class="d-none">
                                        <audio id="audioPlayback" controls style="height: 32px; width: 100%; max-width: 200px;"></audio>
                                    </div>
                                </div>
                                <input type="hidden" name="voice_note_base64" id="voiceNoteBase64">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase">5. Photo / Video Evidence (Optional)</label>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" id="startCameraBtn" class="btn btn-outline-danger flex-grow-1 py-3 rounded-4 shadow-sm fw-bold">
                                    <i class="bi bi-camera-fill me-2"></i> TAKE LIVE PHOTO
                                </button>
                                <div class="position-relative flex-grow-1">
                                    <input type="file" id="mediaUploadInput" class="form-control h-100 py-3 rounded-4" accept="image/*,video/*" style="opacity: 0; position: absolute; cursor: pointer; z-index: 2;">
                                    <button type="button" class="btn btn-outline-secondary w-100 h-100 py-3 rounded-4 fw-bold">
                                        <i class="bi bi-upload me-2"></i> UPLOAD FILE
                                    </button>
                                </div>
                            </div>
                            <div id="mediaSummary" class="text-secondary small d-none p-2 bg-white rounded-3 border border-dashed">
                                <span id="mediaSummaryText">No file attached</span>
                                <button type="button" id="clearMediaBtn" class="btn btn-link btn-sm text-danger p-0 ms-2 text-decoration-none fw-bold">REMOVE</button>
                            </div>
                            <input type="hidden" name="media_base64" id="mediaBase64">
                            <small class="text-secondary mt-2 d-block"><i class="bi bi-info-circle me-1"></i> Use your camera to capture the scene and help responders prepare.</small>
                        </div>

                        <div class="mb-4 info-card-industrial p-4 shadow-sm border-0" style="background: rgba(255,255,255,0.03);">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label id="gpsLabel" class="form-label mb-0 fw-bold text-uppercase">6. Location Address</label>
                                <div id="locationStatus" class="location-indicator status-searching">
                                    <div class="pulse-dot pulse-searching"></div>
                                    <span id="locationStatusText">SEARCHING GPS...</span>
                                </div>
                            </div>
                            <div class="position-relative">
                                <i class="bi bi-geo-alt-fill position-absolute top-50 start-0 translate-middle-y ms-3 text-danger" style="z-index: 5;"></i>
                                <input type="text" name="ghana_post_gps" id="gpsInput" class="form-control px-4 py-3 ps-5 fw-bold" required placeholder="AK-485-9323" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: white;" value="<?php echo $_SESSION['user']['user_metadata']['ghana_post_gps'] ?? ''; ?>">
                            </div>
                            <input type="hidden" name="live_location" id="liveLocation">
                            <small class="text-secondary mt-2 d-block"><i class="bi bi-info-circle me-1"></i> Don't know your GPS? Our system is auto-detecting your live coordinates.</small>
                        </div>

                        <div class="d-grid mt-5 pt-4 border-top border-light">
                            <button type="submit" id="submitBtn" class="btn btn-rescue btn-lg">
                                <span class="normal-text"><i class="bi bi-send-fill me-2"></i> REPORT EMERGENCY</span>
                                <span class="loading-text d-none"><span class="spinner-border spinner-border-sm me-2"></span> MOBILIZING UNIT...</span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="text-center mt-5 position-relative z-index-10">
                    <a href="/dashboard" class="text-secondary text-decoration-none hover-white transition-all"><i class="bi bi-arrow-left me-1"></i> Back to Safety</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Camera Modal -->
    <div class="modal fade" id="cameraModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-2xl">
                <div class="modal-body p-0">
                    <div id="cameraContainer" class="position-relative bg-black" style="min-height: 400px;">
                        <video id="cameraStream" autoplay playsinline muted></video>
                        <canvas id="cameraCanvas"></canvas>
                        <img id="snapshotPreview" alt="Snapshot Preview">
                        
                        <div class="camera-controls position-absolute bottom-0 start-0 end-0">
                            <div id="captureState">
                                <button type="button" id="shutterBtn" class="camera-btn shadow-lg">
                                    <i class="bi bi-camera-fill"></i>
                                </button>
                                <div class="text-center mt-3 text-white-50 small text-uppercase letter-spacing-2">TAP TO CAPTURE</div>
                            </div>
                            
                            <div id="previewState" class="d-none">
                                <div class="d-flex justify-content-center gap-3">
                                    <button type="button" id="retakeBtn" class="btn btn-outline-light rounded-pill px-4 py-2">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i> RETAKE
                                    </button>
                                    <button type="button" id="usePhotoBtn" class="btn btn-success rounded-pill px-5 py-2 fw-bold shadow-lg">
                                        <i class="bi bi-check2-circle me-1"></i> USE PHOTO
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="p-3 text-center bg-dark">
                    <button type="button" id="closeCameraBtn" class="btn btn-link text-white-50 text-decoration-none small text-uppercase letter-spacing-1" data-bs-dismiss="modal">CANCEL & CLOSE</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Media Recorder Logic
        let mediaRecorder;
        let audioChunks = [];
        const recordBtn = document.getElementById('recordBtn');
        const recordStatus = document.getElementById('recordStatus');
        const playbackContainer = document.getElementById('playbackContainer');
        const audioPlayback = document.getElementById('audioPlayback');
        const voiceInput = document.getElementById('voiceNoteBase64');
        const recordingIndicator = document.getElementById('recordingIndicator');

        // Continuous Live Location Tracking
        const locInput = document.getElementById('liveLocation');
        const gpsInput = document.getElementById('gpsInput');
        const gpsLabel = document.getElementById('gpsLabel');
        const locStatus = document.getElementById('locationStatus');
        const locStatusText = document.getElementById('locationStatusText');
        const pulseDot = locStatus.querySelector('.pulse-dot');
        let watchId = null;

        function updateLocationStatus(state, msg) {
            locStatus.className = 'location-indicator ' + state;
            locStatusText.innerText = msg;
            pulseDot.className = 'pulse-dot ' + (state === 'status-secured' ? 'pulse-secured' : (state === 'status-searching' ? 'pulse-searching' : ''));
            
            if (state === 'status-secured') {
                gpsInput.required = false;
                gpsInput.placeholder = "Optional (Live Location Active)";
                gpsLabel.innerHTML = '<i class="bi bi-geo-alt-fill text-success me-2"></i> 4. EMERGENCY LOCATION <span class="badge bg-success ms-2">SECURED</span>';
            } else if (state === 'status-denied') {
                gpsInput.required = true;
                gpsInput.placeholder = "REQUIRED (AK-485-9323)";
                gpsLabel.innerHTML = '<i class="bi bi-geo-alt-fill text-danger me-2"></i> 4. EMERGENCY LOCATION <span class="badge bg-danger ms-2">MANDATORY</span>';
            }
        }

        if (navigator.geolocation) {
            watchId = navigator.geolocation.watchPosition(
                pos => {
                    const coords = pos.coords.latitude + ',' + pos.coords.longitude;
                    locInput.value = coords;
                    updateLocationStatus('status-secured', 'POSITION SECURED');
                    console.log('Location Updated:', coords);
                },
                err => {
                    console.warn('Location tracking failed', err);
                    if (err.code === 1) { // Permission Denied
                        updateLocationStatus('status-denied', 'GPS ACCESS DENIED');
                    } else {
                        updateLocationStatus('status-searching', 'GPS SEARCHING...');
                    }
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            updateLocationStatus('status-denied', 'GPS NOT SUPPORTED');
        }

        // Photo/Video File Handling
        const mediaInput = document.getElementById('mediaUploadInput');
        const mediaBase64Input = document.getElementById('mediaBase64');
        const mediaSummary = document.getElementById('mediaSummary');
        const mediaSummaryText = document.getElementById('mediaSummaryText');
        const clearMediaBtn = document.getElementById('clearMediaBtn');

        function updateMediaPreview(source, filename = "Scene Capture Attached") {
            mediaBase64Input.value = source;
            mediaSummaryText.innerText = filename;
            mediaSummary.classList.remove('d-none');
        }

        mediaInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) { return; }
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                Swal.fire('File Too Large', 'Please keep photo/video under 5MB for rapid transmission.', 'warning');
                mediaInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onloadend = () => { 
                updateMediaPreview(reader.result, file.name);
            };
        });

        clearMediaBtn.onclick = () => {
            mediaBase64Input.value = '';
            mediaInput.value = '';
            mediaSummary.classList.add('d-none');
        };

        // Live Camera Logic
        const cameraModal = new bootstrap.Modal(document.getElementById('cameraModal'));
        const video = document.getElementById('cameraStream');
        const canvas = document.getElementById('cameraCanvas');
        const imgPreview = document.getElementById('snapshotPreview');
        const shutterBtn = document.getElementById('shutterBtn');
        const retakeBtn = document.getElementById('retakeBtn');
        const usePhotoBtn = document.getElementById('usePhotoBtn');
        const captureState = document.getElementById('captureState');
        const previewState = document.getElementById('previewState');
        let currentStream = null;

        document.getElementById('startCameraBtn').onclick = async () => {
            captureState.classList.remove('d-none');
            previewState.classList.add('d-none');
            video.style.display = 'block';
            imgPreview.style.display = 'none';
            
            try {
                currentStream = await navigator.mediaDevices.getUserMedia({ 
                    video: { facingMode: 'environment' }, 
                    audio: false 
                });
                video.srcObject = currentStream;
                cameraModal.show();
            } catch (err) {
                console.error("Camera access failed", err);
                Swal.fire({
                    title: 'Camera Access Denied',
                    text: 'We need camera access to capture emergency evidence. Please check your browser permissions.',
                    icon: 'error',
                    background: '#1E293B',
                    color: '#fff'
                });
            }
        };

        shutterBtn.onclick = () => {
            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
            imgPreview.src = dataUrl;
            
            video.style.display = 'none';
            imgPreview.style.display = 'block';
            captureState.classList.add('d-none');
            previewState.classList.remove('d-none');
        };

        retakeBtn.onclick = () => {
            video.style.display = 'block';
            imgPreview.style.display = 'none';
            captureState.classList.remove('d-none');
            previewState.classList.add('d-none');
        };

        usePhotoBtn.onclick = () => {
            updateMediaPreview(imgPreview.src, "Live Camera Snapshot");
            stopCamera();
            cameraModal.hide();
        };

        function stopCamera() {
            if (currentStream) {
                currentStream.getTracks().forEach(track => track.stop());
                currentStream = null;
            }
        }

        document.getElementById('cameraModal').addEventListener('hidden.bs.modal', stopCamera);
        document.getElementById('closeCameraBtn').onclick = stopCamera;

        recordBtn.onclick = async () => {
            if (mediaRecorder && mediaRecorder.state === "recording") {
                mediaRecorder.stop();
                return;
            }

            try {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                mediaRecorder = new MediaRecorder(stream);
                audioChunks = [];

                mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) audioChunks.push(e.data); };
                mediaRecorder.onstart = () => {
                    recordBtn.classList.replace('btn-outline-danger', 'btn-danger');
                    recordBtn.classList.add('pulse-highlight');
                    recordBtn.innerHTML = '<i class="bi bi-stop-fill fs-4"></i>';
                    recordStatus.innerText = "Recording... Stop when done.";
                    recordingIndicator.classList.remove('d-none');
                };
                mediaRecorder.onstop = async () => {
                    recordBtn.classList.replace('btn-danger', 'btn-outline-danger');
                    recordBtn.classList.remove('pulse-highlight');
                    recordBtn.innerHTML = '<i class="bi bi-mic-fill fs-4"></i>';
                    recordingIndicator.classList.add('d-none');
                    
                    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    const reader = new FileReader();
                    reader.readAsDataURL(audioBlob);
                    reader.onloadend = () => {
                        const base64data = reader.result;
                        voiceInput.value = base64data;
                        audioPlayback.src = base64data;
                        playbackContainer.classList.remove('d-none');
                        recordStatus.innerText = "Voice note attached successfully.";
                    };
                    
                    stream.getTracks().forEach(track => track.stop());
                };

                mediaRecorder.start();
            } catch (err) {
                console.error("Mic Error:", err);
                recordStatus.innerText = "Permissions required.";
                Swal.fire({ title: 'Microphone Needed', text: 'Help us hear you. Please enable mic access.', icon: 'info', background: '#1E293B', color: '#fff' });
            }
        };

        // Other Type Handler
        const typeSelect = document.querySelector('select[name="emergency_type"]');
        const patientSelect = document.querySelector('select[name="patient_id"]');
        const gpsInput = document.querySelector('input[name="ghana_post_gps"]');
        const symptomsLabel = document.getElementById('symptomsLabel');
        const symptomsTextarea = document.getElementById('symptomsTextarea');

        if (patientSelect) {
            patientSelect.onchange = function() {
                const selectedOption = this.options[this.selectedIndex];
                const gps = selectedOption.getAttribute('data-gps');
                if (gps) {
                    gpsInput.value = gps;
                    // Visual feedback
                    gpsInput.classList.add('border-info');
                    setTimeout(() => gpsInput.classList.remove('border-info'), 1000);
                }
            };
        }

        typeSelect.onchange = function() {
            if (this.value === 'other') {
                symptomsLabel.innerHTML = '3. Describe Emergency <span class="badge bg-danger ms-2">REQUIRED</span>';
                symptomsTextarea.placeholder = "REQUIRED: Please describe the situation in detail. This request will be reviewed by our clinical administrator immediately.";
                symptomsTextarea.required = true;
                symptomsTextarea.focus();
            } else {
                symptomsLabel.innerText = '3. Primary Symptoms / Details';
                symptomsTextarea.placeholder = "Briefly describe what is happening... Stay calm, type clearly. (Optional for this type)";
                symptomsTextarea.required = false;
            }
        };

        document.querySelector('form').onsubmit = async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.querySelector('.normal-text').classList.add('d-none');
            btn.querySelector('.loading-text').classList.remove('d-none');

            try {
                const formData = new FormData(this);
                const res = await fetch('/api/emergency/report', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'Response Team Triggered!',
                        html: '<p class="lead text-white-50">Please remain calm.</p><p>Specialized help has been dispatched. You are now being redirected to the <b>Live Emergency Tracker</b> for first-aid guidance and responder location.</p>',
                        icon: 'success',
                        background: '#1E293B',
                        color: '#fff',
                        iconColor: '#10B981',
                        confirmButtonColor: '#10B981',
                        confirmButtonText: 'Open Live Tracker',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = '/emergency_tracker?id=' + (data.emergency_id || '');
                    });
                } else {
                    Swal.fire({
                        title: 'Dispatch Issue',
                        text: data.error || 'There was an issue transmitting the emergency pulse. Please try again immediately.',
                        icon: 'error',
                        background: '#1E293B',
                        color: '#fff',
                        confirmButtonColor: '#EF4444'
                    });
                    btn.disabled = false;
                    btn.querySelector('.normal-text').classList.remove('d-none');
                    btn.querySelector('.loading-text').classList.add('d-none');
                }
            } catch (err) {
                Swal.fire({
                    title: 'Connection Dropped',
                    text: 'We could not reach the server. Please check your signal and hit dispatch again.',
                    icon: 'warning',
                    background: '#1E293B',
                    color: '#fff',
                    confirmButtonColor: '#EF4444'
                });
                btn.disabled = false;
                btn.querySelector('.normal-text').classList.remove('d-none');
                btn.querySelector('.loading-text').classList.add('d-none');
            }
        };
    </script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
