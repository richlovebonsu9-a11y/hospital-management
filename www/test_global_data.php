<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();

header('Content-Type: text/plain');

$tables = ['vitals', 'lab_requests', 'consultations', 'prescriptions'];
foreach ($tables as $t) {
    echo "=== GLOBAL TABLE: $t ===\n";
    $res = $sb->request('GET', "/rest/v1/$t?select=*&order=created_at.desc&limit=5", null, true);
    // If created_at doesn't exist, try recorded_at or just limit
    if ($res['status'] !== 200) {
        $res = $sb->request('GET', "/rest/v1/$t?select=*&limit=5", null, true);
    }
    
    echo "Status: " . $res['status'] . "\n";
    if (!empty($res['data'])) {
        foreach ($res['data'] as $row) {
             echo "Row ID: " . ($row['id'] ?? 'N/A') . " | Patient ID: " . ($row['patient_id'] ?? 'N/A') . " | Date: " . ($row['created_at'] ?? $row['recorded_at'] ?? 'N/A') . "\n";
        }
    } else {
        echo "No records found.\n";
    }
    echo "\n\n";
}
