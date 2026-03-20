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

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';
$role = trim($_POST['role'] ?? '');
$department = trim($_POST['department'] ?? '');

if (empty($name) || empty($email) || empty($password) || empty($role)) {
    header('Location: /dashboard_admin.php?error=Missing+required+fields');
    exit;
}

$supabase = new Supabase();
$result = $supabase->auth()->signUp($email, $password, [
    'name' => $name,
    'role' => $role,
    'department' => $department
]);

if ($result['status'] >= 200 && $result['status'] < 300) {
    header('Location: /dashboard_admin.php?staff_added=1');
    exit;
} else {
    $errorMsg = urlencode($result['data']['msg'] ?? $result['data']['message'] ?? 'Failed to add staff');
    header('Location: /dashboard_admin.php?error=' . $errorMsg);
    exit;
}
