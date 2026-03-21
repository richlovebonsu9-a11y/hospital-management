<?php
// API: Approve Guardian-Patient Link - Admin only
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$u = json_decode($_COOKIE['sb_user'] ?? '{}', true);
if (($u['user_metadata']['role'] ?? '') !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$linkId = $_POST['link_id'] ?? '';
$action = $_POST['action'] ?? 'approve'; // approve or reject

if (!$linkId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Missing link ID']);
    exit;
}

$sb = new Supabase();
$status = ($action === 'approve') ? 'approved' : 'rejected';

// Update the link status
$res = $sb->request('PATCH', '/rest/v1/guardians?id=eq.' . $linkId, [
    'status' => $status
]);

if ($res['status'] >= 200 && $res['status'] < 300) {
    // Optionally notify the guardian
    // First get the guardian_id from the link
    $linkInfo = $sb->request('GET', '/rest/v1/guardians?id=eq.' . $linkId . '&select=guardian_id,patient:patient_id(name)', null, true);
    if ($linkInfo['status'] === 200 && !empty($linkInfo['data'])) {
        $guardianId = $linkInfo['data'][0]['guardian_id'];
        $patientName = $linkInfo['data'][0]['patient']['name'] ?? 'your patient';
        $msg = "Your request to link with $patientName has been $status by an administrator.";
        
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $guardianId,
            'message' => $msg
        ]);
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => 'Database update failed', 'details' => $res['data']]);
}
