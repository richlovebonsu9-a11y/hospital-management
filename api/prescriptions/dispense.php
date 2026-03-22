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

$isAjax = !empty($_POST['is_ajax']);

if (!$prescriptionId) {
    if ($isAjax) { echo json_encode(['success' => false, 'error' => 'no_prescription']); exit; }
    header('Location: /dashboard_staff.php?error=no_prescription'); exit;
}

$sb = new Supabase();

// 1. Fetch Prescription Details
$preRes = $sb->request('GET', '/rest/v1/prescriptions?id=eq.' . $prescriptionId . '&select=*,patient:patient_id(id)', null, true);
if ($preRes['status'] !== 200 || empty($preRes['data'])) {
    if ($isAjax) { echo json_encode(['success' => false, 'error' => 'prescription_not_found']); exit; }
    header('Location: /dashboard_staff.php?error=prescription_not_found'); exit;
}
$prescription = $preRes['data'][0];
$patientId = $prescription['patient_id'] ?? $prescription['patient']['id'] ?? null;
$drugId = $prescription['drug_id'] ?? null;

// 2. Inventory & Billing Logic (If matched to an inventory item)
if ($drugId && $patientId) {
    $drugRes = $sb->request('GET', '/rest/v1/drug_inventory?id=eq.' . $drugId, null, true);
    if ($drugRes['status'] === 200 && !empty($drugRes['data'])) {
        $drug = $drugRes['data'][0];
        $basePrice = (float)($drug['unit_price'] ?? 0);
        $stock = (int)($drug['stock_count'] ?? 0);

        if ($stock > 0) {
            // A. Fetch the actual prescribed quantity (Fallback to 1 if missing)
            $qty = (int)($prescription['quantity'] ?? 1); 
            if ($qty > $stock) { $qty = $stock; } // Cap at available stock

            // A. Decrement Stock (always done on dispense)
            $sb->request('PATCH', '/rest/v1/drug_inventory?id=eq.' . $drugId, ['stock_count' => $stock - $qty], true);

            // B. Billing: only add invoice item if prescription was NOT from a consultation
            // (consultation-linked prescriptions are billed immediately in save.php to avoid double-charging)
            $wasConsultationPrescription = !empty($prescription['consultation_id']);

            if (!$wasConsultationPrescription) {
                // NHIS DISCOUNT CHECK
                $profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=ghana_card,nhis_membership_number', null, true);
                $pData = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
                $hasNHIS = (!empty($pData['ghana_card']) && !empty($pData['nhis_membership_number']));
                $discountMultiplier = $hasNHIS ? 0.5 : 1.0;
                $chargedPrice = ($basePrice * $qty) * $discountMultiplier;

                // Find or Create Unpaid Invoice
                $invRes = $sb->request('GET', '/rest/v1/invoices?patient_id=eq.' . $patientId . '&status=eq.unpaid&order=created_at.desc&limit=1', null, true);
                
                $isNewInvoice = false;
                if ($invRes['status'] === 200 && !empty($invRes['data'])) {
                    $invoiceId = $invRes['data'][0]['id'];
                    $currentTotal = (float)$invRes['data'][0]['total_amount'];
                } else {
                    $newInv = $sb->request('POST', '/rest/v1/invoices', [
                        'patient_id' => $patientId, 
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
                    
                    // Add Hospital Service Fee (₵50) if it's a brand new invoice
                    if ($isNewInvoice) {
                        $hospFeeBase = 50.00;
                        $hospFeeCharged = $hospFeeBase * $discountMultiplier;
                        $sb->request('POST', '/rest/v1/invoice_items', [
                            'invoice_id' => $invoiceId,
                            'description' => 'Hospital Service Fee (Standard)' . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
                            'quantity' => 1,
                            'unit_price' => $hospFeeBase,
                            'amount' => $hospFeeCharged
                        ], true);
                        $addedTotal += $hospFeeCharged;
                    }

                    // Add Medication Item
                    $sb->request('POST', '/rest/v1/invoice_items', [
                        'invoice_id' => $invoiceId,
                        'description' => 'Medication (Walk-in): ' . $drug['drug_name'] . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
                        'quantity' => $qty,
                        'unit_price' => $basePrice,
                        'amount' => $chargedPrice
                    ], true);
                    $addedTotal += $chargedPrice;

                    // Update Invoice Total
                    $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, ['total_amount' => $currentTotal + $addedTotal], true);
                }
            }
        } else {
            if ($isAjax) { echo json_encode(['success' => false, 'error' => 'out_of_stock']); exit; }
            header('Location: /dashboard_staff.php?error=out_of_stock'); exit;
        }
    }
}

// 3. Complete Prescription
$res = $sb->request('PATCH', '/rest/v1/prescriptions?id=eq.' . $prescriptionId, [
    'status' => 'dispensed',
    'batch_number' => $batch,
    'dispense_notes' => $notes,
    'dispensed_by' => $u['id'],
    'dispensed_at' => date('Y-m-d H:i:s')
], true);

if ($isAjax) {
    echo json_encode(['success' => true]);
    exit;
}

header('Location: /dashboard_staff.php?dispensed=1');
exit;
