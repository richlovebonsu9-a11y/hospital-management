<?php
// API: Get Available Beds for a Ward
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if (!isset($_COOKIE['sb_user'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$wardId = $_GET['ward_id'] ?? '';
if (!$wardId) {
    echo json_encode(['success' => false, 'error' => 'Missing ward_id']);
    exit;
}

$sb = new Supabase();
// Fetch available beds
$res = $sb->request('GET', '/rest/v1/beds?ward_id=eq.' . $wardId . '&status=eq.available&select=id,bed_number&order=bed_number.asc', null, true);

if ($res['status'] === 200) {
    echo json_encode(['success' => true, 'data' => $res['data']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error', 'debug' => $res]);
}
