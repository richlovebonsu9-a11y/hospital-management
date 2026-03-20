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
        $_SESSION['user'] = $tokenData['user'];
        $_SESSION['access_token'] = $tokenData['access_token'];
        
        // Redirect based on role
        $role = $tokenData['user']['user_metadata']['role'] ?? 'patient';
        header('Location: /dashboard?role=' . $role);
    } else {
        $error = $result['data']['error_description'] ?? 'Login failed';
        header('Location: /login?error=' . urlencode($error));
    }
}
