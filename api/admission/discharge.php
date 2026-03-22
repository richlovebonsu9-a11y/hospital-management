<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if (!in_array($role, ['admin', 'doctor', 'nurse'])) { exit; }

$admissionId = $_POST['admission_id'] ?? '';
$wardId = $_POST['ward_id'] ?? '';

if (!$admissionId || !$wardId) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

$sb = new Supabase();

// 1. Mark as discharged
$res = $sb->request('PATCH', '/rest/v1/admissions?id=eq.' . $admissionId, [
    'status' => 'discharged',
    'discharge_date' => date('Y-m-d H:i:s')
], true);

if ($res['status'] === 204 || $res['status'] === 200) {
    // 2. Decrement Ward Occupancy
    $wardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $wardId . '&select=occupied_beds', null, true);
    if ($wardRes['status'] === 200 && !empty($wardRes['data'])) {
        $newOcc = max(0, (int)$wardRes['data'][0]['occupied_beds'] - 1);
        $sb->request('PATCH', '/rest/v1/wards?id=eq.' . $wardId, ['occupied_beds' => $newOcc], true);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update admission status']);
}
