<?php
// Enhanced Reconciliation script to sync Auth users into the Profiles table
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
$skippedCount = 0;
$logs = [];

// 1. Fetch all users from Supabase Auth
$response = $supabase->request('GET', '/auth/v1/admin/users', null, true);

if ($response['status'] !== 200) {
    echo json_encode(['error' => 'Failed to fetch users from Auth', 'details' => $response['data']]);
    exit;
}

$users = $response['data']['users'] ?? [];

foreach ($users as $u) {
    $uid = $u['id'];
    $email = $u['email'] ?? 'No Email';
    $meta = $u['user_metadata'] ?? [];
    $role = strtolower($meta['role'] ?? 'patient');
    $name = $meta['name'] ?? 'Unknown User';
    $dept = $meta['department'] ?? 'General OPD';

    // We only care about reconciling STAFF
    if (in_array($role, ['doctor', 'nurse', 'pharmacist', 'technician', 'admin'])) {
        // Use UPSERT (POST with Prefer: resolution=merge-duplicates or simply check and update)
        // For simplicity with our current client, we'll check existence or just try to insert/update.
        
        $profilePayload = [
            'id' => $uid,
            'name' => $name,
            'role' => $role,
            'department' => $dept,
            'ghana_post_gps' => $meta['ghana_post_gps'] ?? 'N/A'  // Required NOT NULL - use saved value or placeholder
        ];

        // Let's use internal UPSERT logic: Try to POST, if fails due to conflict, PATCH.
        $createResp = $supabase->request('POST', '/rest/v1/profiles', $profilePayload, true);

        if ($createResp['status'] >= 200 && $createResp['status'] < 300) {
            $reconciledCount++;
            $logs[] = "Successfully created profile for $name ($email) as $role in $dept";
        } else if ($createResp['status'] == 409) {
            // Already exists, let's UPDATE it to ensure role/dept is correct
            $updateResp = $supabase->request('PATCH', '/rest/v1/profiles?id=eq.' . $uid, $profilePayload, true);
            if ($updateResp['status'] >= 200 && $updateResp['status'] < 300) {
                 $reconciledCount++;
                 $logs[] = "Successfully updated legacy profile for $name ($email)";
            } else {
                 $logs[] = "Failed to update existing profile for $email: " . json_encode($updateResp['data']);
            }
        } else {
            $logs[] = "Failed to reconcile $email: Status " . $createResp['status'] . " - " . json_encode($createResp['data']);
        }
    } else {
        $skippedCount++;
        $logs[] = "Skipping $email (Role: $role)";
    }
}

echo json_encode([
    'success' => true,
    'reconciled_count' => $reconciledCount,
    'skipped_count' => $skippedCount,
    'total_checked' => count($users),
    'logs' => $logs
]);
