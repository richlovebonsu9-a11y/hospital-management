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
    <title>EMERGENCY NOW - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-midnight: #020617;
            --bg-deep: #0F172A;
            --glow-cyan: #22D3EE;
            --glow-cyan-alt: #0891B2;
            --accent-violet: #8B5CF6;
            --slate-300: #CBD5E1;
            --slate-400: #94A3B8;
            --glass-dark: rgba(15, 23, 42, 0.85);
            --border-glow: rgba(34, 211, 238, 0.2);
        }
        body {
            background: radial-gradient(circle at 50% 50%, #172554 0%, #020617 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--slate-300);
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
        }

        /* Celestial Particles */
        .stars {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            z-index: 0;
            background-image: 
                radial-gradient(1px 1px at 20px 30px, #fff, rgba(0,0,0,0)),
                radial-gradient(1.5px 1.5px at 100px 150px, #fff, rgba(0,0,0,0)),
                radial-gradient(1px 1px at 200px 250px, rgba(255,255,255,0.5), rgba(0,0,0,0));
            background-size: 300px 300px;
            opacity: 0.15;
            pointer-events: none;
        }

        .nebula {
            position: absolute;
            width: 80vw;
            height: 80vw;
            background: radial-gradient(circle, rgba(139, 92, 246, 0.05) 0%, transparent 70%);
            top: -20%;
            left: -10%;
            z-index: 0;
            filter: blur(50px);
            animation: drift 30s infinite alternate ease-in-out;
            pointer-events: none;
        }

        @keyframes drift {
            from { transform: translate(0, 0) scale(1); }
            to { transform: translate(50px, 30px) scale(1.2); }
        }

        .emergency-header {
            position: relative;
            z-index: 10;
            padding: 100px 0 50px;
        }
        
        .emergency-header h1 {
            font-weight: 800;
            letter-spacing: 2px;
            color: #fff;
            text-transform: uppercase;
            text-shadow: 0 0 20px rgba(34, 211, 238, 0.4);
        }

        .hope-message {
            font-size: 1.1rem;
            color: var(--slate-400);
            font-weight: 400;
            max-width: 650px;
            margin: 0 auto;
            letter-spacing: 0.5px;
            line-height: 1.8;
        }

        .glass-card {
            background: var(--glass-dark);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--border-glow);
            border-radius: 2rem;
            box-shadow: 0 0 60px rgba(0, 0, 0, 0.5), inset 0 0 20px rgba(34, 211, 238, 0.05);
            position: relative;
            z-index: 10;
        }

        .form-label {
            color: #fff;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 2px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            opacity: 0.9;
        }
        .form-label i { color: var(--glow-cyan); margin-right: 10px; }

        .form-select, .form-control {
            background: rgba(15, 23, 42, 0.6) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
            color: #fff !important;
            border-radius: 1rem;
            padding: 1.25rem 1.75rem !important;
            transition: all 0.4s;
            font-size: 0.9rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--glow-cyan) !important;
            box-shadow: 0 0 20px rgba(34, 211, 238, 0.2) !important;
            background: rgba(15, 23, 42, 0.8) !important;
        }

        /* Tactical Severity Buttons */
        .severity-btn {
            background: rgba(30, 41, 59, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 1.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            min-width: 140px;
            padding: 2rem 1rem;
            position: relative;
            overflow: hidden;
        }
        
        .severity-btn i { font-size: 2.2rem; margin-bottom: 1rem; display: block; color: rgba(255,255,255,0.2); transition: all 0.4s; }
        .severity-btn .fw-bold { font-size: 0.7rem; letter-spacing: 2px; color: rgba(255,255,255,0.4); text-transform: uppercase; transition: all 0.4s; }

        .severity-btn:hover { background: rgba(30, 41, 59, 0.8); transform: translateY(-3px); border-color: rgba(255,255,255,0.15); }

        #sev_medium:checked + .severity-btn {
            border-color: var(--glow-cyan);
            background: rgba(34, 211, 238, 0.05);
            box-shadow: 0 0 25px rgba(34, 211, 238, 0.15);
        }
        #sev_medium:checked + .severity-btn i { color: var(--glow-cyan); text-shadow: 0 0 15px var(--glow-cyan); }
        #sev_medium:checked + .severity-btn .fw-bold { color: var(--glow-cyan); }

        #sev_high:checked + .severity-btn {
            border-color: var(--accent-violet);
            background: rgba(139, 92, 246, 0.05);
            box-shadow: 0 0 25px rgba(139, 92, 246, 0.15);
        }
        #sev_high:checked + .severity-btn i { color: var(--accent-violet); text-shadow: 0 0 15px var(--accent-violet); }
        #sev_high:checked + .severity-btn .fw-bold { color: var(--accent-violet); }

        #sev_critical:checked + .severity-btn {
            border-color: #F43F5E;
            background: rgba(244, 63, 94, 0.05);
            box-shadow: 0 0 30px rgba(244, 63, 94, 0.2);
            animation: pulse-danger 1.5s infinite;
        }
        #sev_critical:checked + .severity-btn i { color: #F43F5E; text-shadow: 0 0 15px #F43F5E; }
        #sev_critical:checked + .severity-btn .fw-bold { color: #F43F5E; }

        @keyframes pulse-danger {
            0% { box-shadow: 0 0 10px rgba(244, 63, 94, 0.2); }
            50% { box-shadow: 0 0 30px rgba(244, 63, 94, 0.4); }
            100% { box-shadow: 0 0 10px rgba(244, 63, 94, 0.2); }
        }

        .btn-celestial {
            background: linear-gradient(135deg, var(--glow-cyan) 0%, var(--glow-cyan-alt) 100%);
            border: none;
            color: #020617;
            box-shadow: 0 0 30px rgba(34, 211, 238, 0.3);
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            text-transform: uppercase;
            letter-spacing: 3px;
            font-weight: 900;
            padding: 1.5rem;
            border-radius: 1rem;
        }
        .btn-celestial:hover {
            transform: scale(1.05);
            box-shadow: 0 0 50px rgba(34, 211, 238, 0.5);
            color: #000;
        }

        .tactical-alert {
            background: rgba(34, 211, 238, 0.03);
            border: 1px dashed rgba(34, 211, 238, 0.3) !important;
            border-radius: 1.5rem;
        }

        .info-card-tactical {
            background: rgba(30, 41, 59, 0.4);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 1.5rem;
        }

        .neon-text { text-shadow: 0 0 10px var(--glow-cyan); }
    </style>
