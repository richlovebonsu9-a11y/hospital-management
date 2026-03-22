<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_staff.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'technician') { header('Location: /dashboard'); exit; }

$requestId = trim($_POST['request_id'] ?? '');
$result = trim($_POST['result_text'] ?? '');

$isAjax = !empty($_POST['is_ajax']);

if (!$requestId || !$result) {
    if ($isAjax) { echo json_encode(['success' => false, 'error' => 'missing_result']); exit; }
    header('Location: /dashboard_staff.php?error=missing_result'); exit;
}

$sb = new Supabase();

$patchData = [
    'status' => 'completed',
    'result_text' => $result
];

$reqRes = $sb->request('GET', '/rest/v1/lab_requests?id=eq.' . $requestId . '&select=doctor_id,patient_id,requester_id,test_type', null, true);
$doctorId = ($reqRes['status'] === 200 && !empty($reqRes['data'])) ? $reqRes['data'][0]['doctor_id'] : null;
$patientId = ($reqRes['status'] === 200 && !empty($reqRes['data'])) ? $reqRes['data'][0]['patient_id'] : null;
$requesterId = ($reqRes['status'] === 200 && !empty($reqRes['data'])) ? $reqRes['data'][0]['requester_id'] : null;
$testType = ($reqRes['status'] === 200 && !empty($reqRes['data'])) ? $reqRes['data'][0]['test_type'] : '';

$res = $sb->request('PATCH', '/rest/v1/lab_requests?id=eq.' . $requestId, $patchData, true);

if ($res['status'] >= 200 && $res['status'] < 300) {
    // Phase 38: Auto-update Blood Group in Profile
    if ($testType === 'Blood Group test' && $patientId) {
        // Try to extract blood group (e.g. O+, A, B-, AB)
        preg_match('/(A|B|AB|O)[+-]?/i', $result, $matches);
        if (!empty($matches[0])) {
            $extractedBG = strtoupper($matches[0]);
            $sb->request('PATCH', '/rest/v1/profiles?id=eq.' . $patientId, ['blood_group' => $extractedBG], true);
        }
    }

    $targetNotificationId = $requesterId ?: $doctorId;
    
    if ($targetNotificationId) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $targetNotificationId,
            'message' => "Lab results for Patient " . substr($patientId, 0, 8) . " have been processed and are ready for review."
        ], true);
    }
    
    if ($isAjax) { echo json_encode(['success' => true]); exit; }
    header('Location: /dashboard_staff.php?result_submitted=1');
} else {
    // Inject visible error tag for debugging
    if ($isAjax) { echo json_encode(['success' => false, 'error' => 'patch_failed', 'code' => $res['status']]); exit; }
    header('Location: /dashboard_staff.php?error=patch_failed&code=' . $res['status']);
}
exit;
