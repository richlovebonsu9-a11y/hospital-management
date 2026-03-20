<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_doctor.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'doctor') { header('Location: /dashboard'); exit; }

$patientId = $_POST['patient_id'] ?? '';
$temp = $_POST['temperature'] ?? null;
$bp = $_POST['blood_pressure'] ?? '';
$weight = $_POST['weight'] ?? null;
$pulse = $_POST['pulse'] ?? null;
$notes = $_POST['notes'] ?? '';
$diagnosis = $_POST['diagnosis'] ?? '';
$admission = isset($_POST['recommend_admission']) ? 'yes' : 'no';

if (!$patientId) {
    header('Location: /dashboard_doctor.php?error=invalid_patient'); exit;
}

$sb = new Supabase();

// 1. Save Vitals
$sb->request('POST', '/rest/v1/vitals', [
    'patient_id' => $patientId,
    'recorded_by' => $u['id'],
    'temperature' => $temp,
    'blood_pressure' => $bp,
    'weight' => $weight,
    'pulse' => $pulse
]);

// 2. Here we would also save notes/diagnosis to a consultations/EMR table if we had one defined in detail
// for now, vitals is the key structured data.

header('Location: /dashboard_doctor.php?visit_finished=1');
exit;
