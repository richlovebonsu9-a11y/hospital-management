<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /emergency'); exit; }

$sb = new Supabase();
$userId = null;
if (isset($_COOKIE['sb_user'])) {
    $u = json_decode($_COOKIE['sb_user'], true);
    $userId = $u['id'] ?? null;
}

$severity      = trim($_POST['severity'] ?? '');
$symptoms      = trim($_POST['symptoms'] ?? '');
$ghanaPostGps  = trim($_POST['ghana_post_gps'] ?? '');

if (!$severity || !$ghanaPostGps) {
    header('Location: /emergency?error=missing_fields'); exit;
}

$body = [
    'reporter_id'    => $userId,
    'ghana_post_gps' => $ghanaPostGps,
    'severity'       => $severity,
    'symptoms'       => $symptoms,
    'status'         => 'active',
];
$res = $sb->request('POST', '/rest/v1/emergencies', $body, false, ['Prefer' => 'return=representation']);
$emergencyId = $res['data'][0]['id'] ?? null;

// Notify admins (best-effort — insert notification row)
if ($emergencyId) {
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => null, // broadcast — admin queries unread
        'message' => "🚨 New emergency ({$severity}) reported at {$ghanaPostGps}. ID: {$emergencyId}",
    ]);
}

$redirect = $emergencyId ? "/emergency_tracking?id={$emergencyId}" : '/emergency?error=failed';
header('Location: ' . $redirect);
exit;
