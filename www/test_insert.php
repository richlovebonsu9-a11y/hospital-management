<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: text/plain');
$sb = new Supabase();

$testPatientId = '3c7180eb-00ca-4177-b9b5-ac41251cee20'; // Emmanuel from screenshot
$testDoctorId = '5727038e-9080-4df2-bc8e-17855364126c'; // Just a sample if we could find one, but I'll try to use the current user if I can get it.
// Actually I'll use a known doctor ID if I can find one in profiles.

$profilesRes = $sb->request('GET', '/rest/v1/profiles?role=eq.doctor&limit=1', null, true);
if ($profilesRes['status'] === 200 && !empty($profilesRes['data'])) {
    $testDoctorId = $profilesRes['data'][0]['id'];
}

echo "Testing Consultation Insert...\n";
$data = [
    'patient_id' => $testPatientId,
    'doctor_id' => $testDoctorId,
    'notes' => 'Test Notes',
    'diagnosis' => 'Test Diagnosis',
    'recommend_admission' => 'no'
];

$res = $sb->request('POST', '/rest/v1/consultations', $data, true, ['Prefer' => 'return=representation']);
echo "Status: " . $res['status'] . "\n";
echo "Response Body:\n";
print_r($res['data']);

if ($res['status'] !== 201) {
    echo "\nFAILED TO INSERT CONSULTATION.\n";
} else {
    echo "\nCONSULTATION INSERTED SUCCESSFULLY.\n";
}
