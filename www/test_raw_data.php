<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$patientId = '0499c15c-8b84-4f60-9943-705779386008';

header('Content-Type: text/plain');

$tables = ['vitals', 'lab_requests', 'consultations', 'prescriptions'];
foreach ($tables as $t) {
    echo "=== TABLE: $t ===\n";
    
    // 1. Get Schema
    $schemaRes = $sb->request('GET', "/rest/v1/$t?limit=1", null, true);
    if ($schemaRes['status'] === 200 && !empty($schemaRes['data'])) {
        echo "Columns: " . implode(', ', array_keys($schemaRes['data'][0])) . "\n";
    } else {
        // Try getting column names from information_schema if possible (might not work over PostgREST standard)
        echo "Could not fetch sample record (Status: " . $schemaRes['status'] . ").\n";
    }
    
    // 2. Get Data for Prince
    echo "Data for Prince ($patientId):\n";
    // For prescriptions, we might need a different filter if patient_id doesn't exist
    $filter = (str_contains(json_encode($schemaRes['data'] ?? []), 'patient_id') || $schemaRes['status'] !== 400) ? "patient_id=eq.$patientId" : "limit=3";
    
    $dataRes = $sb->request('GET', "/rest/v1/$t?$filter", null, true);
    echo "Status: " . $dataRes['status'] . "\n";
    print_r($dataRes['data']);
    echo "\n\n";
}
