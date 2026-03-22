<?php
// Temporary debug endpoint — DELETE after debugging
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');
$sb = new Supabase();

// 1. Check all prescriptions
$allRes = $sb->request('GET', '/rest/v1/prescriptions?select=*&order=created_at.desc&limit=10', null, true);

// 2. Check only pending
$pendingRes = $sb->request('GET', '/rest/v1/prescriptions?status=eq.pending&select=*&order=created_at.asc', null, true);

// 3. Check table columns by fetching one row
$oneRow = $sb->request('GET', '/rest/v1/prescriptions?limit=1&select=*', null, true);

echo json_encode([
    'all_prescriptions' => [
        'status_code' => $allRes['status'],
        'count' => is_array($allRes['data'] ?? null) ? count($allRes['data']) : 'not_array',
        'data' => $allRes['data'] ?? $allRes['error'] ?? 'empty',
    ],
    'pending_prescriptions' => [
        'status_code' => $pendingRes['status'],
        'count' => is_array($pendingRes['data'] ?? null) ? count($pendingRes['data']) : 'not_array',
        'data' => $pendingRes['data'] ?? $pendingRes['error'] ?? 'empty',
    ],
    'sample_row_columns' => ($oneRow['status'] === 200 && !empty($oneRow['data']))
        ? array_keys($oneRow['data'][0])
        : ($oneRow['data'] ?? $oneRow['error'] ?? 'no_data'),
], JSON_PRETTY_PRINT);
