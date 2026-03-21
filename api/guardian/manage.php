<?php
// API: Manage Guardian-Patient Relationship
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard_patient.php');
    exit;
}

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$userId = $user['id'];
$linkId = $_POST['link_id'] ?? null;
$action = $_POST['action'] ?? null;

if (!$linkId || !$action) {
    header('Location: /dashboard_patient.php?error=invalid_request');
    exit;
}

$sb = new Supabase();

if ($action === 'approve') {
    // Update status to 'approved'
    // Use service key to ensure we can update even if RLS is strict (though patient should be allowed)
    $res = $sb->request('PATCH', '/rest/v1/guardians?id=eq.' . $linkId . '&patient_id=eq.' . $userId, [
        'status' => 'approved'
    ], true);
    
    if ($res['status'] >= 200 && $res['status'] < 300) {
        header('Location: /dashboard_patient.php?success=link_approved');
    } else {
        header('Location: /dashboard_patient.php?error=update_failed');
    }
} elseif ($action === 'decline') {
    // We can either delete the link or mark as 'rejected'
    // Let's mark as 'rejected' for record keeping
    $res = $sb->request('PATCH', '/rest/v1/guardians?id=eq.' . $linkId . '&patient_id=eq.' . $userId, [
        'status' => 'rejected'
    ], true);
    
    if ($res['status'] >= 200 && $res['status'] < 300) {
        header('Location: /dashboard_patient.php?success=link_declined');
    } else {
        header('Location: /dashboard_patient.php?error=update_failed');
    }
} else {
    header('Location: /dashboard_patient.php?error=invalid_action');
}
exit;
