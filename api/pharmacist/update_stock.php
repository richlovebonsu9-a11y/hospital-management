<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['user_metadata']['role'] ?? '') !== 'pharmacist') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$sb = new Supabase();
$drugId = $_POST['id'] ?? '';
$newStock = $_POST['stock_count'] ?? '';

if (empty($drugId) || $newStock === '') {
    echo json_encode(['success' => false, 'error' => 'Missing drug ID or stock count']);
    exit;
}

$updateRes = $sb->request('PATCH', '/rest/v1/drug_inventory?id=eq.' . $drugId, [
    'stock_count' => (int)$newStock
], true);

if ($updateRes['status'] >= 200 && $updateRes['status'] < 300) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update inventory: ' . json_encode($updateRes)]);
}
