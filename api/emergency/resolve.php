<?php
// API: Resolve/Complete an emergency + cross-notify counterpart
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$actingUserId = $u['id'] ?? '';
$actingRole = $u['user_metadata']['role'] ?? '';
$actingName = $u['user_metadata']['name'] ?? ucfirst($actingRole);

$emergencyId = $_POST['emergency_id'] ?? '';
$resolutionNotes = $_POST['resolution_notes'] ?? '';

if (!$emergencyId) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

$sb = new Supabase();

// 0. Fetch emergency to get assigned_to and reporter_id
$emergRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=id,assigned_to,reporter_id', null, true);
$emergency = ($emergRes['status'] === 200 && !empty($emergRes['data'])) ? $emergRes['data'][0] : null;
$assignedToId = $emergency['assigned_to'] ?? null;
$patientId = $emergency['reporter_id'] ?? null;

// 1. Update Emergency Record
$updateData = [
    'status' => 'resolved',
    'dispatch_notes' => $resolutionNotes,
    'resolved_at' => date('c'),
    'handled_by' => $actingUserId
];

$updRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, $updateData, true);

if ($updRes['status'] === 204 || $updRes['status'] === 200) {
    $shortId = '#' . strtoupper(substr($emergencyId, 0, 5));

    // 2a. Notify patient (if known)
    if ($patientId) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $patientId,
            'message' => "✅ Your emergency {$shortId} has been resolved. You are in safe hands.",
            'type' => 'emergency_resolved',
            'related_id' => $emergencyId
        ], true);
    }

    // 2b. Cross-notify counterpart
    if ($actingRole === 'admin' && $assignedToId && $assignedToId !== $actingUserId) {
        // Admin resolved it → notify assigned staff
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $assignedToId,
            'message' => "✅ Emergency {$shortId} has been resolved by Admin {$actingName}. You can clear this task from your queue.",
            'type' => 'emergency_handled_by_admin',
            'related_id' => $emergencyId
        ], true);
    } elseif ($actingRole !== 'admin') {
        // Staff resolved it → notify all admins
        $adminsRes = $sb->request('GET', '/rest/v1/profiles?role=eq.admin&select=id', null, true);
        if ($adminsRes['status'] === 200 && !empty($adminsRes['data'])) {
            foreach ($adminsRes['data'] as $admin) {
                if ($admin['id'] !== $actingUserId) {
                    $sb->request('POST', '/rest/v1/notifications', [
                        'user_id' => $admin['id'],
                        'message' => "✅ Emergency {$shortId} has been resolved by {$actingName} ({$actingRole}). You can clear it from the queue.",
                        'type' => 'emergency_handled_by_staff',
                        'related_id' => $emergencyId
                    ], true);
                }
            }
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to resolve emergency']);
}
