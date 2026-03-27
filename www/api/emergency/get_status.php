<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

$emergencyId = $_GET['id'] ?? null;

if (!$emergencyId) {
    echo json_encode(['error' => 'Missing ID']);
    exit;
}

$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=status,assigned_to', null, true);

if ($res['status'] === 200 && !empty($res['data'])) {
    $e = $res['data'][0];
    echo json_encode([
        'status' => $e['status'],
        'responder' => $e['assigned_to'] ?? null
    ]);
} else {
    echo json_encode(['error' => 'Not found']);
}
