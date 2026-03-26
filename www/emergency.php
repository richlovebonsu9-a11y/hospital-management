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
            --vital-green: #059669;
            --vital-green-dark: #065F46;
            --vital-green-light: #ECFDF5;
            --sky-blue: #0284C7;
            --sky-blue-light: #F0F9FF;
            --slate-700: #334155;
            --slate-600: #475569;
            --glass-bg: rgba(255, 255, 255, 0.85);
        }
        body {
            background: linear-gradient(135deg, #F0F9FF 0%, #E0F2F1 50%, #ECFDF5 100%);
            background-attachment: fixed;
            min-height: 100vh;
            color: var(--slate-700);
            font-family: 'Montserrat', sans-serif;
            overflow-x: hidden;
        }

        /* Breathing Background Blobs */
        .blob {
            position: absolute;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.4;
            border-radius: 50%;
            animation: move 20s infinite alternate ease-in-out;
            pointer-events: none;
        }
        .blob-1 { width: 500px; height: 500px; background: #BAE6FD; top: -100px; left: -100px; }
        .blob-2 { width: 600px; height: 600px; background: #A7F3D0; bottom: -200px; right: -100px; animation-delay: -5s; }

        @keyframes move {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(100px, 50px) scale(1.1); }
        }

        .emergency-header {
            position: relative;
            z-index: 10;
            padding: 90px 0 40px;
        }
        
        .emergency-header h1 {
            font-weight: 800;
            letter-spacing: -1px;
            color: var(--vital-green-dark);
            text-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
        }

        .hope-message {
            font-size: 1.2rem;
            color: var(--slate-600);
            font-weight: 500;
            max-width: 650px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 3rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.08);
            position: relative;
            z-index: 10;
            transition: transform 0.3s ease;
        }

        .form-label {
            color: var(--slate-700);
            font-weight: 700;
            font-size: 0.8rem;
            letter-spacing: 1px;
            margin-bottom: 0.75rem;
        }

        .form-select, .form-control {
            background: #fff !important;
            border: 1px solid #E2E8F0 !important;
            color: #1E293B !important;
            border-radius: 1.5rem;
            padding: 1.1rem 1.5rem !important;
            transition: all 0.3s;
            font-size: 0.95rem;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--sky-blue) !important;
            box-shadow: 0 0 0 5px rgba(2, 132, 199, 0.1) !important;
        }

        /* Severity Buttons Redesign - Vital Serenity Style */
        .severity-btn {
            background: #fff;
            border: 2px solid #F1F5F9;
            border-radius: 2rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            min-width: 150px;
            padding: 1.5rem 1rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        
        .severity-btn i { font-size: 2.5rem; margin-bottom: 0.75rem; display: block; color: #CBD5E1; transition: all 0.4s; }
        .severity-btn .fw-bold { font-size: 0.8rem; letter-spacing: 1.5px; color: #94A3B8; transition: all 0.4s; }

        .severity-btn:hover { transform: translateY(-5px); box-shadow: 0 15px 30px -5px rgba(0,0,0,0.1); border-color: #E2E8F0; }

        #sev_medium:checked + .severity-btn {
            border-color: #0284C7;
            background: #F0F9FF;
        }
        #sev_medium:checked + .severity-btn i, #sev_medium:checked + .severity-btn .fw-bold { color: #0284C7; }

        #sev_high:checked + .severity-btn {
            border-color: #059669;
            background: #ECFDF5;
        }
        #sev_high:checked + .severity-btn i, #sev_high:checked + .severity-btn .fw-bold { color: #059669; }

        #sev_critical:checked + .severity-btn {
            border-color: #D97706;
            background: #FFFBEB;
            animation: pulse-amber 2s infinite;
        }
        #sev_critical:checked + .severity-btn i, #sev_critical:checked + .severity-btn .fw-bold { color: #D97706; }

        @keyframes pulse-amber {
            0% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(217, 119, 6, 0); }
            100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
        }

        .btn-vital {
            background: linear-gradient(135deg, #059669 0%, #0284C7 100%);
            border: none;
            color: white;
            box-shadow: 0 12px 24px rgba(5, 150, 105, 0.25);
            transition: all 0.4s ease;
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 800;
            padding: 1.25rem;
            position: relative;
            overflow: hidden;
        }
        .btn-vital::after {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: 0.5s;
        }
        .btn-vital:hover {
            transform: translateY(-4px) scale(1.02);
            box-shadow: 0 20px 35px rgba(5, 150, 105, 0.4);
            color: white;
        }
        .btn-vital:hover::after { left: 100%; }

        .policy-alert {
            background: #fff;
            border: 1px solid #F1F5F9 !important;
            border-left: 5px solid #0284C7 !important;
            box-shadow: 0 10px 20px -5px rgba(0,0,0,0.03);
        }

        .info-card-subtle {
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 1.5rem;
        }

        .animate-float { animation: float 6s infinite ease-in-out; }
        @keyframes float { 0% { transform: translateY(0px); } 50% { transform: translateY(-10px); } 100% { transform: translateY(0px); } }

        .particles-subtle { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; background-image: radial-gradient(#059669 0.5px, transparent 0.5px); background-size: 80px 80px; opacity: 0.03; pointer-events: none; }
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>
    <div class="particles-subtle"></div>

    <div class="emergency-header text-center">
        <div class="container">
            <div class="animate-float">
                <i class="bi bi-heart-pulse-fill display-1 text-success mb-3" style="color: var(--vital-green) !important;"></i>
            </div>
            <h1 class="display-4 mb-3">YOU ARE IN SAFE HANDS</h1>
            <p class="hope-message">Our dedicated emergency team is standing by to provide you with the professional care and support you need right now.</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="alert policy-alert border-0 rounded-4 mb-4 shadow-sm p-4">
                    <div class="d-flex align-items-center">
                        <div class="bg-primary-soft p-3 rounded-circle me-3" style="background: rgba(2, 132, 199, 0.1);">
                            <i class="bi bi-shield-lock-fill fs-3" style="color: var(--sky-blue);"></i>
                        </div>
                        <div>
                            <strong class="d-block mb-1 text-uppercase letter-spacing-1" style="color: var(--sky-blue);">Medical Response Protocol</strong>
                            <p class="small mb-0 text-muted">This specialized channel is reserved for urgent medical needs. Directing our resources to genuine emergencies helps us protect every life.</p>
                        </div>
                    </div>
                </div>

                <div class="glass-card p-4 p-md-5 mt-4">
                    <form action="/api/emergency/report" method="POST">
                        <?php if ($role === 'guardian' && !empty($guardianLinks)): ?>
                        <div class="mb-5 info-card-subtle p-4 shadow-sm">
                            <label class="form-label mb-3 text-uppercase"><i class="bi bi-people-fill me-2" style="color: var(--sky-blue);"></i> WHO REQUIRES ASSISTANCE?</label>
                            <select name="patient_id" class="form-select border-0 shadow-sm" required>
                                <option value="<?php echo $userId; ?>">Myself (Primary Account)</option>
                                <?php foreach ($guardianLinks as $link): ?>
                                    <option value="<?php echo $link['patient_id']; ?>" 
                                            data-gps="<?php echo htmlspecialchars($link['patient']['user_metadata']['ghana_post_gps'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($link['patient']['name']); ?> (<?php echo htmlspecialchars($link['relationship']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted mt-3 d-block opacity-75"><i class="bi bi-info-circle me-1"></i> We will retrieve their medical records to prepare the response team.</small>
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

                        <div class="d-grid mt-5 pt-4 border-top border-light">
                            <button type="submit" id="submitBtn" class="btn btn-vital btn-lg rounded-pill">
                                <span class="normal-text"><i class="bi bi-heart-pulse-fill me-2"></i> ACTIVATE RAPID RESPONSE</span>
                                <span class="loading-text d-none"><span class="spinner-border spinner-border-sm me-2"></span> SECURING CONNECTION...</span>
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
