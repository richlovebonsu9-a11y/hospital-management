<?php
// API: Dispatch help for an emergency
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if (!in_array($role, ['admin', 'doctor', 'nurse'])) { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit; 
}

$emergencyId = $_POST['emergency_id'] ?? '';
$dispatchType = $_POST['dispatch_type'] ?? 'none';
$dispatchNotes = $_POST['dispatch_notes'] ?? '';
$medicationNotes = $_POST['medication_notes'] ?? '';

if (!$emergencyId || $dispatchType === 'none') {
    echo json_encode(['success' => false, 'error' => 'Missing dispatch type']);
    exit;
}

$sb = new Supabase();

// 1. Update Emergency Record
$updateData = [
    'dispatch_type' => $dispatchType,
    'dispatch_notes' => $dispatchNotes,
    'medication_notes' => $medicationNotes,
    'status' => 'dispatched',
    'responded_at' => date('c')
];

$updRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, $updateData, true);

if ($updRes['status'] === 204 || $updRes['status'] === 200) {
    // 2. Notify reporter (patient/guardian)
    $emergRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=reporter_id', null, true);
    if ($emergRes['status'] === 200 && !empty($emergRes['data'])) {
        $reporterId = $emergRes['data'][0]['reporter_id'];
        if ($reporterId) {
            $typeLabel = ucfirst($dispatchType);
            $sb->request('POST', '/rest/v1/notifications', [
                'user_id' => $reporterId,
                'message' => "🚑 Emergency Update: A {$typeLabel} has been dispatched to your location. Stay calm, help is on the way.",
                'type' => 'emergency_update',
                'related_id' => $emergencyId
            ], true);
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to dispatch help']);
}
