<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$sb = new Supabase();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
$staffId = $_SESSION['user']['id'] ?? null;
$staffName = $_SESSION['user']['user_metadata']['name'] ?? 'Emergency Staff';

if (!$staffId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$emergencyId = $_POST['emergency_id'] ?? '';

if (empty($emergencyId)) {
    echo json_encode(['success' => false, 'error' => 'Emergency ID is required']);
    exit;
}

// 1. Fetch Emergency for patient_id and type
$eRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=reporter_id,emergency_type,status', null, true);
$emergency = ($eRes['status'] === 200 && !empty($eRes['data'])) ? $eRes['data'][0] : null;

if (!$emergency) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}

$patientId = $emergency['reporter_id'];
$type = str_replace('_', ' ', $emergency['emergency_type'] ?? 'emergency');

// 2. Fetch patient name
$pRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=name', null, true);
$pName = ($pRes['status'] === 200 && !empty($pRes['data'])) ? $pRes['data'][0]['name'] : 'Patient';

// 3. Notify all Admins and Nurses to assign a ward and bed
$recipientsRes = $sb->request('GET', '/rest/v1/profiles?role=in.(admin,nurse)&select=id', null, true);
$notified = 0;
if ($recipientsRes['status'] === 200) {
    foreach ($recipientsRes['data'] as $r) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id'    => $r['id'],
            'message'    => "ADMISSION REQUIRED: Emergency responder ($staffName) has recommended immediate admission for $pName following a $type emergency. Please assign a ward and bed.",
            'type'       => 'admission_recommendation',
            'related_id' => $patientId
        ], true);
        $notified++;
    }
}

// 4. Notify patient
$sb->request('POST', '/rest/v1/notifications', [
    'user_id' => $patientId,
    'message'  => "Admission Recommended: Our emergency team has recommended your admission following your $type. Hospital staff will assign you a bed shortly.",
    'type'     => 'admission_request',
    'related_id' => $emergencyId
], true);

// 5. Notify Guardians
$gLinksRes = $sb->request('GET', '/rest/v1/guardians?patient_id=eq.' . $patientId . '&status=eq.approved', null, true);
if ($gLinksRes['status'] === 200) {
    foreach ($gLinksRes['data'] as $link) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id'    => $link['guardian_id'],
            'message'    => "Medical Alert: Admission has been recommended for $pName by the emergency response team.",
            'type'       => 'admission_request',
            'related_id' => $emergencyId
        ], true);
    }
}

echo json_encode(['success' => true, 'notified' => $notified, 'patient' => $pName]);
