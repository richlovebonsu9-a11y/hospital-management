<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit('Method not allowed'); }
if (!isset($_COOKIE['sb_user'])) { exit('Not authenticated'); }

$u = json_decode($_COOKIE['sb_user'], true);
$userId = $u['id'];
$wardId = $_POST['ward_id'] ?? '';
$consultId = $_POST['consultation_id'] ?? '';

if (!$wardId || !$consultId) { 
    header('Location: /dashboard_patient.php?error=missing_data'); exit; 
}

$sb = new Supabase();

// 1. Check ward availability
$wardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $wardId . '&select=*', null, true);
if ($wardRes['status'] !== 200 || empty($wardRes['data'])) {
    header('Location: /dashboard_patient.php?error=ward_not_found'); exit;
}
$ward = $wardRes['data'][0];
if ($ward['occupied_beds'] >= $ward['total_beds']) {
    header('Location: /dashboard_patient.php?error=ward_full'); exit;
}

// 2. Atomic Update: Increment occupied_beds
$sb->request('PATCH', '/rest/v1/wards?id=eq.' . $wardId, [
    'occupied_beds' => $ward['occupied_beds'] + 1
], true);

// 3. Create Admission Record
$admissionData = [
    'patient_id' => $userId,
    'ward_id' => $wardId,
    'bed_number' => 'B-' . ($ward['occupied_beds'] + 1), // Pseudo assignment
    'status' => 'active'
];
$sb->request('POST', '/rest/v1/admissions', $admissionData, true);

// 4. Automated Billing for Bed Fee
// a. NHIS DISCOUNT CHECK
$profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $userId . '&select=ghana_card,nhis_membership_number', null, true);
$pData = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
$hasNHIS = (!empty($pData['ghana_card']) && !empty($pData['nhis_membership_number']));
$discountMultiplier = $hasNHIS ? 0.5 : 1.0;

// b. Find or create invoice
$invRes = $sb->request('GET', '/rest/v1/invoices?patient_id=eq.' . $userId . '&status=eq.unpaid&limit=1', null, true);
$isNewInvoice = false;
if ($invRes['status'] === 200 && !empty($invRes['data'])) {
    $invoiceId = $invRes['data'][0]['id'];
    $currentTotal = (float)$invRes['data'][0]['total_amount'];
} else {
    $newInv = $sb->request('POST', '/rest/v1/invoices', [
        'patient_id' => $userId, 
        'total_amount' => 0, 
        'status' => 'unpaid'
    ], true, ['Prefer' => 'return=representation']);
    
    if ($newInv['status'] === 201 && !empty($newInv['data'])) {
        $invoiceId = $newInv['data'][0]['id'];
        $currentTotal = 0;
        $isNewInvoice = true;
    }
}

if (isset($invoiceId)) {
    $addedTotal = 0;
    
    // c. Add Hospital Service Fee (₵50) if it's a new invoice
    if ($isNewInvoice) {
        $hospFeeBase = 50.00;
        $hospFeeCharged = $hospFeeBase * $discountMultiplier;
        $sb->request('POST', '/rest/v1/invoice_items', [
            'invoice_id' => $invoiceId,
            'description' => 'Hospital Service Fee (Standard)',
            'quantity' => 1,
            'unit_price' => $hospFeeBase,
            'amount' => $hospFeeCharged
        ], true);
        $addedTotal += $hospFeeCharged;
    }

    // d. Add Bed Fee item
    $baseFee = (float)($ward['admission_fee'] ?? 0);
    $chargedFee = $baseFee * $discountMultiplier;
    $sb->request('POST', '/rest/v1/invoice_items', [
        'invoice_id' => $invoiceId,
        'description' => 'Hospital Bed & Admission Fee (' . $ward['ward_name'] . ')' . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
        'quantity' => 1,
        'unit_price' => $baseFee,
        'amount' => $chargedFee
    ], true);
    $addedTotal += $chargedFee;

    // e. Update Invoice total
    $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, [
        'total_amount' => $currentTotal + $addedTotal
    ], true);
}

// 5. Mark notification as read if it exists
$sb->request('PATCH', '/rest/v1/notifications?user_id=eq.' . $userId . '&related_id=eq.' . $consultId . '&type=eq.admission_request', [
    'is_read' => true
], true);

header('Location: /dashboard_patient.php?admitted=1');
?>
