<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /dashboard_staff.php'); exit; }
if (!isset($_COOKIE['sb_user'])) { header('Location: /login'); exit; }

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';

if (!in_array($role, ['doctor', 'nurse'])) {
    header('Location: /dashboard');
    exit;
}

$patientId = $_POST['patient_id'] ?? '';
$temp = $_POST['temperature'] ?? null;
$bp = $_POST['blood_pressure'] ?? '';
$weight = $_POST['weight'] ?? null;
$pulse = $_POST['pulse'] ?? null;
$notes = $_POST['notes'] ?? '';
$diagnosis = $_POST['diagnosis'] ?? '';
$meds = $_POST['meds'] ?? []; 
$admission = isset($_POST['recommend_admission']) ? 'yes' : 'no';
$wardId = $_POST['ward_id'] ?? null;
$bedNumber = $_POST['bed_number'] ?? '';

if (!$patientId) {
    header('Location: /dashboard_staff.php?error=invalid_patient'); exit;
}

$sb = new Supabase();

// 1. Save Vitals (Done by both Nurse and Doctor)
$vitalsData = [
    'patient_id' => $patientId,
    'temperature' => $temp,
    'blood_pressure' => $bp,
    'weight' => $weight,
    'pulse' => $pulse,
    'recorded_by' => $u['id']
];
$vitalsRes = $sb->request('POST', '/rest/v1/vitals', $vitalsData, true); // useServiceKey = true

// Check if there was an urgent triage appointment for this patient requested by a doctor
if ($role === 'nurse') {
    $apptRes = $sb->request('GET', '/rest/v1/appointments?patient_id=eq.' . $patientId . '&status=eq.scheduled&doctor_id=not.is.null&order=created_at.desc&limit=1', null, true);
    if ($apptRes['status'] === 200 && !empty($apptRes['data'])) {
        $urgentAppt = $apptRes['data'][0];
        $doctorId = $urgentAppt['doctor_id'];
        
        // Notify doctor
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id' => $doctorId,
            'message' => "Fresh vitals for Patient " . substr($patientId, 0, 8) . " have been successfully recorded by the nursing team."
        ], true);
        
        // Mark appointment as completed so it leaves the nurse queue
        $sb->request('PATCH', '/rest/v1/appointments?id=eq.' . $urgentAppt['id'], ['status' => 'completed'], true);
    }
}

