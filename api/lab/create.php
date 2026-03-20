<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_doctor.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'doctor') { header('Location: /dashboard'); exit; }

$patientId = $_POST['patient_id'] ?? '';
$testName = $_POST['test_name'] ?? '';
$testType = $_POST['test_type'] ?? 'Laboratory';

if (!$patientId || !$testName) {
    header('Location: /dashboard_doctor.php?error=missing_test'); exit;
}

$sb = new Supabase();
$res = $sb->request('POST', '/rest/v1/lab_requests', [
    'patient_id' => $patientId,
    'doctor_id' => $u['id'],
    'test_type' => $testType,
    'test_name' => $testName,
    'status' => 'pending'
]);

header('Location: /dashboard_doctor.php?test_ordered=1');
exit;
