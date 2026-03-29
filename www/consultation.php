<?php
// Consultation Studio - K.M. General Hospital
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

// Fetch patient info
$profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patient_id . '&select=name,blood_group', null, true);
$patientProfile = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
$patient_name = $patientProfile['name'] ?? ("Patient " . substr($patient_id, 0, 8));

// Fetch available drugs for the prescription list
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation Studio - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { font-family: 'Montserrat', sans-serif; overflow: hidden; background: #0F172A; }
        
        /* Split Screen Layout */
        .studio-container { display: flex; height: 100vh; width: 100vw; }
        
        /* LEFT: Telemedicine / Patient Feed (Dark Mode) */
        .telemed-panel {
            flex: 0 0 40%;
            background: linear-gradient(180deg, #1E293B 0%, #0F172A 100%);
            color: white;
            position: relative;
            display: flex;
            flex-direction: column;
            border-right: 1px solid rgba(255,255,255,0.05);
        }
        
        .video-feed-placeholder {
            flex-grow: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            position: relative;
        }

        .pulse-ring {
            width: 150px; height: 150px;
            border-radius: 50%;
            background: rgba(37, 99, 235, 0.1);
            position: absolute;
            animation: pulse 2s infinite ease-out;
        }
        
        @keyframes pulse {
            0% { transform: scale(0.8); opacity: 0.8; }
            100% { transform: scale(1.5); opacity: 0; }
        }

        .patient-avatar {
            width: 120px; height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            display: flex; align-items: center; justify-content: center;
            font-size: 3rem; color: white;
            z-index: 2;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            border: 4px solid #1E293B;
        }

        .call-controls {
            padding: 20px;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: center;
            gap: 15px;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        
        .control-btn {
            width: 50px; height: 50px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
        }
        
        .bg-glass { background: rgba(255,255,255,0.1); color: white; }
        .bg-glass:hover { background: rgba(255,255,255,0.2); }
        .bg-danger-control { background: #EF4444; color: white; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.4); }
        .bg-danger-control:hover { background: #DC2626; transform: scale(1.05); }

        .vitals-glass-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 1rem;
            padding: 1rem;
            margin: 1rem;
        }

        /* RIGHT: Clinical Workspace (Light Mode) */
        .workspace-panel {
            flex: 0 0 60%;
            background: #F8FAFC;
            height: 100%;
            overflow-y: auto;
            position: relative;
        }
        
        .workspace-header {
            background: white;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #E2E8F0;
            position: sticky; top: 0; z-index: 10;
        }

        .clinical-form { padding: 2rem; padding-bottom: 100px; }
        
        .form-section {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
            border: 1px solid #F1F5F9;
        }
        
        .section-title {
            color: #2563EB; font-weight: 700; font-size: 0.9rem; text-transform: uppercase;
            letter-spacing: 1px; margin-bottom: 1.25rem;
            display: flex; align-items: center;
        }

        .action-bar {
            position: fixed; bottom: 0; right: 0; width: 60%;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-top: 1px solid #E2E8F0;
            padding: 1rem 2rem;
            display: flex; justify-content: space-between; align-items: center;
            z-index: 20;
        }

        /* Scrolled Custom */
        .workspace-panel::-webkit-scrollbar { width: 6px; }
        .workspace-panel::-webkit-scrollbar-thumb { background: #CBD5E1; border-radius: 10px; }
        
        /* Modern Select */
        .form-select, .form-control { background-color: #F8FAFC; border-color: #E2E8F0; border-radius: 0.75rem; }
        .form-select:focus, .form-control:focus { background-color: #fff; border-color: #93C5FD; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
    </style>
</head>
<body>
    <div class="studio-container">
        <!-- LEFT: Patient Feed -->
        <div class="telemed-panel">
            <div class="p-4 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0">Live Consultation</h5>
                    <small class="text-secondary"><span class="text-success">●</span> Secure Connection</small>
                </div>
                <div class="px-3 py-1 rounded-pill bg-glass border border-secondary text-white font-monospace" id="call-timer">00:00</div>
            </div>

            <div class="video-feed-placeholder">
                <div class="pulse-ring"></div>
                <div class="patient-avatar shadow-lg">
                    <?php echo strtoupper(substr($patientProfile['name'] ?? 'P', 0, 1)); ?>
                </div>
                <h4 class="mt-4 fw-bold mb-1"><?php echo htmlspecialchars($patient_name); ?></h4>
                <p class="text-muted">Blood Group: <span class="text-danger fw-bold"><?php echo htmlspecialchars($patientProfile['blood_group'] ?? 'N/A'); ?></span></p>
            </div>

            <div class="vitals-glass-card">
                <h6 class="text-muted text-uppercase small fw-bold mb-3"><i class="bi bi-activity text-primary me-2"></i> Latest Vitals (<?php echo $latestVitals ? date('h:i A', strtotime($latestVitals['recorded_at'])) : 'None'; ?>)</h6>
                <div class="row g-2 text-center">
                    <div class="col-3 border-end border-secondary border-opacity-25">
                        <small class="text-secondary d-block" style="font-size: 10px;">TEMP</small>
                        <span class="fw-bold text-white"><?php echo $latestVitals['temperature'] ?? '--'; ?>°C</span>
                    </div>
                    <div class="col-3 border-end border-secondary border-opacity-25">
                        <small class="text-secondary d-block" style="font-size: 10px;">BP</small>
                        <span class="fw-bold text-white"><?php echo $latestVitals['blood_pressure'] ?? '--'; ?></span>
                    </div>
                    <div class="col-3 border-end border-secondary border-opacity-25">
                        <small class="text-secondary d-block" style="font-size: 10px;">PULSE</small>
                        <span class="fw-bold text-white"><?php echo $latestVitals['pulse'] ?? '--'; ?></span>
                    </div>
                    <div class="col-3">
                        <small class="text-secondary d-block" style="font-size: 10px;">WEIGHT</small>
                        <span class="fw-bold text-white"><?php echo $latestVitals['weight'] ?? '--'; ?>kg</span>
                    </div>
                </div>
            </div>

            <!-- Pre-Lab Results Hint -->
            <?php if (!empty($labRequests)): ?>
            <div class="vitals-glass-card mt-0 border-primary border-opacity-25">
                <h6 class="text-muted text-uppercase small fw-bold mb-2"><i class="bi bi-droplet text-primary me-2"></i> Latest Lab Reports</h6>
                <?php $latestLab = $labRequests[0]; ?>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="small text-truncate me-2 text-white">
                        <?php echo htmlspecialchars($latestLab['test_name']); ?>
                    </div>
                    <span class="badge bg-<?php echo ($latestLab['status'] === 'completed') ? 'success' : 'warning text-dark'; ?> rounded-pill">
                        <?php echo strtoupper($latestLab['status']); ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>

            <div class="call-controls">
                <button class="control-btn bg-glass"><i class="bi bi-mic-fill"></i></button>
                <button class="control-btn bg-glass"><i class="bi bi-camera-video-fill"></i></button>
                <button class="control-btn bg-glass"><i class="bi bi-chat-text-fill"></i></button>
                <a href="/emr.php?patient_id=<?php echo $patient_id; ?>" class="control-btn bg-danger-control text-decoration-none" title="End Call"><i class="bi bi-telephone-x-fill"></i></a>
            </div>
        </div>

        <!-- RIGHT: Clinical Data Entry -->
        <div class="workspace-panel shadow-lg">
            <form action="/api/consultation/save.php" method="POST" id="clinicalForm">
                <input type="hidden" name="patient_id" value="<?php echo htmlspecialchars($patient_id); ?>">
                
                <div class="workspace-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">Clinical Notes & Orders</h4>
                        <div class="text-muted small">Session connected to EMR</div>
                    </div>
                    <a href="/emr.php?patient_id=<?php echo $patient_id; ?>" class="btn btn-light rounded-pill border fw-bold text-secondary px-4">
                        <i class="bi bi-person-lines-fill me-2"></i> View Full EMR
                    </a>
                </div>

                <div class="clinical-form">
                    
                    <!-- Vitals Overrides -->
                    <div class="form-section">
                        <div class="section-title"><i class="bi bi-heart-pulse me-2 fs-5"></i> Immediate Vitals Update (Optional)</div>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Temp (°C)</label>
                                <input type="number" step="0.1" name="temperature" class="form-control" placeholder="e.g. 36.5" value="<?php echo $latestVitals['temperature'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">BP (mmHg)</label>
                                <input type="text" name="blood_pressure" class="form-control" placeholder="120/80" value="<?php echo htmlspecialchars($latestVitals['blood_pressure'] ?? ''); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Pulse (BPM)</label>
                                <input type="number" name="pulse" class="form-control" placeholder="72" value="<?php echo $latestVitals['pulse'] ?? ''; ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Weight (kg)</label>
                                <input type="number" step="0.1" name="weight" class="form-control" placeholder="70" value="<?php echo $latestVitals['weight'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Diagnosis & Notes -->
                    <div class="form-section">
                        <div class="section-title"><i class="bi bi-journal-text me-2 fs-5"></i> Diagnosis & Observations</div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Primary Diagnosis</label>
                            <input type="text" name="diagnosis" class="form-control border-primary" placeholder="Enter definitive or provisional diagnosis..." required>
                        </div>
                        <div>
                            <label class="form-label fw-bold small text-muted">Clinical Notes</label>
                            <textarea name="notes" class="form-control" rows="4" placeholder="Patient reports..." required></textarea>
                        </div>
                    </div>

                    <!-- Prescriptions -->
                    <div class="form-section">
                        <div class="section-title d-flex justify-content-between w-100">
                            <span><i class="bi bi-capsule me-2 fs-5"></i> e-Prescription</span>
                            <button type="button" class="btn btn-sm btn-primary rounded-pill px-3" id="add-med-btn">
                                <i class="bi bi-plus-lg"></i> Add Drug
                            </button>
                        </div>
                        
                        <div id="medication-list">
                            <div class="medication-item bg-light p-3 rounded-4 mb-3 border position-relative">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="small fw-bold text-muted">Select Drug</label>
                                        <select name="meds[0][drug_id]" class="form-select border-start border-4 border-primary">
                                            <option value="">-- Choose from Pharmacy --</option>
                                            <?php foreach ($availableDrugs as $drug): ?>
                                                <option value="<?php echo $drug['id']; ?>">
                                                    <?php echo htmlspecialchars($drug['drug_name']); ?> 
                                                    (Stock: <?php echo $drug['stock_count']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold text-muted">Dosage</label>
                                        <input type="text" name="meds[0][dosage]" class="form-control" placeholder="e.g. 500mg">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold text-muted">Frequency</label>
                                        <input type="text" name="meds[0][frequency]" class="form-control" placeholder="1x3">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold text-muted">Duration</label>
                                        <input type="text" name="meds[0][duration]" class="form-control" placeholder="5 days">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="small fw-bold text-muted">Qty</label>
                                        <input type="number" name="meds[0][quantity]" class="form-control" placeholder="15" value="1" min="1">
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger position-absolute top-0 end-0 m-2 border-0 remove-med-btn d-none rounded-circle">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Admission Recommendation -->
                    <div class="form-section flex-row d-flex justify-content-between align-items-center">
                        <div>
                            <div class="section-title mb-1"><i class="bi bi-hospital me-2 fs-5"></i> Hospital Admission</div>
                            <p class="small text-muted mb-0">Does this patient require inpatient care?</p>
                        </div>
                        <div class="form-check form-switch fs-4 mb-0">
                            <input class="form-check-input bg-danger border-danger" type="checkbox" role="switch" name="recommend_admission" value="yes">
                        </div>
                    </div>

                </div>

                <!-- Floating Bottom Action Bar -->
                <div class="action-bar border-top shadow-lg">
                    <div class="text-secondary small fw-bold">
                        <i class="bi bi-info-circle me-1"></i> Form auto-saves upon submission.
                    </div>
                    <button type="submit" class="btn btn-success btn-lg rounded-pill px-5 shadow-sm fw-bold">
                        <i class="bi bi-send-check-fill me-2"></i> Save Clinical Record
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Call Timer
        let seconds = 0;
        setInterval(() => {
            seconds++;
            const mins = String(Math.floor(seconds / 60)).padStart(2, '0');
            const secs = String(seconds % 60).padStart(2, '0');
            document.getElementById('call-timer').textContent = `${mins}:${secs}`;
        }, 1000);

        // Add Medication Logic
        let medCount = 1;
        document.getElementById('add-med-btn').addEventListener('click', function() {
            const container = document.getElementById('medication-list');
            const firstItem = container.querySelector('.medication-item');
            const newItem = firstItem.cloneNode(true);
            
            newItem.querySelector('.remove-med-btn').classList.remove('d-none');
            newItem.querySelectorAll('input').forEach(input => { input.value = ''; if(input.type === 'number') input.value = '1'; });
            newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            
            const inputs = newItem.querySelectorAll('[name^="meds[0]"]');
            inputs.forEach(input => {
                const oldName = input.getAttribute('name');
                const newName = oldName.replace('meds[0]', `meds[${medCount}]`);
                input.setAttribute('name', newName);
            });
            
            container.appendChild(newItem);
            medCount++;
        });

        document.getElementById('medication-list').addEventListener('click', function(e) {
            if(e.target.closest('.remove-med-btn')) {
                e.target.closest('.medication-item').remove();
            }
        });
    </script>
</body>
</html>
