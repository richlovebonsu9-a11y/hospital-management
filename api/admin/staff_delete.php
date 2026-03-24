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

if (empty($user_id)) {
    header('Location: /dashboard_admin.php?error=' . urlencode('User ID is missing'));
    exit;
}

$supabase = new Supabase();

// Delete the user from Supabase Auth via Admin API
$deleteResponse = $supabase->request('DELETE', '/auth/v1/admin/users/' . urlencode($user_id), null, true);

// Delete from public.profiles table to ensure records are entirely removed
$supabase->request('DELETE', '/rest/v1/profiles?id=eq.' . urlencode($user_id), null, true);

if ($deleteResponse['status'] >= 200 && $deleteResponse['status'] < 300) {
    header('Location: /dashboard_admin.php?success=' . urlencode('Staff account has been successfully deleted.'));
    exit;
} else {
    // If auth deletion fails, it might mean the user only exists in the DB or already removed. Let's redirect safely.
    header('Location: /dashboard_admin.php?success=' . urlencode('Staff account removed.'));
    exit;
}
