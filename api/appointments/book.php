<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u         = json_decode($_COOKIE['sb_user'], true);
$patientId = $_POST['patient_id'] ?? $u['id'];
$department = trim($_POST['department'] ?? '');
$date       = trim($_POST['date'] ?? '');
$reason     = trim($_POST['reason'] ?? '');

if (!$department || !$date) {
    header('Location: /dashboard_patient.php?error=missing_fields'); exit;
}

$sb  = new Supabase();
$data = [
    'patient_id'       => $patientId,
    'department'       => $department,
    'appointment_date' => $date, // Corrected column name to match schema.sql
    'reason'           => $reason,
    'status'           => 'scheduled',
];

if (($u['user_metadata']['role'] ?? '') === 'guardian') {
    $data['guardian_id'] = $u['id'];
}

$res = $sb->request('POST', '/rest/v1/appointments', $data);

// Check for success status (201 Created)
if ($res['status'] === 201) {
    // Notify all staff in the selected department
    $staffRes = $sb->request('GET', '/rest/v1/profiles?department=eq.' . urlencode($department), null, true);
    if ($staffRes['status'] === 200 && !empty($staffRes['data'])) {
        foreach ($staffRes['data'] as $staff) {
            $msg = "New Appointment Request in your department (" . htmlspecialchars($department) . ") on " . date('M d, H:i', strtotime($date));
            $sb->request('POST', '/rest/v1/notifications', [
                'user_id' => $staff['id'],
                'message' => $msg
            ]);
        }
    }
} else {
    $role = $u['user_metadata']['role'] ?? 'patient';
    $back = ($role === 'guardian') ? '/dashboard_guardian.php' : '/dashboard_patient.php';
    header('Location: ' . $back . '?error=booking_failed&msg=' . urlencode($res['data']['message'] ?? 'Unable to save appointment.'));
    exit;
}

$role  = $u['user_metadata']['role'] ?? 'patient';
$redir = ($role === 'guardian') ? '/dashboard_guardian.php?appt_booked=1' : '/dashboard_patient.php?appt_booked=1';
header('Location: ' . $redir);
exit;
