<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$userId = $u['id'];
$action = $_POST['action'] ?? 'update_profile';
$sb = new Supabase();

if ($action === 'link_patient') {
    $patientName = trim($_POST['patient_name'] ?? '');
    $patientEmail = trim($_POST['patient_email'] ?? '');
    $relationship = trim($_POST['relationship'] ?? 'Guardian');
    
    if (!$patientName || !$patientEmail) {
        header('Location: /dashboard_guardian.php?error=missing_fields'); exit;
    }
    
    // 1. Find the patient profile by Email (use service key to bypass RLS for lookup)
    $pRes = $sb->request('GET', '/rest/v1/profiles?email=eq.' . urlencode($patientEmail) . '&select=id,name', null, true);
    
    if ($pRes['status'] !== 200 || empty($pRes['data'])) {
        header('Location: /dashboard_guardian.php?error=patient_not_found'); exit;
    }
    
    $patient = $pRes['data'][0];
    $patientId = $patient['id'];
    
    // 2. Create the guardian link in the table (standardizing with admin view)
    $linkData = [
        'guardian_id' => $userId,
        'patient_id' => $patientId,
        'relationship' => $relationship,
        'status' => 'pending'
    ];
    
    $res = $sb->request('POST', '/rest/v1/guardians', $linkData);

    if ($res['status'] >= 200 && $res['status'] < 300) {
        header('Location: /dashboard_guardian.php?linked=1');
    } else {
        header('Location: /dashboard_guardian.php?error=link_exists_or_failed');
    }
    exit;
}

// Standard Profile Update
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$dob = trim($_POST['dob'] ?? '');
$gps = trim($_POST['ghana_post_gps'] ?? '');

if (!$name || !$phone) {
    header('Location: /dashboard_patient.php?error=missing_fields'); exit;
}

$metadata = array_merge($u['user_metadata'], [
    'name' => $name,
    'phone' => $phone,
    'dob' => $dob,
    'ghana_post_gps' => $gps
]);

$res = $sb->request('PUT', '/auth/v1/admin/users/' . $userId, ['user_metadata' => $metadata], true);

if ($res['status'] >= 200 && $res['status'] < 300) {
    $u['user_metadata'] = $metadata;
    setcookie('sb_user', json_encode($u), time() + (86400 * 30), "/");
    header('Location: ' . ($_SESSION['user']['user_metadata']['role'] === 'guardian' ? '/dashboard_guardian.php' : '/dashboard_patient.php') . '?success=1');
} else {
    header('Location: /dashboard_patient.php?error=update_failed');
}
exit;
