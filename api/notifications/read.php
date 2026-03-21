<?php
// API: Mark Notification as Read
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!isset($_COOKIE['sb_user'])) { http_response_code(401); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$id = $_GET['id'] ?? '';

if ($id) {
    $sb = new Supabase();
    // Use user ID to ensure they can only mark their own notifications
    $sb->request('PATCH', '/rest/v1/notifications?id=eq.' . $id . '&user_id=eq.' . $u['id'], [
        'is_read' => true
    ], true);
}
http_response_code(200);
echo json_encode(['success' => true]);
exit;
