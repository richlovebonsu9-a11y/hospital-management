<?php
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$sql = "SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'emergencies'";
$res = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);

print_r($res);
