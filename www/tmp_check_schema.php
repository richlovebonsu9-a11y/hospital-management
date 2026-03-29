<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
header('Content-Type: text/plain');

$res = $sb->request('GET', '/rest/v1/emergencies?limit=1', null, true);
if ($res['status'] === 200 && !empty($res['data'])) {
    echo "COLUMNS:\n";
    print_r(array_keys($res['data'][0]));
} else {
    echo "Error or no records: " . $res['status'] . "\n";
    print_r($res['data']);
}
