<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Method not allowed'); }
if (!isset($_COOKIE['sb_user'])) { exit('Not authenticated'); }

$invoiceId = $_POST['invoice_id'] ?? '';
$method = $_POST['payment_method'] ?? '';

if (!$invoiceId || !$method) {
    header('Location: /dashboard_patient.php?error=invalid_payment'); exit;
}

$sb = new Supabase();
$u = json_decode($_COOKIE['sb_user'], true);

// 1. Process "Payment" (Simulated)
$updateData = [
    'status' => 'paid',
    'payment_method' => $method,
    'paid_at' => date('c') 
];

$res = $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, $updateData, true);

// FALLBACK: If the full patch fails, try patching 'status' AND storing metadata in 'nhis_note'
if ($res['status'] !== 204 && $res['status'] !== 200) {
    $meta = json_encode([
        'method' => $method,
        'paid_at' => date('Y-m-d H:i:s'),
        'source' => 'web_portal'
    ]);
    $res = $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, [
        'status' => 'paid',
        'nhis_note' => 'PAYMENT_META:' . $meta
    ], true);
}

if ($res['status'] === 204 || $res['status'] === 200) {
    // Notify the patient
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => $u['id'],
        'message' => "Payment successful! Your invoice has been cleared via " . strtoupper($method) . "."
    ], true);

    header('Location: /dashboard_patient.php?success=payment_complete');
} else {
    // If even the fallback fails, report the error detail if possible
    $errorMsg = 'payment_failed';
    if (isset($res['data']['message'])) {
        $errorMsg = urlencode($res['data']['message']);
    }
    header('Location: /dashboard_patient.php?error=' . $errorMsg);
}
exit;
