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
    
    // 1. Find the patient email in Supabase Auth (since profiles table doesn't have email)
    $authRes = $sb->request('GET', '/auth/v1/admin/users', null, true);
    
    $patientId = null;
    $patientActualName = null;
    
    if ($authRes['status'] === 200 && isset($authRes['data']['users'])) {
        foreach ($authRes['data']['users'] as $u) {
            if (isset($u['email']) && strtolower($u['email']) === strtolower($patientEmail)) {
                $patientId = $u['id'];
                $patientActualName = $u['user_metadata']['name'] ?? '';
                break;
            }
        }
    }
    
    if (!$patientId) {
        header('Location: /dashboard_guardian.php?error=patient_not_found'); exit;
    }
    
    // 2. Ensure BOTH guardian and patient have profile records (required for foreign keys)
    // Check Guardian
    $gProfileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $userId . '&select=id', null, true);
    if ($gProfileRes['status'] !== 200 || empty($gProfileRes['data'])) {
        $sb->request('POST', '/rest/v1/profiles', [
            'id' => $userId,
            'name' => $_SESSION['user']['user_metadata']['name'] ?? 'Guardian',
            'role' => 'guardian',
            'ghana_post_gps' => 'Unknown'
        ], true);
    }

    // Check Patient
    $profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=id', null, true);
    if ($profileRes['status'] !== 200 || empty($profileRes['data'])) {
        // Create minimal profile
        $sb->request('POST', '/rest/v1/profiles', [
            'id' => $patientId,
            'name' => $patientActualName,
            'role' => 'patient',
            'ghana_post_gps' => 'Unknown'
        ], true);
    }
    
    // 3. Create the guardian link in the table
    $linkData = [
        'guardian_id' => $userId,
        'patient_id' => $patientId,
        'relationship' => $relationship,
        'status' => 'pending'
    ];
    
    $res = $sb->request('POST', '/rest/v1/guardians', $linkData, true);

    if ($res['status'] >= 200 && $res['status'] < 300) {
        header('Location: /dashboard_guardian.php?linked=1');
    } else {
        $errorMsg = urlencode($res['data']['message'] ?? 'Insertion failed - possible duplicate or constraint error');
        header('Location: /dashboard_guardian.php?error=link_failed&msg=' . $errorMsg);
    }
    exit;
}

// Standard Profile Update
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$dob = trim($_POST['dob'] ?? '');
$gps = trim($_POST['ghana_post_gps'] ?? '');
$ghanaCard = trim($_POST['ghana_card'] ?? '');
$nhis = trim($_POST['nhis_membership_number'] ?? '');

if (!$name || !$phone) {
    header('Location: /dashboard_patient.php?error=missing_fields'); exit;
}

$metadata = array_merge($u['user_metadata'] ?? [], [
    'name' => $name,
    'phone' => $phone,
    'dob' => $dob,
    'ghana_post_gps' => $gps,
    'ghana_card' => $ghanaCard,
    'nhis_membership_number' => $nhis
]);

// 1. Update Auth Metadata
$res = $sb->request('PUT', '/auth/v1/admin/users/' . $userId, ['user_metadata' => $metadata], true);

// 2. Update Profiles Table (This is what the EMR and Dashboards actually read)
$profileUpdate = [
    'name' => $name,
    'phone' => $phone,
    'dob' => $dob,
    'ghana_post_gps' => $gps,
    'ghana_card' => $ghanaCard,
    'nhis_membership_number' => $nhis
];
$sb->request('PATCH', '/rest/v1/profiles?id=eq.' . $userId, $profileUpdate, true);

if ($res['status'] >= 200 && $res['status'] < 300) {
    $u['user_metadata'] = $metadata;
    setcookie('sb_user', json_encode($u), time() + (86400 * 30), "/");
    $_SESSION['user'] = $u; // Sync session as well
    
    $role = $u['user_metadata']['role'] ?? 'patient';
    $target = ($role === 'guardian') ? '/dashboard_guardian.php' : '/dashboard_patient.php';
    header('Location: ' . $target . '?success=1');
} else {
    header('Location: /dashboard_patient.php?error=update_failed');
}
exit;
