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

// 1. Fetch appointment details first to get patient and guardian info
$apptRes = $sb->request('GET', '/rest/v1/appointments?id=eq.' . $apptId . '&select=*,patient:patient_id(name)', null, true);
$appt = ($apptRes['status'] === 200 && !empty($apptRes['data'])) ? $apptRes['data'][0] : null;

if (!$appt) {
    header('Location: /dashboard_admin.php?error=not_found');
    exit;
}

// 2. Fetch the newly assigned staff name
$staffRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $assignedTo . '&select=name', null, true);
$staffName = ($staffRes['status'] === 200 && !empty($staffRes['data'])) ? $staffRes['data'][0]['name'] : 'a healthcare specialist';

// 3. Update the appointment with the assigned staff member
$res = $sb->request('PATCH', '/rest/v1/appointments?id=eq.' . $apptId, [
    'assigned_to' => $assignedTo
]);

if ($res['status'] >= 200 && $res['status'] < 300) {
    // 4. Create Notifications for Patient and Guardian
    $timeStr = date('M d, H:i', strtotime($appt['appointment_date']));
    
    // Explicitly include staff name or fallback
    $displayStaff = !empty($staffName) ? $staffName : 'a medical specialist';
    $msg = "Confirmation: Your appointment on $timeStr has been assigned to $displayStaff.";
    
    // Notify Patient (Always notify patient, even if guardian booked)
    if (!empty($appt['patient_id'])) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $appt['patient_id'],
            'message' => $msg
        ]);
    }
    
    // Notify Guardian (if applicable)
    if (!empty($appt['guardian_id'])) {
        $guardianMsg = "Update: The appointment for " . ($appt['patient']['name'] ?? 'your dependant') . " on $timeStr has been assigned to $displayStaff.";
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $appt['guardian_id'],
            'message' => $guardianMsg
        ]);
    }

    header('Location: /dashboard_admin.php?assigned=1#section-appointments');
} else {
    header('Location: /dashboard_admin.php?error=assignment_failed&msg=' . urlencode($res['data']['message'] ?? 'DB Error'));
}
exit;
