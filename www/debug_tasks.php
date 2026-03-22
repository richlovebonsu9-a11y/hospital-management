<?php
require_once __DIR__ . '/src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/prescriptions?status=eq.pending&select=*,patient:patient_id(name)', null, true);

header('Content-Type: application/json');
echo json_encode([
    'status' => $res['status'],
    'count' => count($res['data'] ?? []),
    'data' => $res['data']
], JSON_PRETTY_PRINT);
?>
