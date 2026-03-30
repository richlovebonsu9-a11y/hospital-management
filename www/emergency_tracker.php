<?php
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$emergencyId = $_GET['id'] ?? null;
if (!$emergencyId) {
    $fallback = isset($_SESSION['user']) ? '/emergency' : '/emergency_guest';
    header("Location: $fallback");
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
$createdAt = $e['created_at'] ?? null;
$serverElapsed = 0;
if ($status === 'dispatched' && !empty($dispatchedAt)) {
    $serverElapsed = max(0, time() - strtotime($dispatchedAt));
}

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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            background-image: radial-gradient(var(--slate-800) 0.5px, transparent 0.5px);
            background-size: 30px 30px;
            background-attachment: fixed;
            color: var(--slate-800);
            font-family: 'Montserrat', sans-serif;
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .status-badge {
            background: var(--slate-950);
            color: #fff;
            border: none;
            padding: 10px 24px;
            border-radius: 4px;
            font-weight: 900;
            font-size: 0.7rem;
            letter-spacing: 2px;
            text-transform: uppercase;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
            display: inline-flex;
            align-items: center;
        }

        /* Industrial Radar Container */
        .radar-container {
            width: 320px;
            height: 320px;
            margin: 20px auto;
            position: relative;
            background: #fff;
            border-radius: 1rem;
            border: 4px solid var(--slate-950);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-shadow: 15px 15px 0px rgba(15, 23, 42, 0.05);
        }
        /* Tactical Search Grid */
        .radar-container::before {
            content: '';
            position: absolute;
            width: 100%; height: 100%;
            background-image: 
                linear-gradient(rgba(15, 23, 42, 0.05) 1px, transparent 1px),
                linear-gradient(90deg, rgba(15, 23, 42, 0.05) 1px, transparent 1px);
            background-size: 20px 20px;
            z-index: 1;
        }

        .radar-sweep {
            position: absolute;
            width: 100%;
            height: 100%;
            background: conic-gradient(from 0deg, transparent 0%, rgba(249, 115, 22, 0.15) 20%, transparent 40%);
            border-radius: 50%;
            animation: rotate 3s linear infinite;
            z-index: 2;
        }
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .patient-dot {
            width: 16px;
            height: 16px;
            background: var(--slate-950);
            border: 3px solid #fff;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 0 5px rgba(15, 23, 42, 0.1);
            z-index: 10;
        }
        .responder-dot {
            width: 20px;
            height: 20px;
            background: var(--rescue-orange);
            border: 4px solid #fff;
            border-radius: 50%;
            position: absolute;
            top: 30%;
            left: 70%;
            box-shadow: 0 0 30px rgba(249, 115, 22, 0.4);
            animation: beacon-pulse 1.5s infinite ease-in-out;
            transition: all 1.5s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 11;
        }
        @keyframes beacon-pulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(249, 115, 22, 0.4); }
            70% { transform: scale(1.4); box-shadow: 0 0 0 20px rgba(249, 115, 22, 0); }
            100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(249, 115, 22, 0); }
        }

        .guide-card {
            background: #fff;
            border-radius: 1.5rem;
            border: 2px solid var(--slate-950);
            box-shadow: 10px 10px 0px rgba(15, 23, 42, 0.05);
        }

        /* Mission Milestone Timeline */
        .status-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-bottom: 40px;
        }
        .status-timeline::after {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            width: 100%;
            height: 2px;
            background: var(--slate-800);
            opacity: 0.1;
            z-index: 1;
        }
        .status-step {
            position: relative;
            z-index: 2;
            text-align: center;
            width: 33%;
        }
        .step-icon {
            width: 50px;
            height: 50px;
            background: #fff;
            border: 2px solid var(--slate-800);
            border-radius: 8px;
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: 0.4s;
            color: var(--slate-800);
        }
        .status-active .step-icon {
            background: var(--rescue-orange);
            border-color: var(--rescue-orange);
            color: #fff;
            box-shadow: 0 10px 20px rgba(249, 115, 22, 0.3);
            transform: translateY(-5px);
        }
        .status-complete .step-icon {
            background: var(--slate-950);
            border-color: var(--slate-950);
            color: #fff;
        }
        .step-label {
            font-size: 0.7rem;
            font-weight: 800;
            letter-spacing: 1.5px;
            color: var(--slate-600);
            text-transform: uppercase;
        }
        .status-active .step-label { color: var(--rescue-orange-dark); }

        .pulse-text { animation: alert-flash 1s infinite alternate; color: var(--rescue-orange); font-weight: 900; }
        @keyframes alert-flash { from { opacity: 0.5; } to { opacity: 1; } }

        .btn-exit {
            background: var(--slate-950);
            border: none;
            color: #fff;
            border-radius: 4px;
            padding: 10px 24px;
            font-weight: 800;
            font-size: 0.75rem;
            letter-spacing: 1.5px;
            transition: 0.3s;
        }
        .btn-exit:hover {
            background: var(--rescue-orange);
            color: white;
            box-shadow: 0 5px 15px rgba(249, 115, 22, 0.2);
        }
    </style>
    </style>
