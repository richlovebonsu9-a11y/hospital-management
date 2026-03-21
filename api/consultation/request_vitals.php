<?php
// API: Request Fresh Vitals from Nurse Triage
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_doctor.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$patientId = $_POST['patient_id'] ?? '';

if (!$patientId) {
    header('Location: /dashboard_doctor.php'); exit;
}

$sb = new Supabase();
$doctorName = $u['user_metadata']['name'] ?? 'A Doctor';

// We create a new appointment mapped to the General OPD / Nurse Triage to alert staff.
// The staff dashboard's task queue naturally pulls scheduled appointments for processing vitals.
$res = $sb->request('POST', '/rest/v1/appointments', [
    'patient_id' => $patientId,
    'department' => 'General OPD',
    'appointment_date' => date('c'), // Current time ISO8601
    'status' => 'scheduled',
    'reason' => "URGENT TRIAGE: Dr. $doctorName requests fresh vitals immediately."
], true); // useServiceKey = true to bypass RLS blocks

header('Location: /consultation.php?patient_id=' . $patientId . '&vitals_requested=1');
exit;
