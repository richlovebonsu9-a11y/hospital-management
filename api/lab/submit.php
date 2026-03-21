<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_staff.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'technician') { header('Location: /dashboard'); exit; }

$requestId = $_POST['request_id'] ?? '';
$result = $_POST['result_text'] ?? '';

if (!$requestId || !$result) {
    header('Location: /dashboard_staff.php?error=missing_result'); exit;
}

$sb = new Supabase();
$res = $sb->request('PATCH', '/rest/v1/lab_requests?id=eq.' . $requestId, [
    'status' => 'completed',
    'result_text' => $result,
    'completed_by' => $u['id']
], true);

header('Location: /dashboard_staff.php?result_submitted=1');
exit;
