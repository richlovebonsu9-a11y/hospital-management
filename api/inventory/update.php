<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_staff.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'pharmacist') { header('Location: /dashboard'); exit; }

$action = $_POST['action'] ?? 'update';
$drugId = $_POST['id'] ?? '';
$name = trim($_POST['drug_name'] ?? '');
$stock = (int)($_POST['stock_count'] ?? 0);
$price = (float)($_POST['unit_price'] ?? 0);
$category = trim($_POST['category'] ?? 'General');

$sb = new Supabase();

if ($action === 'delete' && $drugId) {
    $sb->request('DELETE', '/rest/v1/drug_inventory?id=eq.' . $drugId, null, true);
} elseif ($action === 'add') {
    $sb->request('POST', '/rest/v1/drug_inventory', [
        'drug_name' => $name,
        'stock_count' => $stock,
        'unit_price' => $price,
        'category' => $category,
        'updated_at' => date('Y-m-d H:i:s')
    ], true);
} else {
    // Update existing
    $sb->request('PATCH', '/rest/v1/drug_inventory?id=eq.' . $drugId, [
        'drug_name' => $name,
        'stock_count' => $stock,
        'unit_price' => $price,
        'category' => $category,
        'updated_at' => date('Y-m-d H:i:s')
    ], true);
}

header('Location: /dashboard_staff.php?inventory_updated=1#section-inventory');
exit;
?>
