<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Method not allowed'); }
if (!isset($_COOKIE['sb_user'])) { exit('Not authenticated'); }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'admin' && ($u['user_metadata']['role'] ?? '') !== 'pharmacist') {
    exit('Unauthorized');
}

$action = $_POST['action'] ?? '';
$id = $_POST['id'] ?? '';
$name = $_POST['drug_name'] ?? '';
$stock = $_POST['stock_count'] ?? 0;
$price = $_POST['unit_price'] ?? 0;
$category = $_POST['category'] ?? 'General';

$sb = new Supabase();
$res = null;

if ($action === 'add') {
    $res = $sb->request('POST', '/rest/v1/drug_inventory', [
        'drug_name' => $name,
        'stock_count' => (int)$stock,
        'unit_price' => (float)$price,
        'category' => $category
    ], true);
} elseif ($action === 'update' && $id) {
    $res = $sb->request('PATCH', '/rest/v1/drug_inventory?id=eq.' . $id, [
        'drug_name' => $name,
        'stock_count' => (int)$stock,
        'unit_price' => (float)$price,
        'category' => $category,
        'last_updated' => date('c')
    ], true);
} elseif ($action === 'delete' && $id) {
    $res = $sb->request('DELETE', '/rest/v1/drug_inventory?id=eq.' . $id, null, true);
}

if ($res && ($res['status'] === 201 || $res['status'] === 204 || $res['status'] === 200)) {
    header('Location: ' . $_SERVER['HTTP_REFERER']);
} else {
    echo "Error processing request: ";
    print_r($res);
}
exit;
