<?php
// Emergency reporting handler
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';

use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $symptoms = $_POST['symptoms'] ?? '';
    $severity = $_POST['severity'] ?? 'medium';
    $ghana_post_gps = $_POST['ghana_post_gps'] ?? '';
    $reporter_id = $_SESSION['user']['id'] ?? null;

    $supabase = new Supabase();
    $result = $supabase->from('emergencies')->insert([
        'reporter_id' => $reporter_id,
        'symptoms' => $symptoms,
        'severity' => $severity,
        'ghana_post_gps' => $ghana_post_gps,
        'status' => 'pending'
    ]);

    if ($result['status'] >= 200 && $result['status'] < 300) {
        // Success - redirect to tracking page
        header('Location: /emergency_tracking.php?id=' . ($result['data'][0]['id'] ?? ''));
    } else {
        $error = $result['data']['message'] ?? 'Failed to report emergency';
        header('Location: /emergency.php?error=' . urlencode($error));
    }
}
