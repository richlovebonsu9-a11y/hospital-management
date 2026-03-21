<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_staff.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'pharmacist') { header('Location: /dashboard'); exit; }

$prescriptionId = $_POST['prescription_id'] ?? '';
$batch = $_POST['batch_number'] ?? '';
$notes = $_POST['notes'] ?? '';

if (!$prescriptionId) {
    header('Location: /dashboard_staff.php?error=no_prescription'); exit;
}

$sb = new Supabase();
$res = $sb->request('PATCH', '/rest/v1/prescriptions?id=eq.' . $prescriptionId, [
    'status' => 'dispensed',
    'batch_number' => $batch,
    'dispense_notes' => $notes,
    'dispensed_by' => $u['id']
], true);

header('Location: /dashboard_staff.php?dispensed=1');
exit;
