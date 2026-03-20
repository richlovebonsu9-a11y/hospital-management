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
        // Success - typically redirects to a confirmation page or login
        header('Location: /login?signup=success');
    } else {
        // Error
        $error = $result['data']['msg'] ?? 'Signup failed';
        header('Location: /signup?error=' . urlencode($error));
    }
}
