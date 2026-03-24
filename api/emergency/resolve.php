<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

// Accept both JSON body (from staff dashboard) and POST form data (from admin dashboard)
$jsonData = json_decode(file_get_contents('php://input'), true);
$emergencyId = $_POST['emergency_id'] ?? $jsonData['id'] ?? '';

if (empty($emergencyId)) {
    echo json_encode(['success' => false, 'error' => 'Emergency ID is required']);
    exit;
}

$sb = new Supabase();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
$staffId   = $_SESSION['user']['id'] ?? null;
$staffName = $_SESSION['user']['user_metadata']['name'] ?? 'Emergency Staff';
$staffRole = strtolower($_SESSION['user']['user_metadata']['role'] ?? 'staff');

// 1. Fetch Emergency details (service key required to bypass RLS)
$eRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=*', null, true);
if ($eRes['status'] !== 200 || empty($eRes['data'])) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}
$emergency  = $eRes['data'][0];
$type       = $emergency['emergency_type'] ?? 'general';
$patientId  = $emergency['reporter_id'];
$typeName   = ucwords(str_replace('_', ' ', $type));

// 2. Update Emergency Status to 'resolved'
$updateRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, [
    'status' => 'resolved'
], true);

if ($updateRes['status'] < 200 || $updateRes['status'] >= 300) {
    echo json_encode(['success' => false, 'error' => 'Could not update emergency status']);
    exit;
}

// 3. Fetch patient name for notifications
$pRes  = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=name', null, true);
$pName = ($pRes['status'] === 200 && !empty($pRes['data'])) ? $pRes['data'][0]['name'] : 'Patient';

// 4. Notify Patient
$sb->request('POST', '/rest/v1/notifications', [
    'user_id' => $patientId,
    'message'  => "Your {$typeName} emergency has been resolved. Our team has completed their response. We hope you are doing better.",
    'type'     => 'emergency_resolved'
], true);

// 5. Notify ALL Admins — use 'emergency_handled_by_staff' type so the admin
//    bell panel shows a "Clear" button (dashboard_admin.php listens for this type)
$adminsRes = $sb->request('GET', '/rest/v1/profiles?role=eq.admin&select=id', null, true);
if ($adminsRes['status'] === 200) {
    foreach ($adminsRes['data'] as $admin) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id'    => $admin['id'],
            'message'    => "RESOLVED: {$typeName} emergency for {$pName} has been resolved by {$staffName} ({$staffRole}).",
            'type'       => 'emergency_handled_by_staff',
            'related_id' => $emergencyId
        ], true);
    }
}

// 6. Notify Guardians
$gLinksRes = $sb->request('GET', '/rest/v1/guardians?patient_id=eq.' . $patientId . '&status=eq.approved', null, true);
if ($gLinksRes['status'] === 200) {
    foreach ($gLinksRes['data'] as $link) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id'    => $link['guardian_id'],
            'message'    => "Update for {$pName}: The {$typeName} emergency has been successfully resolved by our emergency team.",
            'type'       => 'emergency_resolved',
            'related_id' => $emergencyId
        ], true);
    }
}

echo json_encode(['success' => true, 'patient' => $pName]);
