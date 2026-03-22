<?php
// API: Assign staff to an emergency
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if ($role !== 'admin') { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit; 
}

$emergencyId = $_POST['emergency_id'] ?? '';
$assignedTo = $_POST['assigned_to'] ?? '';

if (!$emergencyId || !$assignedTo) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

$sb = new Supabase();

// 1. Update Emergency Record
$updateData = [
    'assigned_to' => $assignedTo,
    'status' => 'assigned',
    'responded_at' => date('c')
];

$updRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, $updateData, true);

if ($updRes['status'] === 204 || $updRes['status'] === 200) {
    // 2. Notify assigned staff
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => $assignedTo,
        'message' => "🚨 URGENT: You have been assigned to Emergency #".substr($emergencyId, 0, 5).". Please respond immediately.",
        'type' => 'emergency_assignment',
        'related_id' => $emergencyId
    ], true);

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to assign emergency']);
}
