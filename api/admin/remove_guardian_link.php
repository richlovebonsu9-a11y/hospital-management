<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}
if (!isset($_COOKIE['sb_user'])) {
    http_response_code(401);
    exit('Not authenticated');
}

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

$data = json_decode(file_get_contents('php://input'), true);
$linkId = $data['link_id'] ?? '';

if (!$linkId) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Link ID is required']));
}

$sb = new Supabase();
$res = $sb->request('DELETE', '/rest/v1/patient_guardian_links?id=eq.' . $linkId, null, true);

if ($res && ($res['status'] === 204 || $res['status'] === 200)) {
    $sb->request('POST', '/rest/v1/audit_log', [
        'user_id' => $u['id'] ?? null,
        'user_role' => 'admin',
        'action' => 'UNLINK_GUARDIAN',
        'details' => "Removed guardian link $linkId",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    ]);
    echo json_encode(['success' => true]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to remove link.']);
}
exit;
