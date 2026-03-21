<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$patientId = '0499c15c-8b84-4f60-9943-705779386008';

header('Content-Type: text/plain');

$tables = ['vitals', 'lab_requests', 'consultations', 'prescriptions'];
foreach ($tables as $t) {
    echo "--- TABLE: $t ---\n";
    $res = $sb->request('GET', "/rest/v1/$t?patient_id=eq.$patientId&select=*", null, true);
    echo "Status: " . $res['status'] . "\n";
    print_r($res['data']);
    echo "\n\n";
}
