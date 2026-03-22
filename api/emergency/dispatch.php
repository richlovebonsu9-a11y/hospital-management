<?php
// API: Dispatch help for an emergency
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$actingUserId = $u['id'] ?? '';
$actingRole = $u['user_metadata']['role'] ?? '';
if (!in_array($actingRole, ['admin', 'doctor', 'nurse'])) { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit; 
}

$emergencyId = $_POST['emergency_id'] ?? '';
$dispatchType = $_POST['dispatch_type'] ?? 'none';
$dispatchNotes = $_POST['dispatch_notes'] ?? '';
$meds = $_POST['meds'] ?? []; // Array of medications

if (!$emergencyId || $dispatchType === 'none') {
    echo json_encode(['success' => false, 'error' => 'Missing dispatch type']);
    exit;
}

$sb = new Supabase();

// 0. Fetch Emergency Details & Patient Profile (include assigned_to)
$emergRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=*,reporter:reporter_id(id,name,role,ghana_card,nhis_membership_number)', null, true);
if ($emergRes['status'] !== 200 || empty($emergRes['data'])) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}
$emergency = $emergRes['data'][0];
$patientId = $emergency['reporter_id'];
$pData = $emergency['reporter'] ?? [];
$assignedToId = $emergency['assigned_to'] ?? null; // Staff assigned to this emergency

// Apply NHIS Logic (50% discount)
$hasNHIS = (!empty($pData['ghana_card']) && !empty($pData['nhis_membership_number']));
$discountMultiplier = $hasNHIS ? 0.5 : 1.0;

// 1. Billing: Find or create unpaid invoice
$invoiceId = null;
$invRes = $sb->request('GET', '/rest/v1/invoices?patient_id=eq.' . $patientId . '&status=eq.unpaid&order=created_at.desc&limit=1', null, true);
if ($invRes['status'] === 200 && !empty($invRes['data'])) {
    $invoiceId = $invRes['data'][0]['id'];
    $currentInvoiceTotal = (float)$invRes['data'][0]['total_amount'];
} else {
    $newInv = $sb->request('POST', '/rest/v1/invoices', [
        'patient_id' => $patientId, 'total_amount' => 0, 'status' => 'unpaid'
    ], true, ['Prefer' => 'return=representation']);
    $invoiceId = ($newInv['status'] === 201 && !empty($newInv['data'])) ? $newInv['data'][0]['id'] : null;
    $currentInvoiceTotal = 0;
}

// 2. Process Medications
$medSummaryArray = [];
$addedBillTotal = 0;

if (is_array($meds) && !empty($meds)) {
    foreach ($meds as $m) {
        $mDrugId = $m['drug_id'] ?? '';
        $mDosage = $m['dosage'] ?? '';
        $mFreq = $m['frequency'] ?? '';
        $mDuration = $m['duration'] ?? '';
        $mQty = (int)($m['quantity'] ?? 1);
        
        if ($mDrugId) {
            $drugRes = $sb->request('GET', '/rest/v1/drug_inventory?id=eq.' . $mDrugId . '&select=drug_name,unit_price,stock_count', null, true);
            if ($drugRes['status'] === 200 && !empty($drugRes['data'])) {
                $drug = $drugRes['data'][0];
                $mMedName = $drug['drug_name'];
                $mUnitPrice = (float)($drug['unit_price'] ?? 0);
                
                // Save formal Prescription
                $sb->request('POST', '/rest/v1/prescriptions', [
                    'emergency_id' => $emergencyId,
                    'patient_id' => $patientId,
                    'drug_id' => $mDrugId,
                    'medication_name' => $mMedName,
                    'dosage' => $mDosage,
                    'frequency' => $mFreq,
                    'duration' => $mDuration,
                    'quantity' => $mQty,
                    'status' => 'pending' // Pharmacist needs to dispense
                ], true);

                // Add to Invoice
                if ($invoiceId && $mUnitPrice > 0) {
                    $chargedAmt = ($mUnitPrice * $mQty) * $discountMultiplier;
                    $sb->request('POST', '/rest/v1/invoice_items', [
                        'invoice_id' => $invoiceId,
                        'description' => 'Emergency Med: ' . $mMedName . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
                        'quantity' => $mQty,
                        'unit_price' => $mUnitPrice,
                        'amount' => $chargedAmt
                    ], true);
                    $addedBillTotal += $chargedAmt;
                }

                // Decrement Stock
                $newStock = (int)$drug['stock_count'] - $mQty;
                $sb->request('PATCH', '/rest/v1/drug_inventory?id=eq.' . $mDrugId, ['stock_count' => $newStock], true);
                
                $medSummaryArray[] = "{$mMedName} ({$mDosage})";
            }
        }
    }
}

