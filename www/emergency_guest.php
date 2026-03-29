<?php
session_start();
// Guest Emergency Reporting - K.M. General Hospital
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GUEST EMERGENCY SOS - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --rescue-red: #DC2626;
            --rescue-red-dark: #991B1B;
            --rescue-red-light: #FEF2F2;
            --slate-950: #020617;
            --slate-800: #1E293B;
            --warm-bg: #FAF9F6;
        }
        body {
            background-color: var(--warm-bg);
            background-image: 
                linear-gradient(rgba(220, 38, 38, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(220, 38, 38, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            min-height: 100vh;
            font-family: 'Montserrat', sans-serif;
        }
        .emergency-header { padding: 80px 0 40px; }
        .emergency-header h1 { font-weight: 900; letter-spacing: -2px; color: var(--rescue-red); text-transform: uppercase; }
        .rescue-line { width: 100px; height: 6px; background: var(--rescue-red); margin: 20px auto; border-radius: 3px; }
        .glass-card {
            background: rgba(255, 255, 255, 0.98);
            border: 2px solid #fff;
            border-radius: 2rem;
            box-shadow: 0 40px 100px rgba(0,0,0,0.1);
        }
        .form-label { font-weight: 800; font-size: 0.75rem; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 0.75rem; }
        .btn-sos {
            background: var(--rescue-red);
            color: white;
            font-weight: 900;
            letter-spacing: 2px;
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 15px 35px rgba(220, 38, 38, 0.3);
            transition: all 0.4s;
            border: none;
        }
        .btn-sos:hover {
            background: var(--rescue-red-dark);
            transform: translateY(-4px);
            box-shadow: 0 20px 45px rgba(220, 38, 38, 0.45);
        }
        .animate-pulse { animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.05); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .pulse-highlight { animation: pulse-red 1.5s infinite; }
        @keyframes pulse-red {
            0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(220, 38, 38, 0); }
            100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
        }
    </style>
</head>
<body>
    <div class="emergency-header text-center">
        <div class="container">
            <div class="animate-pulse mb-4">
                <i class="bi bi-exclamation-triangle-fill display-1 text-danger"></i>
            </div>
            <h1>GUEST EMERGENCY SOS</h1>
            <div class="rescue-line"></div>
            <p class="lead text-secondary fw-semibold">Stay calm. Help is a few clicks away. No account needed.</p>
        </div>
    </div>

    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="glass-card p-4 p-md-5">
                    <form id="emergencyForm">
                        <!-- Guest Identity -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="guest_name" class="form-control" placeholder="Your Name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="guest_phone" class="form-control" placeholder="024 XXX XXXX" required>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Nature of Emergency</label>
                            <select name="emergency_type" class="form-select fw-bold" required>
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
                            <label class="form-label">Emergency Details</label>
                            <textarea name="symptoms" class="form-control" rows="3" placeholder="Describe what has happened..." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label d-flex justify-content-between align-items-center">
                                Voice Note (Optional)
                                <span id="recordingIndicator" class="badge bg-danger rounded-pill px-2 d-none animate-pulse" style="font-size: 0.65rem;">REC</span>
                            </label>
                            <div class="d-flex align-items-center gap-3 p-3 glass-card bg-opacity-10 border border-dashed border-secondary rounded-4 shadow-sm">
                                <button type="button" id="recordBtn" class="btn btn-outline-danger rounded-circle p-0 d-flex align-items-center justify-content-center" style="width: 52px; height: 52px; min-width: 52px;">
                                    <i class="bi bi-mic-fill fs-4"></i>
                                </button>
                                <div class="flex-grow-1 overflow-hidden">
                                    <div id="recordStatus" class="text-secondary extra-small fw-semibold mb-1">Click to capture audio context</div>
                                    <div id="playbackContainer" class="d-none">
                                        <audio id="audioPlayback" controls style="height: 32px; width: 100%;"></audio>
                                    </div>
                                </div>
                                <input type="hidden" name="voice_note_base64" id="voiceNoteBase64">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Photo / Video Evidence (Optional)</label>
                            <input type="file" id="mediaUploadInput" class="form-control" accept="image/*,video/*">
                            <input type="hidden" name="media_base64" id="mediaBase64">
                            <small class="text-muted mt-2 d-block"><i class="bi bi-camera me-1"></i> Capture the scene to help responders prepare.</small>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">GhanaPostGPS / Location Note</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-geo-alt-fill text-danger"></i></span>
                                <input type="text" name="ghana_post_gps" class="form-control border-start-0" placeholder="e.g. GA-123-4567 or Area Name" required>
                            </div>
                            <input type="hidden" name="live_location" id="liveLocation">
                            <small class="text-muted mt-2 d-block"><i class="bi bi-pin-map me-1"></i> We will also capture your GPS location automatically.</small>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" id="submitBtn" class="btn btn-sos">
                                <span class="normal-text"><i class="bi bi-megaphone-fill me-2"></i> ACTIVATE RESCUE MISSION</span>
                                <span class="loading-text d-none"><span class="spinner-border spinner-border-sm me-2"></span> MOBILIZING HELP...</span>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="text-center mt-5">
                    <a href="/" class="text-secondary text-decoration-none"><i class="bi bi-house-door me-1"></i> Return to Homepage</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Location capturing
        const locInput = document.getElementById('liveLocation');
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => { locInput.value = pos.coords.latitude + ',' + pos.coords.longitude; },
                err => { console.warn('Location access denied', err); },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        }

        // Media Recorder Logic
        let mediaRecorder;
        let audioChunks = [];
        const recordBtn = document.getElementById('recordBtn');
        const recordStatus = document.getElementById('recordStatus');
        const playbackContainer = document.getElementById('playbackContainer');
        const audioPlayback = document.getElementById('audioPlayback');
        const voiceInput = document.getElementById('voiceNoteBase64');
        const recordingIndicator = document.getElementById('recordingIndicator');

        // Photo/Video File Handling
        const mediaInput = document.getElementById('mediaUploadInput');
        const mediaBase64Input = document.getElementById('mediaBase64');
        mediaInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) { mediaBase64Input.value = ''; return; }
            if (file.size > 5 * 1024 * 1024) {
                Swal.fire('File Too Large', 'Please keep photo/video under 5MB.', 'warning');
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
                Swal.fire({ title: 'Microphone Needed', text: 'Please enable microphone access to record voice notes.', icon: 'info' });
            }
        };

        document.getElementById('emergencyForm').onsubmit = async function(e) {
            e.preventDefault();
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.querySelector('.normal-text').classList.add('d-none');
            btn.querySelector('.loading-text').classList.remove('d-none');

            try {
                const formData = new FormData(this);
                formData.append('severity', 'high'); // Default for guest reports

                const res = await fetch('/api/emergency/report.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                
                if (data.success) {
                    Swal.fire({
                        title: 'MISSION TRIGGERED!',
                        html: '<p>Help is on the way. Please stay calm and look out for the responder.</p><p>You are being redirected to the <b>Live Tracker</b>.</p>',
                        icon: 'success',
                        confirmButtonText: 'Track Rescue',
                        allowOutsideClick: false
                    }).then(() => {
                        window.location.href = '/emergency_tracker.php?id=' + data.emergency_id;
                    });
                } else {
                    Swal.fire('Dispatch Error', data.error || 'Failed to send alert.', 'error');
                    btn.disabled = false;
                    btn.querySelector('.normal-text').classList.remove('d-none');
                    btn.querySelector('.loading-text').classList.add('d-none');
                }
            } catch (err) {
                Swal.fire('Connection Error', 'Please check your internet and try again.', 'warning');
                btn.disabled = false;
                btn.querySelector('.normal-text').classList.remove('d-none');
                btn.querySelector('.loading-text').classList.add('d-none');
            }
        };
    </script>
</body>
</html>
