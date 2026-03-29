<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$sb = new Supabase();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
$reporterId = $_SESSION['user']['id'] ?? null;
$patientId = $_POST['patient_id'] ?? $reporterId;

$isGuest = !$reporterId;
$guestName = $_POST['guest_name'] ?? 'Anonymous';
$guestPhone = $_POST['guest_phone'] ?? 'N/A';

$emergencyType = $_POST['emergency_type'] ?? 'general';
$gps = $_POST['ghana_post_gps'] ?? 'N/A';
$symptoms = $_POST['symptoms'] ?? 'No symptoms reported';
$severity = $_POST['severity'] ?? 'medium';

// Fetch patient name if reporter is a guardian and reporting for someone else
$patientName = null;
if ($patientId !== $reporterId) {
    $pRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=name', null, true);
    if ($pRes['status'] === 200 && !empty($pRes['data'])) {
        $patientName = $pRes['data'][0]['name'];
    }
}

// AI-Powered Triage: Detect "Red Flags" and auto-escalate
$redFlags = ['unconscious', 'fainting', 'not breathing', 'chest pain', 'heart attack', 'severe bleeding', 'heavy bleeding', 'crushed', 'paralyzed', 'seizure', 'stroke'];
$riskDetected = false;
$lowerSymptoms = strtolower($symptoms);
foreach ($redFlags as $flag) {
    if (strpos($lowerSymptoms, $flag) !== false) {
        $riskDetected = true;
        break;
    }
}
if ($riskDetected && $severity !== 'critical') {
    $severity = 'critical'; // Auto-escalate to Critical if red flags found
}

$teamRole = in_array($emergencyType, ['car_and_motor_accident', 'labour', 'sudden_consciousness_loss', 'breathing_difficulty']) ? 'ambulance' : 'dispatch_rider';

// Round-Robin / Least Loaded Assignment Logic (Bypassed if type is 'other')
$assignedTo = null;
if ($emergencyType !== 'other') {
    $staffRes = $sb->request('GET', '/rest/v1/profiles?role=eq.' . $teamRole . '&select=id', null, true);
    if ($staffRes['status'] === 200 && !empty($staffRes['data'])) {
        $staffIds = array_column($staffRes['data'], 'id');
        
        $activeTasksRes = $sb->request('GET', '/rest/v1/emergencies?status=neq.resolved&select=assigned_to', null, true);
        $counts = [];
        foreach($staffIds as $sid) $counts[$sid] = 0;
        
        if ($activeTasksRes['status'] === 200) {
            foreach($activeTasksRes['data'] as $at) {
                if ($at['assigned_to'] && isset($counts[$at['assigned_to']])) {
                    $counts[$at['assigned_to']]++;
                }
            }
        }
        
        asort($counts);
        $assignedTo = key($counts);
    }
}

$liveLocation = $_POST['live_location'] ?? '';
if (!empty($liveLocation)) {
    $gps .= " ||LOC|| " . $liveLocation;
}

// Workaround: Embed voice note Base64 and Media into symptoms field
$embeddedSymptoms = $symptoms;
if ($isGuest) {
    $embeddedSymptoms = "[GUEST REPORT: $guestName ($guestPhone)] " . $embeddedSymptoms;
} elseif ($patientName) {
    $embeddedSymptoms = "[Patient: $patientName] " . $embeddedSymptoms;
}
if (!empty($_POST['voice_note_base64'])) {
    $embeddedSymptoms .= " ||VOICE_NOTE|| " . $_POST['voice_note_base64'];
}
if (!empty($_POST['media_base64'])) {
    $embeddedSymptoms .= " ||MEDIA|| " . $_POST['media_base64'];
}

$data = [
    'reporter_id' => $reporterId,
    'emergency_type' => $emergencyType,
    'severity' => $severity,
    'ghana_post_gps' => $gps,
    'symptoms' => $embeddedSymptoms,
    'assigned_to' => $assignedTo,
    'status' => $assignedTo ? 'assigned' : 'pending'
];

$res = $sb->request('POST', '/rest/v1/emergencies', $data, true, ['Prefer' => 'return=representation']);

if ($res['status'] >= 200 && $res['status'] < 300) {
    $insertedEmergency = $res['data'][0] ?? null;
    $emergencyId = $insertedEmergency['id'] ?? null;

    // 1. Notify Admins
    $alertPrefix = $isGuest ? "GUEST ALERT" : "HIGH ALERT";
    $sb->request('POST', '/rest/v1/notifications', [
        'role' => 'admin',
        'message' => "{$alertPrefix}: A " . str_replace('_', ' ', $emergencyType) . " emergency has been reported at {$gps}.",
        'type' => 'emergency_alert',
        'related_id' => $emergencyId
    ], true);

    // 2. Targeted Notification to Assigned Staff (Task Queue)
    if ($assignedTo) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $assignedTo,
            'message' => "NEW ASSIGNMENT: Urgent " . str_replace('_', ' ', $emergencyType) . " assigned to you.",
            'type' => 'emergency_alert',
            'related_id' => $emergencyId
        ], true);
    } else {
        // Fallback to role if no specific staff available
        $sb->request('POST', '/rest/v1/notifications', [
            'role' => $teamRole,
            'message' => "NEW UNASSIGNED ALERT: Urgent " . str_replace('_', ' ', $emergencyType) . " reported.",
            'type' => 'emergency_alert',
            'related_id' => $emergencyId
        ], true);
    }

    echo json_encode(['success' => true, 'emergency_id' => $emergencyId]);
}
 else {
    echo json_encode(['success' => false, 'error' => $res['data']['message'] ?? 'Failed to report emergency']);
}
