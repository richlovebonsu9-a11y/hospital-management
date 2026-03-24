<?php
// Emergency Now Page - Kobby Moore Hospital
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMERGENCY NOW - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .emergency-header { background: #dc3545; color: white; padding: 40px 0; }
        .severity-btn { transition: all 0.2s; border: 4px solid transparent; cursor: pointer; min-width: 140px; }
        .severity-btn:hover { transform: translateY(-5px); }
        .severity-btn.active { border-color: white; transform: scale(1.1); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
        input[name="severity"]:checked + .severity-btn { border-color: white; transform: scale(1.1); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="bg-light">
    <div class="emergency-header text-center">
        <div class="container">
            <h1 class="display-3 fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill"></i> EMERGENCY NOW</h1>
            <p class="lead mb-0">Help is on the way. Please provide details below.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-lg rounded-5 p-4">
                    <form action="/api/emergency/report.php" method="POST">
                        <div class="mb-5 text-center">
                            <label class="form-label fw-bold h5 mb-3">Select Severity</label>
                            <div class="d-flex justify-content-center gap-3">
                                <div class="severity-option">
                                    <input type="radio" name="severity" value="medium" id="sev_medium" class="d-none" required>
                                    <div onclick="selectSeverity('medium', this)" class="severity-btn bg-warning p-3 rounded-4 text-white text-center d-block">
                                        <i class="bi bi-heart-pulse h1"></i>
                                        <div class="fw-bold mt-2 text-uppercase">Medium</div>
                                    </div>
                                </div>
                                <div class="severity-option">
                                    <input type="radio" name="severity" value="high" id="sev_high" class="d-none">
                                    <div onclick="selectSeverity('high', this)" class="severity-btn bg-orange p-3 rounded-4 text-white text-center d-block" style="background: #fd7e14;">
                                        <i class="bi bi-activity h1"></i>
                                        <div class="fw-bold mt-2 text-uppercase">High</div>
                                    </div>
                                </div>
                                <div class="severity-option">
                                    <input type="radio" name="severity" value="critical" id="sev_critical" class="d-none">
                                    <div onclick="selectSeverity('critical', this)" class="severity-btn bg-danger p-3 rounded-4 text-white text-center d-block">
                                        <i class="bi bi-shield-fill-exclamation h1"></i>
                                        <div class="fw-bold mt-2 text-uppercase">Critical</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold text-danger">Nature of Emergency</label>
                            <select name="emergency_type" class="form-select rounded-pill px-4 py-3 border-light bg-light fw-bold" required>
                                <option value="">-- Choose Situation --</option>
                                <optgroup label="Ambulance Dispatch Required">
                                    <option value="car_accident">Car and Motor Accident</option>
                                    <option value="labour">Labour / Maternity</option>
                                    <option value="sudden_consciousness_loss">Sudden Consciousness Loss</option>
                                    <option value="breathing_difficulty">Breathing Difficulty</option>
                                </optgroup>
                                <optgroup label="Dispatch Rider Specialist Needed">
                                    <option value="cardiac">Cardiac Emergency</option>
                                    <option value="diabetic">Diabetic Emergency</option>
                                    <option value="asthmatic">Asthmatic Attack</option>
                                    <option value="snake_bite">Snake Bite</option>
                                    <option value="dog_bite">Dog Bite</option>
                                    <option value="scorpion_bite">Scorpion Bite</option>
                                </optgroup>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Primary Symptoms / Details</label>
                            <textarea name="symptoms" class="form-control rounded-4 p-4 border-light bg-light" rows="3" placeholder="Briefly describe what is happening..." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">GhanaPostGPS Address</label>
                            <input type="text" name="ghana_post_gps" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="AK-485-9323" value="<?php echo $_SESSION['user']['user_metadata']['ghana_post_gps'] ?? ''; ?>">
                            <small class="text-muted mt-2 d-block">This is mandatory for rapid response accuracy in Ghana.</small>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-danger btn-lg py-3 fw-bold rounded-pill shadow-lg text-uppercase">Request Immediate Dispatch &rarr;</button>
                        </div>
                    </form>
                </div>
                <div class="text-center mt-4">
                    <a href="/dashboard" class="text-muted text-decoration-none small">&larr; Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectSeverity(val, el) {
            const radio = document.querySelector(`input[value="${val}"]`);
            if (radio) radio.checked = true;
            document.querySelectorAll('.severity-btn').forEach(btn => btn.classList.remove('active'));
            el.classList.add('active');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const checked = document.querySelector('input[name="severity"]:checked');
            if (checked) {
                const btn = checked.nextElementSibling;
                if (btn) btn.classList.add('active');
            }
        });
    </script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
