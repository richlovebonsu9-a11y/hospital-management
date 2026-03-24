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
$eRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=reporter_id');
if ($eRes['status'] !== 200 || empty($eRes['data'])) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}
$patientId = $eRes['data'][0]['reporter_id'];

// 2. Update Status
$updateRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, [
    'status' => 'resolved'
], true);

if ($updateRes['status'] >= 200 && $updateRes['status'] < 300) {
    // 3. Notify Patient
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => $patientId,
        'message' => "Your emergency case has been marked as resolved. We hope you are doing better.",
        'type' => 'emergency_resolved'
    ], true);
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to resolve']);
}
