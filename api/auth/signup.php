<?php
// Signup handler
require_once __DIR__ . '/../../src/lib/Supabase.php';

use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $ghana_post_gps = $_POST['ghana_post_gps'] ?? '';
    $department = $_POST['department'] ?? 'General OPD';

    // Role validation: Only Patient or Guardian allowed via public signup
    if (!in_array($role, ['patient', 'guardian'])) {
        header('Location: /signup?error=' . urlencode('Invalid role. Staff accounts must be created by an Admin.'));
        exit;
    }

    $supabase = new Supabase();
    $result = $supabase->auth()->signUp($email, $password, [
        'name' => $name,
        'role' => $role,
        'phone' => $phone,
        'ghana_post_gps' => $ghana_post_gps,
        'department' => $department
    ]);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        // Create a profile row immediately so FK constraints work (e.g. appointments.patient_id)
        $newUserId = $result['data']['user']['id'] ?? null;
        if ($newUserId) {
            $supabase->request('POST', '/rest/v1/profiles', [
                'id'             => $newUserId,
                'name'           => $name,
                'role'           => $role,
                'phone'          => $phone,
                'ghana_post_gps' => $ghana_post_gps ?: 'N/A',
                'department'     => $department
            ], true); // use service key so RLS doesn't block the insert
        }

        // Automatically sign in
        $signInResult = $supabase->auth()->signIn($email, $password);
        
        if ($signInResult['status'] === 200) {
            $tokenData = $signInResult['data'];
            
            // Protocol aware secure flag
            $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
            
            setcookie('sb_user', json_encode($tokenData['user']), time() + 86400, '/', '', $isSecure, true);
            setcookie('sb_token', $tokenData['access_token'], time() + 86400, '/', '', $isSecure, true);
            
            $userRole = $tokenData['user']['user_metadata']['role'] ?? 'patient';
            header('Location: /dashboard?role=' . $userRole);
            exit;
        } else {
            header('Location: /login?signup=success&signin=failed');
            exit;
        }
    } else {
        $error = $result['data']['msg'] ?? $result['data']['message'] ?? $result['data']['error'] ?? 'Signup failed';
        error_log("Signup failure: " . json_encode($result));
        header('Location: /signup?error=' . urlencode($error));
        exit;
    }
}
