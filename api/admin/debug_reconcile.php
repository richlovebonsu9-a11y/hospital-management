<?php
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');
$supabase = new Supabase();

// 1. Fetch some profiles to see what's in there
$profiles = $supabase->request('GET', '/rest/v1/profiles?select=*&limit=10');

// 2. Fetch some auth users to see their metadata structure
$authUsers = $supabase->request('GET', '/auth/v1/admin/users', null, true);

echo json_encode([
    'profiles_sample' => $profiles['data'] ?? [],
    'auth_users_sample' => array_slice($authUsers['data']['users'] ?? [], 0, 5),
    'profiles_status' => $profiles['status'],
    'auth_status' => $authUsers['status']
]);
