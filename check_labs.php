<?php
require_once __DIR__ . '/src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/lab_requests?select=*&order=created_at.desc', null, true);
echo "Status: " . $res['status'] . "\n";
print_r(array_slice($res['data'] ?? [], 0, 5));
