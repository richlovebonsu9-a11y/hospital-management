<?php
require_once __DIR__ . '/src/lib/Supabase.php';
use App\Lib\Supabase;
$sb = new Supabase();

// Find General Ward ID
$wardRes = $sb->request('GET', '/rest/v1/wards?ward_name=eq.General%20Ward', null, true);
$wardId = $wardRes['data'][0]['id'] ?? null;

// Get a patient ID
$patRes = $sb->request('GET', '/rest/v1/profiles?role=eq.patient&limit=1', null, true);
$patientId = $patRes['data'][0]['id'] ?? null;

// Get an admin ID
$adminRes = $sb->request('GET', '/rest/v1/profiles?role=eq.admin&limit=1', null, true);
$adminId = $adminRes['data'][0]['id'] ?? null;

if ($wardId && $patientId) {
    echo "Ward: $wardId\nPatient: $patientId\nAdmin: $adminId\n";
    $admRes = $sb->request('POST', '/rest/v1/admissions', [
        'patient_id' => $patientId,
        'ward_id' => $wardId,
        'bed_number' => 'GEN-01',
        'status' => 'active',
        'assigned_by' => $adminId,
        'anticipated_days' => 2
    ], true);
    print_r($admRes);
} else {
    echo "Missing data to test.";
}
