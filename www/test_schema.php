<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();

header('Content-Type: text/plain');

$tables = ['vitals', 'lab_requests', 'consultations', 'prescriptions'];
foreach ($tables as $t) {
    echo "--- SCHEMA: $t ---\n";
    $res = $sb->request('GET', "/rest/v1/$t?limit=1", null, true);
    if ($res['status'] === 200 && !empty($res['data'])) {
        print_r(array_keys($res['data'][0]));
    } else {
        echo "Error or no records: " . $res['status'] . "\n";
        print_r($res['data']);
    }
    echo "\n\n";
}
