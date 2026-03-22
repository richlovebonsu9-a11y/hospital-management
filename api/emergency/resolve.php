<?php
// API: Resolve/Complete an emergency
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';

$emergencyId = $_POST['emergency_id'] ?? '';
$resolutionNotes = $_POST['resolution_notes'] ?? '';

if (!$emergencyId) {
    echo json_encode(['success' => false, 'error' => 'Missing ID']);
    exit;
}

$sb = new Supabase();

// 1. Update Emergency Record
$updateData = [
    'status' => 'resolved',
    'dispatch_notes' => $resolutionNotes, // Append or overwrite with final resolution
    'resolved_at' => date('c')
];

$updRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, $updateData, true);

if ($updRes['status'] === 204 || $updRes['status'] === 200) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to resolve emergency']);
}
