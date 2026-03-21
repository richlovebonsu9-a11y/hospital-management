<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_doctor.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';

// Allow doctors, patients, and guardians to request labs.
if (!in_array($role, ['doctor', 'patient', 'guardian'])) { 
    header('Location: /dashboard'); exit; 
}

$patientId = $_POST['patient_id'] ?? '';
$testName = $_POST['test_name'] ?? '';
$testType = $_POST['test_type'] ?? 'Laboratory';

if (!$patientId || !$testName) {
    header('Location: /dashboard_doctor.php?error=missing_test'); exit;
}

$sb = new Supabase();
$res = $sb->request('POST', '/rest/v1/lab_requests', [
    'patient_id' => $patientId,
    'doctor_id' => ($role === 'doctor') ? $u['id'] : null,
    'requester_id' => $u['id'],
    'test_type' => $testType,
    'test_name' => $testName,
    'status' => 'pending'
], true);

if ($res['status'] === 201) {
    // Notify all technicians
    $techRes = $sb->request('GET', '/rest/v1/profiles?role=eq.technician&select=id', null, true);
    if ($techRes['status'] === 200 && !empty($techRes['data'])) {
        $requesterName = $u['user_metadata']['name'] ?? 'User';
        $requesterTitle = ucfirst($role);
        
        foreach ($techRes['data'] as $tech) {
            $sb->request('POST', '/rest/v1/notifications', [
                'user_id' => $tech['id'],
                'message' => "New Lab Request (" . $testType . " - " . $testName . ") ordered by " . $requesterTitle .  " " . $requesterName
            ], true);
        }
    }
}

// Redirect back to EMR to seamlessly stay on the patient record
header('Location: /emr.php?patient_id=' . $patientId . '&test_ordered=1');
exit;
