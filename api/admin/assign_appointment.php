<?php
// API: Assign Staff to Appointment - Admin only
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_admin.php'); exit; }

$u = json_decode($_COOKIE['sb_user'] ?? '{}', true);
if (($u['user_metadata']['role'] ?? '') !== 'admin') {
    header('Location: /dashboard_admin.php?error=unauthorized');
    exit;
}

$apptId     = $_POST['appointment_id'] ?? '';
$assignedTo = $_POST['assigned_to'] ?? '';

if (!$apptId || !$assignedTo) {
    header('Location: /dashboard_admin.php?error=missing_fields');
    exit;
}

$sb = new Supabase();
// Update the appointment with the assigned staff member
$res = $sb->request('PATCH', '/rest/v1/appointments?id=eq.' . $apptId, [
    'assigned_to' => $assignedTo
]);

if ($res['status'] >= 200 && $res['status'] < 300) {
    header('Location: /dashboard_admin.php?assigned=1#section-appointments');
} else {
    header('Location: /dashboard_admin.php?error=assignment_failed&msg=' . urlencode($res['data']['message'] ?? 'DB Error'));
}
exit;
