<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /dashboard_admin');
    exit;
}

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'admin') {
    die("Unauthorized");
}

$patient_id = $_POST['patient_id'] ?? '';
$guardian_id = $_POST['guardian_id'] ?? '';
$relationship = $_POST['relationship'] ?? '';
$is_primary = isset($_POST['is_primary']) ? true : false;

if (empty($patient_id) || empty($guardian_id)) {
    header('Location: /dashboard_admin?error=Missing+fields');
    exit;
}

$sb = new Supabase();
$data = [
    'patient_id' => $patient_id,
    'guardian_id' => $guardian_id,
    'relationship' => $relationship,
    'is_primary' => $is_primary
];

$res = $sb->request('POST', '/rest/v1/guardians', $data);

if ($res['status'] >= 200 && $res['status'] < 300) {
    // Log the action
    $sb->request('POST', '/rest/v1/audit_log', [
        'user_id' => $_SESSION['user']['id'],
        'action' => 'LINK_GUARDIAN',
        'details' => "Linked patient $patient_id to guardian $guardian_id ($relationship)"
    ]);
    header('Location: /dashboard_admin?success=Link+created');
} else {
    $error = urlencode($res['data']['message'] ?? 'Failed to link guardian');
    header('Location: /dashboard_admin?error=' . $error);
}
exit;
