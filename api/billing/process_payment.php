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
// In a real app, this would integrate with Paystack/Flutterwave/etc.
// For this system, we mark it as paid.

$res = $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, [
    'status' => 'paid',
    'payment_method' => $method,
    'paid_at' => date('Y-m-d H:i:s')
], true);

if ($res['status'] === 204 || $res['status'] === 200) {
    // Notify the patient
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => $u['id'],
        'message' => "Payment successful! Your invoice #".substr($invoiceId, 0, 8)." has been marked as PAID via " . strtoupper($method) . "."
    ], true);

    header('Location: /dashboard_patient.php?success=payment_complete');
} else {
    header('Location: /dashboard_patient.php?error=payment_failed');
}
exit;
