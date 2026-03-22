<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
header('Content-Type: text/plain');

$tables = ['profiles', 'appointments', 'vitals', 'lab_requests', 'consultations', 'prescriptions', 'guardians'];
foreach ($tables as $t) {
    echo "TABLE: $t | ";
    $res = $sb->request('GET', "/rest/v1/$t?select=count", null, true, ['Prefer' => 'count=exact']);
    echo "Count: " . ($res['status'] === 200 ? $res['data'][0]['count'] ?? '?' : "Error (" . $res['status'] . ")") . "\n";
}

echo "\n--- SAMPLE PROFILES ---\n";
$res = $sb->request('GET', "/rest/v1/profiles?select=id,name,role&limit=5", null, true);
print_r($res['data']);
