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
$meds = $_POST['medication_details'] ?? ''; // Legacy fallback
$drugId = $_POST['drug_id'] ?? '';
$dosage = $_POST['dosage'] ?? '';
$frequency = $_POST['frequency'] ?? '';
$duration = $_POST['duration'] ?? '';
$admission = isset($_POST['recommend_admission']) ? 'yes' : 'no';

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
    ], true); // useServiceKey = true

    if ($consultRes['status'] === 201 && !empty($consultRes['data'])) {
        $consultId = $consultRes['data'][0]['id'];
        
        $medName = $meds; // fallback
        if ($drugId) {
            $drugRes = $sb->request('GET', '/rest/v1/drug_inventory?id=eq.' . $drugId . '&select=drug_name', null, true);
            if ($drugRes['status'] === 200 && !empty($drugRes['data'])) {
                $medName = $drugRes['data'][0]['drug_name'];
            }
        }

        if (!empty($medName)) {
            // Save Prescription linked to this consultation
            $sb->request('POST', '/rest/v1/prescriptions', [
                'consultation_id' => $consultId,
                'patient_id' => $patientId,
                'drug_id' => $drugId ?: null,
                'medication_name' => $medName,
                'dosage' => $dosage,
                'frequency' => $frequency,
                'duration' => $duration,
                'status' => 'pending'
            ], true);
        }

        // TRIGGER ADMISSION NOTIFICATION
        if ($admission === 'yes') {
            $msg = "Admission Recommended: Dr. " . ($u['user_metadata']['name'] ?? 'Staff') . " has recommended your admission. Please check your dashboard to approve and secure a bed.";
            
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
                        'message' => "Medical Alert: Admission has been recommended for your ward. Please coordinate for approval.",
                        'type' => 'admission_request',
                        'related_id' => $consultId
                    ], true);
                }
            }
        }
    }
    
    // Conclude formal appointment queue representation
    $sb->request('PATCH', '/rest/v1/appointments?patient_id=eq.' . $patientId . '&assigned_to=eq.' . $u['id'] . '&status=eq.scheduled', [
        'status' => 'completed'
    ], true);
}

$redirect = ($role === 'doctor') ? '/dashboard_doctor.php' : '/dashboard_staff.php';
header('Location: ' . $redirect . '?visit_finished=1');
exit;
