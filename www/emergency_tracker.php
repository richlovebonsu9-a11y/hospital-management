<?php
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$emergencyId = $_GET['id'] ?? null;
if (!$emergencyId) {
    header('Location: /emergency');
    exit;
}

$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=*,assigned_to_profile:profiles!assigned_to(*)', null, true);
$e = ($res['status'] === 200 && !empty($res['data'])) ? $res['data'][0] : null;

if (!$e) {
    echo "Emergency encounter not found.";
    exit;
}

$type = $e['emergency_type'];
$status = $e['status'];
$responder = $e['assigned_to_profile'] ?? null;
$dispatchedAt = $e['dispatched_at'] ?? null;

// First-Aid Logic
$firstAidGuides = [
    'cardiac_emergencies' => [
        'title' => 'Cardiac Support',
        'steps' => [
            'Help the person sit down and remain calm.',
            'Loosen any tight clothing (ties, belts).',
            'Ask if they have prescribed chest pain medication (like nitroglycerin) and help them take it.',
            'If they become unconscious, prepare for CPR immediately.'
        ]
    ],
    'asthmatic_attacks' => [
        'title' => 'Asthma Relief',
        'steps' => [
            'Sit the person upright. Do not let them lie down.',
            'Help them use their blue rescue inhaler (Salbutamol).',
            'Take slow, steady breaths. Try to keep them calm.',
            'If symptoms worsen, do not wait—keep the inhaler handy.'
        ]
    ],
    'car_and_motor_accident' => [
        'title' => 'Trauma Care',
        'steps' => [
            'DO NOT move the patient unless there is immediate danger (fire/explosion).',
            'Check for breathing. If bleeding, apply firm pressure with a clean cloth.',
            'Keep the patient warm and talk to them to keep them conscious.',
            'If unconscious but breathing, do not move the neck.'
        ]
    ],
    'snake_bite' => [
        'title' => 'Bite Management',
        'steps' => [
            'Keep the bitten limb below the level of the heart.',
            'Remain as still as possible to slow the spread of venom.',
            'DO NOT try to suck out the venom or use a tourniquet.',
            'Remove any jewelry or tight clothing before swelling starts.'
        ]
    ],
    'labour' => [
        'title' => 'Emergency Labour',
        'steps' => [
            'Help the mother into a comfortable position (usually on her left side).',
            'Encourage slow, deep breathing through contractions.',
            'Prepare clean towels and warm water.',
            'Wash your hands thoroughly and stay calm for the mother.'
        ]
    ],
    'default' => [
        'title' => 'Emergency Support',
        'steps' => [
            'Ensure the environment is safe for you and the patient.',
            'Stay with the patient until help arrives.',
            'Keep the patient warm and monitor their breathing.',
            'If they have a voice note, do not worry—our team has already heard it.'
        ]
    ]
];

