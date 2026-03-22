<?php
require_once __DIR__ . '/src/lib/Supabase.php';
use App\Lib\Supabase;
$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/wards?limit=1');
print_r($res);
