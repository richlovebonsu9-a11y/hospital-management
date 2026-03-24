<?php
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;
$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/emergencies?select=*', null, true);
print_r($res);
