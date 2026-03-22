<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_patient.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$prescriptionId = $_POST['prescription_id'] ?? '';

if (!$prescriptionId) {
    header('Location: /dashboard_patient.php?error=no_prescription'); exit;
}

$sb = new Supabase();
// Update prescription to be "ordered"
$res = $sb->request('PATCH', '/rest/v1/prescriptions?id=eq.' . $prescriptionId, [
    'is_ordered' => true
], true);

// Add a notification for the pharmacy department
$sb->request('POST', '/rest/v1/notifications', [
    'user_id' => null, // Global or department-level
    'message' => "New medication order received from Patient " . substr($u['id'], 0, 8),
    'type' => 'pharmacy_order'
], true);

header('Location: /dashboard_patient.php?ordered=1');
exit;
?>