</head>
<body>
    <div class="stars"></div>
    <div class="nebula"></div>

    <div class="emergency-header text-center">
        <div class="container">
            <div class="animate-float">
                <i class="bi bi-shield-fill-plus display-1 mb-3" style="color: var(--glow-cyan); filter: drop-shadow(0 0 15px var(--glow-cyan));"></i>
            </div>
            <h1>GUARDIAN RESPONSE ACTIVE</h1>
            <p class="hope-message">Establishing a secure clinical connection. Our tactical medical team is monitoring this frequency and prepared for immediate deployment.</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="alert tactical-alert border-0 rounded-4 mb-4 shadow-sm p-4">
                    <div class="d-flex align-items-center">
                        <div class="p-3 rounded-circle me-3" style="background: rgba(34, 211, 238, 0.1); border: 1px solid var(--border-glow);">
                            <i class="bi bi-broadcast-pin fs-3 neon-text" style="color: var(--glow-cyan);"></i>
                        </div>
                        <div>
                            <strong class="d-block mb-1 text-uppercase letter-spacing-2" style="color: var(--glow-cyan);">Mission Critical Access</strong>
                            <p class="small mb-0 opacity-75">This encrypted channel is prioritizing life-saving interventions. Unauthorized use is monitored to ensure response readiness for genuine emergencies.</p>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-4 p-md-5 mt-4">
                    <form action="/api/emergency/report" method="POST">
                        <?php if ($role === 'guardian' && !empty($guardianLinks)): ?>
                        <div class="mb-5 info-card-tactical p-4 shadow-sm">
                            <label class="form-label mb-3"><i class="bi bi-person-bounding-box"></i> SUBJECT IDENTIFICATION</label>
                            <select name="patient_id" class="form-select border-0 shadow-sm" required>
                                <option value="<?php echo $userId; ?>">System Holder (Primary)</option>
                                <?php foreach ($guardianLinks as $link): ?>
                                    <option value="<?php echo $link['patient_id']; ?>" 
                                            data-gps="<?php echo htmlspecialchars($link['patient']['user_metadata']['ghana_post_gps'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($link['patient']['name']); ?> (<?php echo htmlspecialchars($link['relationship']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-info mt-3 d-block opacity-50"><i class="bi bi-database-fill-check me-1"></i> Linking to patient history for optimized clinical response.</small>
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
                            <textarea name="symptoms" id="symptomsTextarea" class="form-control p-4" rows="3" placeholder="Briefly describe what is happening... Stay calm, type clearly." required></textarea>
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
                            <div class="position-relative">
                                <input type="file" id="mediaUploadInput" class="form-control px-4 py-3 text-secondary" accept="image/*,video/*" style="background: rgba(255,255,255,0.05); border: 1px dashed rgba(255,255,255,0.2);">
                                <input type="hidden" name="media_base64" id="mediaBase64">
                            </div>
                            <small class="text-secondary mt-2 d-block"><i class="bi bi-camera me-1"></i> Capture the scene to help responders prepare.</small>
                        </div>

                        <input type="hidden" name="live_location" id="liveLocation">

                        <div class="mb-4">
                            <label class="form-label fw-bold text-uppercase">6. GhanaPostGPS Address</label>
                            <div class="position-relative">
                                <i class="bi bi-geo-alt-fill position-absolute top-50 start-0 translate-middle-y ms-3 text-danger"></i>
                                <input type="text" name="ghana_post_gps" class="form-control px-4 py-3 ps-5 fw-bold" required placeholder="AK-485-9323" value="<?php echo $_SESSION['user']['user_metadata']['ghana_post_gps'] ?? ''; ?>">
                            </div>
                            <small class="text-secondary mt-2 d-block"><i class="bi bi-info-circle me-1"></i> Mandatory for pinpoint rapid response accuracy.</small>
                        </div>

                        <div class="d-grid mt-5 pt-4 border-top border-white border-opacity-10">
                            <button type="submit" id="submitBtn" class="btn btn-celestial btn-lg">
                                <span class="normal-text"><i class="bi bi-lightning-charge-fill me-2"></i> INITIATE RESCUE PULSE</span>
                                <span class="loading-text d-none"><span class="spinner-border spinner-border-sm me-2"></span> ENCRYPTING SIGNAL...</span>
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

        // Location capturing
        const locInput = document.getElementById('liveLocation');
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => { locInput.value = pos.coords.latitude + ',' + pos.coords.longitude; },
                err => { console.warn('Location access denied or unavailable', err); },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        }

        // Photo/Video File Handling
        const mediaInput = document.getElementById('mediaUploadInput');
        const mediaBase64Input = document.getElementById('mediaBase64');
        mediaInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) { mediaBase64Input.value = ''; return; }
            if (file.size > 5 * 1024 * 1024) { // 5MB limit
                Swal.fire('File Too Large', 'Please keep photo/video under 5MB for rapid transmission.', 'warning');
                mediaInput.value = '';
                return;
            }
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onloadend = () => { mediaBase64Input.value = reader.result; };
        });

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
                symptomsTextarea.focus();
            } else {
                symptomsLabel.innerText = '3. Primary Symptoms / Details';
                symptomsTextarea.placeholder = "Briefly describe what is happening... Stay calm, type clearly.";
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
