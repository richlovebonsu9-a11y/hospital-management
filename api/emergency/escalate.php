<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$emergencyId = $data['id'] ?? '';

if (empty($emergencyId)) {
    echo json_encode(['success' => false, 'error' => 'Emergency ID is required']);
    exit;
}

$sb = new Supabase();

// 1. Fetch Emergency for patient_id
$eRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=reporter_id,emergency_type', null, true);
$emergency = ($eRes['status'] === 200 && !empty($eRes['data'])) ? $eRes['data'][0] : null;

if (!$emergency) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}

// 2. Escalate Status (make it pending again for ambulance team)
$updateRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, [
    'escalation_required' => true,
    'assigned_to' => null, 
    'status' => 'pending'
], true);

if ($updateRes['status'] >= 200 && $updateRes['status'] < 300) {
    // 3. Notify Ambulance Team
    $sb->request('POST', '/rest/v1/notifications', [
        'role' => 'ambulance',
        'message' => "CRITICAL ESCALATION: A " . str_replace('_', ' ', $emergency['emergency_type']) . " case requires immediate ambulance transport.",
        'type' => 'emergency_alert'
    ], true);

    // 4. Notify Patient
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => $emergency['reporter_id'],
        'message' => "Your case is being escalated to our Ambulance Team for specialized transport.",
        'type' => 'emergency_escalated'
    ], true);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to escalate']);
}
