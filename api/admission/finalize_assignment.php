<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { exit; }
if (!isset($_COOKIE['sb_user'])) { exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if (!in_array($role, ['admin', 'doctor', 'nurse'])) { exit; }

$patientId = $_POST['patient_id'] ?? '';
$wardId = $_POST['ward_id'] ?? '';
$bedNumber = $_POST['bed_number'] ?? '';

if (!$patientId || !$wardId) {
    echo json_encode(['success' => false, 'error' => 'Missing data']);
    exit;
}

$sb = new Supabase();

// 1. Create Active Admission Record
$admRes = $sb->request('POST', '/rest/v1/admissions', [
    'patient_id' => $patientId,
    'ward_id' => $wardId,
    'bed_number' => $bedNumber,
    'status' => 'active',
    'assigned_by' => $u['id']
], true);

if ($admRes['status'] === 201 || $admRes['status'] === 200) {
    // 2. Increment Ward Occupancy
    $wardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $wardId . '&select=ward_name,occupied_beds,admission_fee', null, true);
    if ($wardRes['status'] === 200 && !empty($wardRes['data'])) {
        $wInfo = $wardRes['data'][0];
        $newOcc = (int)$wInfo['occupied_beds'] + 1;
        $sb->request('PATCH', '/rest/v1/wards?id=eq.' . $wardId, ['occupied_beds' => $newOcc], true);

        // 3. Add Admission Fee to Invoice
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
            $fee = (float)$wInfo['admission_fee'];
            $profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=ghana_card,nhis_membership_number', null, true);
            $pData = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
            $hasNHIS = (!empty($pData['ghana_card']) && !empty($pData['nhis_membership_number']));
            $chargedFee = $fee * ($hasNHIS ? 0.5 : 1.0);

            $sb->request('POST', '/rest/v1/invoice_items', [
                'invoice_id' => $invoiceId,
                'description' => 'Admission Fee: ' . $wInfo['ward_name'] . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
                'quantity' => 1,
                'unit_price' => $fee,
                'amount' => $chargedFee
            ], true);
            $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, ['total_amount' => $currentTotal + $chargedFee], true);
        }

        // Fetch Patient Name for humanized notifications
        $pRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=name', null, true);
        $pName = ($pRes['status'] === 200 && !empty($pRes['data'])) ? $pRes['data'][0]['name'] : ('Patient ' . substr($patientId, 0, 8));

        // 4. Notify Patient (Humanized)
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $patientId,
            'message' => "Admission Confirmed: You have been assigned to " . ($bedNumber ?: 'a bed') . " in Ward " . $wInfo['ward_name'] . ". Please proceed to the ward.",
            'type' => 'admission_assignment'
        ], true);

        // 5. Cross-Notification: Notify counterparts (If Admin, notify Nurses. If Nurse, notify Admins)
        $targetRole = ($role === 'admin') ? 'nurse' : 'admin';
        $senderName = $u['user_metadata']['name'] ?? 'Staff';
        
        $recipientsRes = $sb->request('GET', '/rest/v1/profiles?role=eq.' . $targetRole . '&select=id', null, true);
        if ($recipientsRes['status'] === 200) {
            foreach ($recipientsRes['data'] as $r) {
                $sb->request('POST', '/rest/v1/notifications', [
                    'user_id' => $r['id'],
                    'message' => "ADMISSION HANDLED: $pName has been assigned to Ward " . $wInfo['ward_name'] . " (Bed: " . ($bedNumber ?: 'Auto') . ") by $senderName.",
                    'type' => 'admission_fulfilled',
                    'related_id' => $patientId
                ], true);
            }
        }

        // 6. One-Time Handling: Mark any 'admission_recommendation' notifications for this patient as read/deleted
        // This ensures the recommendation alert doesn't stay in other staff members' dashboards once handled.
        $sb->request('DELETE', '/rest/v1/notifications?type=eq.admission_recommendation&related_id=eq.' . $patientId, null, true);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ward data not found or database error', 'debug' => $wardRes]);
    }
} else {
    $errorMsg = 'Failed to create admission record';
    if ($admRes['status'] === 400) { $errorMsg = 'Database error: Column mismatch or invalid ID'; }
    echo json_encode(['success' => false, 'error' => $errorMsg, 'debug' => $admRes]);
}
