<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

if (!isset($_COOKIE['sb_user'])) {
    echo json_encode(['error' => 'Not authenticated']); exit;
}

$invoiceId = $_GET['id'] ?? '';
if (!$invoiceId) {
    echo json_encode(['error' => 'Invoice ID required']); exit;
}

$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/invoice_items?invoice_id=eq.' . $invoiceId . '&select=*');

if ($res['status'] === 200) {
    echo json_encode(['items' => $res['data']]);
} else {
    echo json_encode(['error' => 'Failed to fetch details', 'details' => $res]);
}