// 2. Doctor-specific logic (Consultation & Prescription)
if ($role === 'doctor') {
    // Save Consultation Record
    $consultRes = $sb->request('POST', '/rest/v1/consultations?select=id', [
        'patient_id' => $patientId,
        'doctor_id' => $u['id'],
        'notes' => $notes,
        'diagnosis' => $diagnosis,
        'recommend_admission' => $admission
    ], true, ['Prefer' => 'return=representation']); // useServiceKey = true

    if ($consultRes['status'] === 201 && !empty($consultRes['data'])) {
        $consultId = $consultRes['data'][0]['id'];
        
        // For billing, fetch NHIS status once before the prescription loop
        $profileRes = $sb->request('GET', '/rest/v1/profiles?id=eq.' . $patientId . '&select=ghana_card,nhis_membership_number', null, true);
        $pData = ($profileRes['status'] === 200 && !empty($profileRes['data'])) ? $profileRes['data'][0] : [];
        $hasNHIS = (!empty($pData['ghana_card']) && !empty($pData['nhis_membership_number']));
        $discountMultiplier = $hasNHIS ? 0.5 : 1.0;

        // Find or create unpaid invoice
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

        $addedMedTotal = 0;

        if (is_array($meds)) {
            foreach ($meds as $m) {
                $mDrugId = $m['drug_id'] ?? '';
                $mDosage = $m['dosage'] ?? '';
                $mFreq = $m['frequency'] ?? '';
                $mDuration = $m['duration'] ?? '';
                $mQty = (int)($m['quantity'] ?? 1);
                $mMedName = '';
                $mUnitPrice = 0;

                if ($mDrugId) {
                    $drugRes = $sb->request('GET', '/rest/v1/drug_inventory?id=eq.' . $mDrugId . '&select=drug_name,unit_price', null, true);
                    if ($drugRes['status'] === 200 && !empty($drugRes['data'])) {
                        $mMedName = $drugRes['data'][0]['drug_name'];
                        $mUnitPrice = (float)($drugRes['data'][0]['unit_price'] ?? 0);
                    }
                }

                if (!empty($mMedName) || !empty($mDosage)) {
                    // Save Prescription linked to this consultation
                    $sb->request('POST', '/rest/v1/prescriptions', [
                        'consultation_id' => $consultId,
                        'patient_id' => $patientId,
                        'drug_id' => $mDrugId ?: null,
                        'medication_name' => $mMedName ?: 'Custom Medication',
                        'dosage' => $mDosage,
                        'frequency' => $mFreq,
                        'duration' => $mDuration,
                        'quantity' => $mQty,
                        'status' => 'pending'
                    ], true);

                    // Add to billing invoice immediately if drug has a price
                    if ($invoiceId && $mUnitPrice > 0) {
                        $chargedMedAmount = ($mUnitPrice * $mQty) * $discountMultiplier;
                        $sb->request('POST', '/rest/v1/invoice_items', [
                            'invoice_id' => $invoiceId,
                            'description' => 'Medication (Prescribed): ' . $mMedName . ($hasNHIS ? ' (NHIS 50% Off)' : ''),
                            'quantity' => $mQty,
                            'unit_price' => $mUnitPrice,
                            'amount' => $chargedMedAmount
                        ], true);
                        $addedMedTotal += $chargedMedAmount;
                    }
                }
            }
        }

        // Update invoice total with new medication charges
        if ($invoiceId && $addedMedTotal > 0) {
            $sb->request('PATCH', '/rest/v1/invoices?id=eq.' . $invoiceId, ['total_amount' => $currentInvoiceTotal + $addedMedTotal], true);
        }

        // TRIGGER ADMISSION NOTIFICATION OR AUTO-ASSIGNMENT
        if ($admission === 'yes') {
            if ($wardId) {
                // 1. Create Active Admission Record
                $sb->request('POST', '/rest/v1/admissions', [
                    'patient_id' => $patientId,
                    'ward_id' => $wardId,
                    'bed_number' => $bedNumber ?: 'AUTO',
                    'status' => 'active',
                    'assigned_by' => $u['id']
                ], true);

                // 2. Increment Ward Occupancy
                $wardRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $wardId . '&select=occupied_beds', null, true);
                if ($wardRes['status'] === 200 && !empty($wardRes['data'])) {
                    $newOcc = (int)$wardRes['data'][0]['occupied_beds'] + 1;
                    $sb->request('PATCH', '/rest/v1/wards?id=eq.' . $wardId, ['occupied_beds' => $newOcc], true);
                }

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
                    $wardInfoRes = $sb->request('GET', '/rest/v1/wards?id=eq.' . $wardId . '&select=ward_name,admission_fee', null, true);
                    if ($wardInfoRes['status'] === 200 && !empty($wardInfoRes['data'])) {
                        $wInfo = $wardInfoRes['data'][0];
                        $fee = (float)$wInfo['admission_fee'];
                        
                        // Check for NHIS discount
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
                }

                $msg = "Admission Confirmed: You have been assigned to " . ($bedNumber ?: 'a bed') . " in Ward " . ($wInfo['ward_name'] ?? 'Assigned') . ". Please proceed to the nurse station.";
            } else {
                $msg = "Admission Recommended: Dr. " . ($u['user_metadata']['name'] ?? 'Staff') . " has recommended your admission. A bed will be assigned by the hospital staff soon.";
            }
            
            // 1. Notify Patient
            $sb->request('POST', '/rest/v1/notifications', [
                'user_id' => $patientId,
                'message' => $msg,
                'type' => 'admission_request',
                'related_id' => $consultId
            ], true);

            // 2. Notify Guardians
            $gLinksRes = $sb->request('GET', '/rest/v1/guardians?patient_id=eq.' . $patientId . '&status=eq.approved', null, true);
            if ($gLinksRes['status'] === 200) {
                foreach ($gLinksRes['data'] as $link) {
                    $sb->request('POST', '/rest/v1/notifications', [
                        'user_id' => $link['guardian_id'],
                        'message' => "Medical Alert: Admission has been " . ($wardId ? "confirmed" : "recommended") . " for your ward.",
                        'type' => 'admission_request',
                        'related_id' => $consultId
                    ], true);
                }
            }
        }
        
        // Conclude formal appointment queue
        $sb->request('PATCH', '/rest/v1/appointments?patient_id=eq.' . $patientId . '&assigned_to=eq.' . $u['id'] . '&status=eq.scheduled', [
            'status' => 'completed'
        ], true);

        $redirect = ($role === 'doctor') ? '/dashboard_doctor.php' : '/dashboard_staff.php';
        header('Location: ' . $redirect . '?visit_finished=1');
    } else {
        $errMsg = urlencode($consultRes['data']['message'] ?? 'Database consultation record failed');
        $redirect = ($role === 'doctor') ? '/dashboard_doctor.php' : '/dashboard_staff.php';
        header('Location: ' . $redirect . '?error=save_failed&details=' . $errMsg);
    }
    exit;
}
