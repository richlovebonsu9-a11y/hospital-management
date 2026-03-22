<?php
// API: Update an existing admission (Edit Ward/Bed)
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if ($role !== 'admin') { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit; 
}

$admissionId = $_POST['admission_id'] ?? '';
$newWardId = $_POST['ward_id'] ?? '';
$newBedNumber = $_POST['bed_number'] ?? '';
$oldWardId = $_POST['old_ward_id'] ?? '';

if (!$admissionId || !$newWardId) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

$sb = new Supabase();

// 1. Update Admission Record
$updateData = [
    'ward_id' => $newWardId,
    'bed_number' => $newBedNumber
];

$updRes = $sb->request('PATCH', '/rest/v1/admissions?id=eq.' . $admissionId, $updateData, true);

if ($updRes['status'] === 204 || $updRes['status'] === 200) {
    // 2. Adjust Ward Occupancy if ward changed
    if ($oldWardId && $oldWardId !== $newWardId) {
        // Decrement old ward
        $oldWardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $oldWardId . '&select=occupied_beds', null, true);
        if ($oldWardRes['status'] === 200 && !empty($oldWardRes['data'])) {
            $oldOcc = max(0, (int)$oldWardRes['data'][0]['occupied_beds'] - 1);
            $sb->request('PATCH', '/rest/v1/wards?id=eq.' . $oldWardId, ['occupied_beds' => $oldOcc], true);
        }
        
        // Increment new ward
        $newWardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $newWardId . '&select=occupied_beds', null, true);
        if ($newWardRes['status'] === 200 && !empty($newWardRes['data'])) {
            $newOcc = (int)$newWardRes['data'][0]['occupied_beds'] + 1;
            $sb->request('PATCH', '/rest/v1/wards?id=eq.' . $newWardId, ['occupied_beds' => $newOcc], true);
        }
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update admission record']);
}
