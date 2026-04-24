<?php
// Login handler
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';

use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $supabase = new Supabase();
    $result = $supabase->auth()->signIn($email, $password);

    if ($result['status'] === 200) {
        $tokenData = $result['data'];
        
        // Protocol aware secure flag
        $isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        
        // Use cookies instead of server-side sessions for Vercel Serverless
        setcookie('sb_user', json_encode($tokenData['user']), time() + 86400, '/', '', $isSecure, true);
        setcookie('sb_token', $tokenData['access_token'], time() + 86400, '/', '', $isSecure, true);
        
        $_SESSION['user'] = $tokenData['user'];
        $_SESSION['access_token'] = $tokenData['access_token'];
        
        // Redirect based on role
        $role = $tokenData['user']['user_metadata']['role'] ?? 'patient';
        header('Location: /dashboard?role=' . $role);
        exit;
    } else {
        $error = $result['data']['error_description'] ?? $result['data']['msg'] ?? $result['data']['message'] ?? $result['data']['error'] ?? 'Login failed';
        // Log more details to help the user if they check their Vercel logs
        error_log("Login failure: " . json_encode($result));
        header('Location: /login?error=' . urlencode($error));
        exit;
    }
}
