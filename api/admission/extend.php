<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if (!in_array($role, ['admin', 'doctor', 'nurse'])) { exit; }

$admissionId = $_POST['admission_id'] ?? '';
$extraDays = (int)($_POST['extra_days'] ?? 0);

if (!$admissionId || $extraDays <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid data']);
    exit;
}

$sb = new Supabase();

// 1. Fetch current admission and ward details
$admRes = $sb->request('GET', '/rest/v1/admissions?id=eq.' . $admissionId . '&select=*,patient_id,ward_id,anticipated_days', null, true);
if ($admRes['status'] !== 200 || empty($admRes['data'])) {
    echo json_encode(['success' => false, 'error' => 'Admission not found']);
    exit;
}
$adm = $admRes['data'][0];
$patientId = $adm['patient_id'];
$wardId = $adm['ward_id'];
$oldDays = (int)$adm['anticipated_days'];

$wardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $wardId . '&select=ward_name,admission_fee', null, true);
if ($wardRes['status'] !== 200 || empty($wardRes['data'])) {
    echo json_encode(['success' => false, 'error' => 'Ward not found']);
    exit;
}
$ward = $wardRes['data'][0];
$dailyRate = (float)$ward['admission_fee'];

// 2. Update admission anticipated days
$newDays = $oldDays + $extraDays;
$updateRes = $sb->request('PATCH', '/rest/v1/admissions?id=eq.' . $admissionId, [
    'anticipated_days' => $newDays
], true);

if ($updateRes['status'] === 204 || $updateRes['status'] === 200) {
    // 3. Add Extension Fee to Invoice
    $invRes = $sb->request('GET', '/rest/v1/invoices?patient_id=eq.' . $patientId . '&status=eq.unpaid&order=created_at.desc&limit=1', null, true);
    if ($invRes['status'] === 200 && !empty($invRes['data'])) {
        $invoiceId = $invRes['data'][0]['id'];
        $currentTotal = (float)$invRes['data'][0]['total_amount'];
    } else {
        $newInv = $sb->request('POST', '/rest/v1/invoices', ['patient_id' => $patientId, 'total_amount' => 0, 'status' => 'unpaid'], true, ['Prefer' => 'return=representation']);
        $invoiceId = ($newInv['status'] === 201) ? $newInv['data'][0]['id'] : null;
        $currentTotal = 0;
    }

    if ($invoiceId) {
        $totalExtraFee = $dailyRate * $extraDays;

        // Check for NHIS
        $profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=ghana_card,nhis_membership_number', null, true);
        $pData = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
        $hasNHIS = (!empty($pData['ghana_card']) && !empty($pData['nhis_membership_number']));
        $chargedFee = $totalExtraFee * ($hasNHIS ? 0.5 : 1.0);

        $sb->request('POST', '/rest/v1/invoice_items', [
            'invoice_id' => $invoiceId,
            'description' => 'Admission Extension (' . $extraDays . ' days): ' . $ward['ward_name'] . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
            'quantity' => 1,
            'unit_price' => $totalExtraFee,
            'amount' => $chargedFee
        ], true);
        $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, ['total_amount' => $currentTotal + $chargedFee], true);
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update admission']);
}
