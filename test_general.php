<?php
header('Content-Type: application/json');
require_once __DIR__ . '/src/lib/Supabase.php';
$sb = new \App\Lib\Supabase();

// Force add anticipated_days and assigned_by to admissions just in case that's the 400 error
$sql = "
ALTER TABLE admissions ADD COLUMN IF NOT EXISTS anticipated_days INTEGER DEFAULT 1;
ALTER TABLE admissions ADD COLUMN IF NOT EXISTS assigned_by UUID REFERENCES profiles(id);
";
$migRes = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);

// Fetch General Ward ID
$wardRes = $sb->request('GET', '/rest/v1/wards?ward_name=eq.General%20Ward', null, true);
$wardId = $wardRes['data'][0]['id'] ?? null;

$patients = $sb->request('GET', '/rest/v1/profiles?role=eq.patient&limit=1', null, true);
$patientId = $patients['data'][0]['id'] ?? null;

$admins = $sb->request('GET', '/rest/v1/profiles?role=eq.admin&limit=1', null, true);
$adminId = $admins['data'][0]['id'] ?? null;

$result = ['migration' => $migRes, 'ward_id' => $wardId, 'patient_id' => $patientId];

if ($wardId && $patientId) {
    // Try to admit
    $admRes = $sb->request('POST', '/rest/v1/admissions', [
        'patient_id' => $patientId,
        'ward_id' => $wardId,
        'bed_number' => 'GEN-001',
        'status' => 'active',
        'assigned_by' => $adminId,
        'anticipated_days' => 2
    ], true);
    
    $result['admission_test'] = $admRes;
}

echo json_encode($result);
