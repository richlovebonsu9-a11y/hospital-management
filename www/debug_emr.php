<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$patientId = '0499c15c-8b84-4f60-9943-705779386008';

header('Content-Type: application/json');

$res = [
    'vitals' => $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . $patientId, null, true),
    'labs' => $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . $patientId, null, true),
    'consults' => $sb->request('GET', '/rest/v1/consultations?patient_id=eq.' . $patientId, null, true)
];

echo json_encode($res, JSON_PRETTY_PRINT);