// 3. Add Hospital Emergency Fee (Flat Fee: 150 GHS)
if ($invoiceId) {
    $baseFee = 150.00;
    $chargedFee = $baseFee * $discountMultiplier;
    $sb->request('POST', '/rest/v1/invoice_items', [
        'invoice_id' => $invoiceId,
        'description' => 'Hospital Emergency Response Fee' . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
        'quantity' => 1,
        'unit_price' => $baseFee,
        'amount' => $chargedFee
    ], true);
    $addedBillTotal += $chargedFee;
}

// Update Invoice Total
if ($invoiceId && $addedBillTotal > 0) {
    $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, ['total_amount' => $currentInvoiceTotal + $addedBillTotal], true);
}

// 4. Update Emergency Record (track who handled it)
$medicationNotes = implode(", ", $medSummaryArray) ?: 'None prescribed';
$updateData = [
    'dispatch_type' => $dispatchType,
    'dispatch_notes' => $dispatchNotes,
    'medication_notes' => $medicationNotes,
    'status' => 'dispatched',
    'responded_at' => date('c'),
    'handled_by' => $actingUserId  // track who actually handled it
];

$updRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, $updateData, true);

if ($updRes['status'] === 204 || $updRes['status'] === 200) {
    $typeLabel = ucfirst($dispatchType);
    $shortId = '#' . strtoupper(substr($emergencyId, 0, 5));
    $actingName = $u['user_metadata']['name'] ?? ucfirst($actingRole);

    // 5a. Notify patient
    $sb->request('POST', '/rest/v1/notifications', [
        'user_id' => $patientId,
        'message' => "🚑 Emergency {$shortId} Update: A {$typeLabel} has been dispatched. Check your billing for emergency fees.",
        'type' => 'emergency_update',
        'related_id' => $emergencyId
    ], true);

    // 5b. Cross-notify: If admin handled it, notify the assigned staff. If staff handled it, notify all admins.
    if ($actingRole === 'admin' && $assignedToId && $assignedToId !== $actingUserId) {
        // Admin handled it — tell the assigned staff they can clear it
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $assignedToId,
            'message' => "✅ Emergency {$shortId} has been handled by Admin {$actingName}. You can now clear this task from your queue.",
            'type' => 'emergency_handled_by_admin',
            'related_id' => $emergencyId
        ], true);
    } elseif ($actingRole !== 'admin') {
        // Staff handled it — notify all admins
        $adminsRes = $sb->request('GET', '/rest/v1/profiles?role=eq.admin&select=id', null, true);
        if ($adminsRes['status'] === 200 && !empty($adminsRes['data'])) {
            foreach ($adminsRes['data'] as $admin) {
                $sb->request('POST', '/rest/v1/notifications', [
                    'user_id' => $admin['id'],
                    'message' => "✅ Emergency {$shortId} has been handled by {$actingName} ({$actingRole}). You can clear it from the queue.",
                    'type' => 'emergency_handled_by_staff',
                    'related_id' => $emergencyId
                ], true);
            }
        }
    }

    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to finalize dispatch update']);
}

