<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';

use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard_admin.php');
    exit;
}

// Authentication Check: Only Admin
if (isset($_COOKIE['sb_user'])) {
    $user = json_decode($_COOKIE['sb_user'], true);
} else {
    header('Location: /login');
    exit;
}

if (($user['user_metadata']['role'] ?? '') !== 'admin') {
    die("Unauthorized access.");
}

$user_id = trim($_POST['user_id'] ?? '');
$name = trim($_POST['name'] ?? '');
$role = trim($_POST['role'] ?? '');
$department = trim($_POST['department'] ?? '');

if (empty($user_id) || empty($name) || empty($role)) {
    header('Location: /dashboard_admin.php?error=Missing+required+fields');
    exit;
}

$supabase = new Supabase();

// First fetch the existing user to get current metadata so we don't overwrite other fields
$getResponse = $supabase->request('GET', '/auth/v1/admin/users/' . urlencode($user_id), null, true);

if ($getResponse['status'] !== 200 || !isset($getResponse['data']['user_metadata'])) {
    header('Location: /dashboard_admin.php?error=Failed+to+fetch+user+details');
    exit;
}

$currentMetadata = $getResponse['data']['user_metadata'];
$currentMetadata['name'] = $name;
$currentMetadata['role'] = $role;
$currentMetadata['department'] = $department;

// Update the user metadata via Admin API
$updateResponse = $supabase->request('PUT', '/auth/v1/admin/users/' . urlencode($user_id), [
    'user_metadata' => $currentMetadata
], true);

// Sync with public.profiles table
$supabase->request('PATCH', '/rest/v1/profiles?id=eq.' . $user_id, [
    'name' => $name,
    'role' => $role,
    'department' => $department
], true);

if ($updateResponse['status'] >= 200 && $updateResponse['status'] < 300) {
    header('Location: /dashboard_admin.php?staff_edited=1');
    exit;
} else {
    $errorMsg = urlencode($updateResponse['data']['msg'] ?? $updateResponse['data']['message'] ?? 'Failed to update staff');
    header('Location: /dashboard_admin.php?error=' . $errorMsg);
    exit;
}