</head>
<body>
    <div class="container py-4 position-relative" style="z-index:10;">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-5">
            <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-broadcast pulse-text me-2"></i>RESCUE MISSION CONTROL</h4>
            <a href="/dashboard" class="btn btn-exit">EXIT LIVE TRACKER</a>
        </div>

        <!-- Mission Milestones -->
        <div class="status-timeline">
            <div id="step-received" class="status-step <?php echo ($status === 'pending') ? 'status-active' : 'status-complete'; ?>">
                <div class="step-icon"><i class="bi bi-cpu"></i></div>
                <div class="step-label">Report Received</div>
            </div>
            <div id="step-responding" class="status-step <?php echo ($status === 'dispatched' || $status === 'assigned') ? 'status-active' : (($status === 'resolved') ? 'status-complete' : ''); ?>">
                <div class="step-icon"><i class="bi bi-truck-flatbed"></i></div>
                <div class="step-label">Responding</div>
            </div>
            <div id="step-resolved" class="status-step <?php echo ($status === 'resolved') ? 'status-active' : ''; ?>">
                <div class="step-icon"><i class="bi bi-check-lg"></i></div>
                <div class="step-label">Resolved</div>
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
                            <h5 class="fw-bold mb-1 text-dark"><?php echo htmlspecialchars($responder['name']); ?></h5>
                            <p class="text-secondary extra-small mb-0" id="etaSubtext"><i class="bi bi-person-check me-1"></i>RESCUE SPECIALIST ON INTERCEPT COURSE.</p>
                            <div class="mt-3 fs-3 fw-bold tracking-tight text-dark" id="etaDisplay">
                                <?php if($status === 'resolved'): ?>
                                    <span class="text-success"><i class="bi bi-check-all me-2"></i>Resolved</span>
                                <?php else: ?>
                                    <span class="text-primary">ETA: -- mins</span>
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
                        <span class="p-2 bg-warning bg-opacity-25 rounded-3 me-3" style="border: 2px solid var(--rescue-orange);"><i class="bi bi-book-half" style="color: var(--rescue-orange-dark);"></i></span>
                        <div class="flex-grow-1">
                            <div class="text-secondary text-uppercase fw-bold mb-1" style="font-size: 0.75rem; letter-spacing: 1px;">Field Manual: Protocol</div>
                            <div class="text-dark fs-4 fw-bold"><?php echo $guide['title']; ?></div>
                        </div>
                    </h5>
                    
                    <div class="d-flex flex-column gap-3">
                        <?php foreach($guide['steps'] as $index => $step): ?>
                            <div class="d-flex gap-3 align-items-start p-3 rounded-4" style="background: rgba(255,255,255,0.03);">
                                <div class="bg-primary-soft text-primary fw-bold rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="fw-semibold text-dark" style="font-size: 1.05rem; line-height: 1.6;"><?php echo $step; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 p-3 rounded-4 bg-warning-soft border border-warning border-opacity-10 d-flex gap-3">
                        <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                        <div class="fw-medium text-warning" style="font-size: 0.95rem; line-height: 1.5;">
                            <strong class="fw-bold">Note:</strong> We have received your voice note and symptoms. Do not panic—medical experts are now reviewing your details.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-center mt-5">
            <p class="text-secondary extra-small fw-bold">MISSION ID: <span class="orange-text"><?php echo $emergencyId; ?></span> • GGHMS SEARCH & RESCUE COMMAND • OPERATIONAL STATE</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    <script>
        // Supabase Real-time Configuration
        const supabaseUrl = '<?php echo getenv('SUPABASE_URL'); ?>';
        const supabaseKey = '<?php echo getenv('SUPABASE_ANON_KEY'); ?>';
        const emergencyId = '<?php echo $emergencyId; ?>';

        let currentStatus = '<?php echo $status; ?>';
        let dispatchedAt = '<?php echo $dispatchedAt; ?>';
        let serverElapsedAtLoad = <?php echo $serverElapsed; ?>;
        const loadTimestamp = performance.now();
        
        const responderDot = document.getElementById('responderDot');
        const etaDisplay = document.getElementById('etaDisplay');
        const etaSubtext = document.getElementById('etaSubtext');
        
        const MEDICAL_COMPLETION_MSG = "Medical intervention successfully established. Our specialists have secured the site and are administering advanced clinical care. Patient status is being monitored under professional supervision.";

        if (supabaseUrl && supabaseKey) {
            const supabase = supabasejs.createClient(supabaseUrl, supabaseKey);

            // Subscribe to real-time updates for this specific emergency mission
            const channel = supabase.channel(`emergency-${emergencyId}`)
                .on('postgres_changes', { 
                    event: 'UPDATE', 
                    schema: 'public', 
                    table: 'emergencies',
                    filter: `id=eq.${emergencyId}` 
                }, (payload) => {
                    const data = payload.new;
                    console.log("Mission Update Received:", data);
                    
                    // Update global state
                    const oldStatus = currentStatus;
                    currentStatus = data.status;
                    dispatchedAt = data.dispatched_at;

                    // Update UI elements
                    updateTimeline(data.status);
                    
                    // If responder is newly assigned or dispatched, we might need to reload to show responder info
                    if (data.assigned_to && !document.getElementById('responderInfo')) {
                        location.reload(); 
                    } else {
                        updateETA();
                    }
                })
                .subscribe();
        }

        function updateTimeline(newStatus) {
            const sReceived = document.getElementById('step-received');
            const sResponding = document.getElementById('step-responding');
            const sResolved = document.getElementById('step-resolved');

            if (newStatus === 'pending') {
                sReceived.className = 'status-step status-active';
                sResponding.className = 'status-step';
                sResolved.className = 'status-step';
            } else if (newStatus === 'dispatched' || newStatus === 'assigned') {
                sReceived.className = 'status-step status-complete';
                sResponding.className = 'status-step status-active';
                sResolved.className = 'status-step';
            } else if (newStatus === 'resolved') {
                sReceived.className = 'status-step status-complete';
                sResponding.className = 'status-step status-complete';
                sResolved.className = 'status-step status-active';
            }
        }

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

            if ((currentStatus === 'dispatched' || currentStatus === 'assigned') && (dispatchedAt || serverElapsedAtLoad > 0)) {
                const secondsSinceLoad = (performance.now() - loadTimestamp) / 1000;
                const totalElapsedSeconds = Math.floor(serverElapsedAtLoad + secondsSinceLoad);
                
                const initialMins = (parseInt(emergencyId.substring(0, 4), 16) % 4) + 7;
                const totalInitialSeconds = initialMins * 60;
                let remainingTotalSeconds = totalInitialSeconds - totalElapsedSeconds;
                
                if (remainingTotalSeconds <= 0) {
                    etaDisplay.innerHTML = '<span class="text-primary">Arriving at Site</span>';
                } else {
                    const m = Math.floor(remainingTotalSeconds / 60);
                    const s = remainingTotalSeconds % 60;
                    etaDisplay.innerHTML = '<span class="text-primary">ETA: ' + m + ":" + (s < 10 ? '0' : '') + s + '</span>';
                }
            } else {
                etaDisplay.innerHTML = '<span class="text-primary">ETA: ~10 mins</span>';
            }
        }

        // Radar Radar Logic (Local Animation)
        if (responderDot) {
            setInterval(() => {
                if (currentStatus === 'dispatched' || currentStatus === 'assigned') {
                    const top = parseInt(responderDot.style.top || '30');
                    const left = parseInt(responderDot.style.left || '70');
                    if (top < 50) responderDot.style.top = (top + 0.1) + '%';
                    if (left > 50) responderDot.style.left = (left - 0.1) + '%';
                }
            }, 1000);
        }

        // Run ETA countdown independently
        setInterval(updateETA, 1000);
    </script>
</body>
</html>
