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
$vitalsData = [
    'temperature' => $temp,
    'blood_pressure' => $bp,
    'weight' => $weight,
    'pulse' => $pulse
];

$sb->request('POST', '/rest/v1/vitals', array_merge($vitalsData, [
    'patient_id' => $patientId,
    'recorded_by' => $u['id']
]));

// 2. Save Consultation Record
$sb->request('POST', '/rest/v1/consultations', [
    'patient_id' => $patientId,
    'doctor_id' => $u['id'],
    'notes' => "Diagnosis: $diagnosis\n\nNotes: $notes\n\nAdmission Recommended: $admission",
    'vitals' => json_encode($vitalsData)
]);

header('Location: /dashboard_doctor.php?visit_finished=1');
exit;
