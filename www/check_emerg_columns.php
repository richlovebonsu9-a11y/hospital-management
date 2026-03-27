<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
header('Content-Type: text/plain');

$t = 'emergencies';
echo "=== TABLE: $t ===\n";

// Get sample record to see columns
$schemaRes = $sb->request('GET', "/rest/v1/$t?limit=1", null, true);
if ($schemaRes['status'] === 200 && !empty($schemaRes['data'])) {
    echo "Columns found: " . implode(', ', array_keys($schemaRes['data'][0])) . "\n";
} else {
    echo "Could not fetch sample record or table is empty (Status: " . $schemaRes['status'] . ").\n";
    // If empty, try to fetch the first row regardless of status
    $allRes = $sb->request('GET', "/rest/v1/$t", null, true);
    if ($allRes['status'] === 200 && !empty($allRes['data'])) {
        echo "Columns found (from full scan): " . implode(', ', array_keys($allRes['data'][0])) . "\n";
    } else {
        echo "Error: " . json_encode($allRes) . "\n";
    }
}
