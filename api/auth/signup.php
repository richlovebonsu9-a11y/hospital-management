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

    $supabase = new Supabase();
    $result = $supabase->auth()->signUp($email, $password, [
        'name' => $name,
        'role' => $role,
        'phone' => $phone,
        'ghana_post_gps' => $ghana_post_gps
    ]);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        // Automatically sign in
        $signInResult = $supabase->auth()->signIn($email, $password);
        
        if ($signInResult['status'] === 200) {
            $tokenData = $signInResult['data'];
            
            // Use cookies instead of server-side sessions for Vercel Serverless
            setcookie('sb_user', json_encode($tokenData['user']), time() + 86400, '/', '', true, true);
            setcookie('sb_token', $tokenData['access_token'], time() + 86400, '/', '', true, true);
            
            // Note: $_SESSION might not be available or persistent in serverless environments without specific configuration.
            // The cookie approach is generally preferred for Vercel Serverless.
            // $_SESSION['user'] = $tokenData['user'];
            // $_SESSION['access_token'] = $tokenData['access_token'];
            
            $userRole = $tokenData['user']['user_metadata']['role'] ?? 'patient';
            header('Location: /dashboard?role=' . $userRole);
            exit;
        } else {
            // If sign-in fails after successful signup, redirect to login with a generic success message
            // or an error indicating sign-in failure.
            header('Location: /login?signup=success&signin=failed');
            exit;
        }
    } else {
        // Error during signup
        $error = $result['data']['msg'] ?? 'Signup failed';
        header('Location: /signup?error=' . urlencode($error));
        exit;
    }
}
