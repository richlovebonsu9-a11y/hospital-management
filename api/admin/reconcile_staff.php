<?php
// Reconciliation script to sync Auth users into the Profiles table
require_once __DIR__ . '/../../src/lib/Supabase.php';

use App\Lib\Supabase;

header('Content-Type: application/json');

// Authentication Check: Only Admin
if (isset($_COOKIE['sb_user'])) {
    $user = json_decode($_COOKIE['sb_user'], true);
} else {
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

if (($user['user_metadata']['role'] ?? '') !== 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$supabase = new Supabase();
$reconciledCount = 0;
$errors = [];

// 1. Fetch all users from Supabase Auth
$response = $supabase->request('GET', '/auth/v1/admin/users', null, true);

if ($response['status'] !== 200) {
    echo json_encode(['error' => 'Failed to fetch users from Auth', 'details' => $response['data']]);
    exit;
}

$users = $response['data']['users'] ?? [];

foreach ($users as $u) {
    $uid = $u['id'];
    $meta = $u['user_metadata'] ?? [];
    $role = $meta['role'] ?? 'patient';
    $name = $meta['name'] ?? 'Unknown User';
    $dept = $meta['department'] ?? 'General OPD';

    // 2. Check if profile exists
    $profileResp = $supabase->request('GET', '/rest/v1/profiles?id=eq.' . $uid);
    
    if ($profileResp['status'] === 200 && empty($profileResp['data'])) {
        // 3. Profile missing! Create it.
        $createResp = $supabase->request('POST', '/rest/v1/profiles', [
            'id' => $uid,
            'name' => $name,
            'role' => $role,
            'department' => $dept
        ], true);

        if ($createResp['status'] >= 200 && $createResp['status'] < 300) {
            $reconciledCount++;
        } else {
            $errors[] = "Failed to create profile for $uid: " . json_encode($createResp['data']);
        }
    }
}

echo json_encode([
    'success' => true,
    'reconciled_count' => $reconciledCount,
    'total_checked' => count($users),
    'errors' => $errors
]);
