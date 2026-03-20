<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_doctor.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'doctor') { header('Location: /dashboard'); exit; }

$patientId = $_POST['patient_id'] ?? '';
$meds = $_POST['medication_details'] ?? '';

if (!$patientId || !$meds) {
    header('Location: /dashboard_doctor.php?error=missing_data'); exit;
}

$sb = new Supabase();
$res = $sb->request('POST', '/rest/v1/prescriptions', [
    'patient_id' => $patientId,
    'doctor_id' => $u['id'],
    'medication_details' => $meds,
    'status' => 'pending'
]);

header('Location: /dashboard_doctor.php?prescription_sent=1');
exit;
