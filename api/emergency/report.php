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
$patientId = $_SESSION['user']['id'] ?? null;

if (!$patientId) {
    echo json_encode(['success' => false, 'error' => 'User not logged in']);
    exit;
}

$emergencyType = $_POST['emergency_type'] ?? 'general';
$gps = $_POST['ghana_post_gps'] ?? 'N/A';
$location = $_POST['location'] ?? $gps;
$symptoms = $_POST['symptoms'] ?? 'No symptoms reported';

$data = [
    'reporter_id' => $patientId,
    'emergency_type' => $emergencyType,
    'location' => $location,
    'ghana_post_gps' => $gps,
    'symptoms' => $symptoms,
    'status' => 'pending'
];

$res = $sb->request('POST', '/rest/v1/emergencies', $data, true);

if ($res['status'] >= 200 && $res['status'] < 300) {
    // 1. Notify Admins
    $sb->request('POST', '/rest/v1/notifications', [
        'role' => 'admin',
        'message' => "HIGH ALERT: A " . str_replace('_', ' ', $emergencyType) . " emergency has been reported at " . $location . ".",
        'type' => 'emergency_alert'
    ], true);

    // 2. Specialized Routing Notifications (for Task Queue)
    $teamRole = in_array($emergencyType, ['car_and_motor_accident', 'labour', 'sudden_consciousness_loss', 'breathing_difficulty']) ? 'ambulance' : 'dispatch_rider';
    $sb->request('POST', '/rest/v1/notifications', [
        'role' => $teamRole,
        'message' => "NEW ASSIGNMENT: Urgent " . str_replace('_', ' ', $emergencyType) . " reported.",
        'type' => 'emergency_alert'
    ], true);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => $res['data']['message'] ?? 'Failed to report emergency']);
}
