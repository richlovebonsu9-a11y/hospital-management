<?php
// Admin-only staff creation handler
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

// 1. Verify Admin Auth (using cookie for session)
if (!isset($_COOKIE['sb_user'])) { 
    echo json_encode(['success' => false, 'error' => 'Not authenticated']); 
    exit; 
}
$user = json_decode($_COOKIE['sb_user'], true);
if (($user['user_metadata']['role'] ?? '') !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? '';
    $department = $_POST['department'] ?? 'General OPD';

    if (empty($email) || empty($password) || empty($name) || empty($role)) {
        echo json_encode(['success' => false, 'error' => 'All fields are required.']);
        exit;
    }

    $sb = new Supabase();
    
    // Create the user using Supabase Admin API (via service key)
    // This allows account creation without signing out the current admin session
    $result = $sb->adminAuth()->createUser($email, $password, [
        'name' => $name,
        'role' => $role,
        'department' => $department
    ]);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        $newUserId = $result['data']['id'] ?? null;
        if ($newUserId) {
            // Also create corresponding row in public.profiles for application use
            $sb->request('POST', '/rest/v1/profiles', [
                'id' => $newUserId,
                'name' => $name,
                'role' => $role,
                'department' => $department,
                'ghana_post_gps' => 'N/A' // Required by DB constraint
            ], true);
            
            $sb->request('POST', '/rest/v1/audit_log', [
                'user_id' => $user['id'] ?? null,
                'user_role' => 'admin',
                'action' => 'ADD_STAFF',
                'details' => "Added new $role ($name) to $department",
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
            ], true);
        }
        echo json_encode(['success' => true]);
    } else {
        $msg = $result['data']['msg'] ?? $result['data']['message'] ?? 'Failed to create staff account';
        echo json_encode(['success' => false, 'error' => $msg]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
}
