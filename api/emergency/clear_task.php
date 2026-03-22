<?php
// API: Clear an emergency task from a user's view (acknowledgment, not resolution)
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }
if (!isset($_COOKIE['sb_user'])) { echo json_encode(['success' => false, 'error' => 'Not authenticated']); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$userId = $u['id'] ?? '';

$notificationId = $_POST['notification_id'] ?? '';

if (!$notificationId) {
    echo json_encode(['success' => false, 'error' => 'Missing notification ID']);
    exit;
}

$sb = new Supabase();

// Mark the specific notification as read/cleared
$res = $sb->request('PATCH', '/rest/v1/notifications?id=eq.' . $notificationId . '&user_id=eq.' . $userId, [
    'is_read' => true
], true);

if ($res['status'] === 204 || $res['status'] === 200) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to clear task']);
}