$guide = $firstAidGuides[$type] ?? $firstAidGuides['default'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Tracking | GGHMS Emergency</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --vital-green: #059669;
            --vital-green-dark: #064E3B;
            --vital-green-light: #ECFDF5;
            --sky-blue: #0284C7;
            --sky-blue-light: #F0F9FF;
            --slate-800: #1E293B;
            --slate-700: #334155;
            --slate-500: #64748B;
            --glass-white: rgba(255, 255, 255, 0.9);
        }
        body {
            background: linear-gradient(135deg, #F0F9FF 0%, #ECFDF5 100%);
            background-attachment: fixed;
            color: var(--slate-700);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Breathing Background Blobs */
        .blob {
            position: absolute;
            filter: blur(80px);
            z-index: 0;
            opacity: 0.3;
            border-radius: 50%;
            animation: move 20s infinite alternate ease-in-out;
            pointer-events: none;
        }
        .blob-1 { width: 400px; height: 400px; background: #BAE6FD; top: -100px; left: -100px; }
        .blob-2 { width: 500px; height: 500px; background: #A7F3D0; bottom: -150px; right: -50px; animation-delay: -5s; }

        @keyframes move {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(80px, 40px) scale(1.1); }
        }

        .status-badge {
            background: #fff;
            color: var(--sky-blue);
            border: 1px solid rgba(2, 132, 199, 0.1);
            padding: 10px 20px;
            border-radius: 100px;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 1px;
            text-transform: uppercase;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            display: inline-flex;
            align-items: center;
        }

        /* Enhanced Radar Container */
        .radar-container {
            width: 300px;
            height: 300px;
            margin: 30px auto;
            position: relative;
            background: #fff;
            border-radius: 50%;
            border: 1px solid #E2E8F0;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.05);
        }
        /* Hexagonal Grid Overlay */
        .radar-container::before {
            content: '';
            position: absolute;
            width: 100%; height: 100%;
            background-image: 
                linear-gradient(30deg, #F1F5F9 12%, transparent 12.5%, transparent 87%, #F1F5F9 87.5%, #F1F5F9),
                linear-gradient(150deg, #F1F5F9 12%, transparent 12.5%, transparent 87%, #F1F5F9 87.5%, #F1F5F9),
                linear-gradient(30deg, #F1F5F9 12%, transparent 12.5%, transparent 87%, #F1F5F9 87.5%, #F1F5F9),
                linear-gradient(150deg, #F1F5F9 12%, transparent 12.5%, transparent 87%, #F1F5F9 87.5%, #F1F5F9),
                linear-gradient(60deg, #F8FAFC 25%, transparent 25.5%, transparent 75%, #F8FAFC 75.5%, #F8FAFC),
                linear-gradient(60deg, #F8FAFC 25%, transparent 25.5%, transparent 75%, #F8FAFC 75.5%, #F8FAFC);
            background-size: 40px 70px;
            background-position: 0 0, 0 0, 20px 35px, 20px 35px, 0 0, 20px 35px;
            opacity: 0.4;
            z-index: 1;
        }

        .radar-sweep {
            position: absolute;
            width: 100%;
            height: 100%;
            background: conic-gradient(from 0deg, transparent 0%, rgba(5, 150, 105, 0.1) 20%, transparent 40%);
            border-radius: 50%;
            animation: rotate 5s linear infinite;
            z-index: 2;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .patient-dot {
            width: 14px;
            height: 14px;
            background: #F97316; /* Amber instead of Red for warning but hopeful */
            border: 3px solid #fff;
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 15px rgba(249, 115, 22, 0.5);
            z-index: 10;
        }
        .responder-dot {
            width: 16px;
            height: 16px;
            background: var(--sky-blue);
            border: 3px solid #fff;
            border-radius: 50%;
            position: absolute;
            top: 30%;
            left: 70%;
            box-shadow: 0 0 20px rgba(2, 132, 199, 0.4);
            animation: pulse-responder 3s infinite ease-in-out;
            transition: all 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
        }
        @keyframes pulse-responder {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.4); }
            70% { transform: scale(1.3); box-shadow: 0 0 0 15px rgba(2, 132, 199, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); }
        }

        .guide-card {
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.7);
            border-radius: 2.5rem;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.05);
            padding: 2.5rem !important;
        }

        /* Vertical-Style Status Timeline */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 50px;
            padding: 0 20px;
        }
        .status-timeline::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 40px;
            right: 40px;
            height: 4px;
            background: #E2E8F0;
            border-radius: 2px;
            z-index: 1;
        }
        .status-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 33%;
        }
        .step-icon {
            width: 44px;
            height: 44px;
            background: #fff;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.5s;
            color: #94A3B8;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
        }
        .status-active .step-icon {
            background: var(--vital-green);
            border-color: var(--vital-green);
            color: #fff;
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.3);
        }
        .status-complete .step-icon {
            background: var(--sky-blue);
            border-color: var(--sky-blue);
            color: #fff;
        }
        .step-label {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: #94A3B8;
            text-transform: uppercase;
        }
        .status-active .step-label { color: var(--vital-green-dark); }

        .pulse-text { animation: blink 2s infinite ease-in-out; color: var(--sky-blue); font-weight: 700; }
        @keyframes blink { 0% { opacity: 0.5; } 50% { opacity: 1; } 100% { opacity: 0.5; } }

        .btn-exit {
            background: #fff;
            border: 1px solid #E2E8F0;
            color: var(--slate-500);
            border-radius: 100px;
            padding: 8px 20px;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02);
        }
        .btn-exit:hover {
            color: var(--sky-blue);
            border-color: var(--sky-blue);
            transform: translateY(-2px);
            box-shadow: 0 8px 15px rgba(2, 132, 199, 0.08);
        }
    </style>
    </style>
</head>
<body>
    <div class="blob blob-1"></div>
    <div class="blob blob-2"></div>

    <div class="container py-4 position-relative" style="z-index:10;">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h4 class="fw-bold mb-0" style="color: var(--vital-green-dark);"><i class="bi bi-shield-fill-check me-2"></i>GGHMS Vitality Pulse</h4>
            <a href="/dashboard" class="btn btn-exit">Safe Exit</a>
        </div>

        <!-- Status Timeline -->
        <div class="status-timeline">
            <div class="status-step <?php echo ($status === 'pending') ? 'status-active' : 'status-complete'; ?>">
                <div class="step-icon"><i class="bi bi-send-check"></i></div>
                <div class="step-label">Signal Received</div>
            </div>
            <div class="status-step <?php echo ($status === 'dispatched') ? 'status-active' : (($status === 'resolved') ? 'status-complete' : ''); ?>">
                <div class="step-icon"><i class="bi bi-geo-fill"></i></div>
                <div class="step-label">Team En-Route</div>
            </div>
            <div class="status-step <?php echo ($status === 'resolved') ? 'status-active' : ''; ?>">
                <div class="step-icon"><i class="bi bi-heart-fill"></i></div>
                <div class="step-label">Care Established</div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Simulated Radar -->
            <div class="col-lg-6">
                <div class="guide-card p-4 text-center h-100 d-flex flex-column justify-content-center">
                    <div class="status-badge mb-2">
                        <span class="pulse-text"><i class="bi bi-broadcast me-2"></i>Live Location Tracking...</span>
                    </div>
                    
                    <div class="radar-container">
                        <div class="radar-sweep"></div>
                        <div class="patient-dot"></div>
                        <?php if($status === 'dispatched' || $status === 'resolved'): ?>
                            <div class="responder-dot" id="responderDot"></div>
                        <?php endif; ?>
                        <!-- Concentric circles -->
                        <div style="position: absolute; width: 66%; height: 66%; border: 1px solid rgba(13, 148, 136, 0.1); border-radius: 50%;"></div>
                        <div style="position: absolute; width: 33%; height: 33%; border: 1px solid rgba(13, 148, 136, 0.1); border-radius: 50%;"></div>
                    </div>

                    <?php if($responder): ?>
                        <div class="mt-2" id="responderInfo">
                            <h5 class="fw-bold mb-1" style="color: var(--vital-green-dark);"><?php echo htmlspecialchars($responder['name']); ?></h5>
                            <p class="text-muted small mb-0" id="etaSubtext"><i class="bi bi-shield-shaded me-1"></i>Our clinical specialist is navigating to your location.</p>
                            <div class="mt-3 fs-3 fw-bold tracking-tight" id="etaDisplay">
                                <?php if($status === 'resolved'): ?>
                                    <span class="text-success"><i class="bi bi-check-all me-2"></i>Mission Complete</span>
                                <?php else: ?>
                                    ETA: -- mins
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="mt-2">
                            <h6 class="text-white-50 small">Coordinating with nearby specialists...</h6>
                            <div class="progress mt-3 mx-auto" style="height: 4px; width: 200px; background: rgba(255,255,255,0.05); border-radius: 10px; overflow: hidden;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width: 100%"></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- First-Aid Guide -->
            <div class="col-lg-6">
                <div class="guide-card p-4 h-100">
                    <h5 class="fw-bold mb-4 d-flex align-items-center">
                        <span class="p-2 bg-success bg-opacity-10 rounded-3 me-3"><i class="bi bi-shield-plus" style="color: var(--vital-green);"></i></span>
                        <div class="flex-grow-1">
                            <div class="extra-small text-muted text-uppercase letter-spacing-1 mb-1" style="font-size: 0.65rem;">Immediate First-Aid</div>
                            <div style="color: var(--vital-green-dark);"><?php echo $guide['title']; ?></div>
                        </div>
                    </h5>
                    
                    <div class="d-flex flex-column gap-3">
                        <?php foreach($guide['steps'] as $index => $step): ?>
                            <div class="d-flex gap-3 align-items-start p-3 rounded-4" style="background: rgba(255,255,255,0.03);">
                                <div class="bg-primary-soft text-primary fw-bold rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.8rem;">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="small fw-medium opacity-90"><?php echo $step; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 p-3 rounded-4 bg-warning-soft border border-warning border-opacity-10 d-flex gap-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                        <div class="small text-warning">
                            <strong>Note:</strong> We have received your voice note and symptoms. Do not panic—medical experts are now reviewing your details.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-white-50 extra-small">Case ID: <code class="text-primary"><?php echo $emergencyId; ?></code> • GGHMS Ghana Universal Emergency Response Pulse</p>
        </div>
    </div>

    <script>
        // Dynamic ETA and Status Logic
        const responderDot = document.getElementById('responderDot');
        const etaDisplay = document.getElementById('etaDisplay');
        const etaSubtext = document.getElementById('etaSubtext');
        const currentStatus = '<?php echo $status; ?>';
        const dispatchedAt = '<?php echo $dispatchedAt; ?>';
        
        const MEDICAL_COMPLETION_MSG = "Medical intervention successfully established. Our specialists have secured the site and are administering advanced clinical care. Patient status is being monitored under professional supervision.";

        function updateETA() {
            if (currentStatus === 'resolved') {
                etaDisplay.innerHTML = '<span class="text-success"><i class="bi bi-cloud-check-fill me-2"></i>Response Finalized</span>';
                etaSubtext.innerHTML = `<div class="mt-2 p-3 rounded-4 bg-success bg-opacity-10 border border-success border-opacity-25 text-success small fw-medium" style="line-height:1.6;">${MEDICAL_COMPLETION_MSG}</div>`;
                if(responderDot) {
                    responderDot.style.top = '50%';
                    responderDot.style.left = '50%';
                }
                return;
            }

            if (currentStatus === 'dispatched' && dispatchedAt) {
                const dispatchTime = new Date(dispatchedAt).getTime();
                const now = new Date().getTime();
                const diffMs = now - dispatchTime;
                const diffMins = Math.floor(diffMs / 60000);
                
                // Assume a 10-minute response window for the countdown
                const initialETA = 10;
                let remaining = initialETA - diffMins;
                
                if (remaining <= 0) {
                    etaDisplay.innerText = "Arriving at Site";
                    etaDisplay.classList.add('text-primary');
                } else {
                    etaDisplay.innerText = "ETA: " + remaining + " mins";
                }
            } else if (currentStatus === 'dispatched') {
                etaDisplay.innerText = "ETA: Calculating...";
            }
        }

        updateETA();

        if (responderDot && currentStatus !== 'resolved') {
            const moveInterval = setInterval(() => {
                const top = parseInt(responderDot.style.top || '30');
                const left = parseInt(responderDot.style.left || '70');
                
                if (top < 50) responderDot.style.top = (top + 1) + '%';
                if (left > 50) responderDot.style.left = (left - 1) + '%';

                if (top >= 50 && left <= 50) {
                    clearInterval(moveInterval);
                    if (currentStatus !== 'resolved') {
                        etaDisplay.innerText = "Arriving at Site";
                        etaDisplay.classList.add('text-primary');
                    }
                }
            }, 10000); 

            // Update countdown every 30 seconds
            setInterval(updateETA, 30000);
        }

        // Auto-refresh state every 20 seconds
        setInterval(() => {
            fetch(window.location.href)
                .then(res => res.text())
                .then(html => {
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const newStatus = doc.querySelector('script').textContent.match(/currentStatus = '(.*?)'/)[1];
                    
                    if (newStatus !== currentStatus) {
                        location.reload(); // Hard reload on status change to reset JS state
                        return;
                    }

                    const newTimeline = doc.querySelector('.status-timeline');
                    if(newTimeline) document.querySelector('.status-timeline').innerHTML = newTimeline.innerHTML;
                    
                    // Also update guide if needed (though usually static)
                    const newGuide = doc.querySelector('.col-lg-6:last-child .guide-card');
                    if(newGuide) document.querySelector('.col-lg-6:last-child').innerHTML = newGuide.parentElement.innerHTML;
                });
        }, 20000);
    </script>
</body>
</html>
