<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$name = 'Godwin';
$email = 'godwin@gmail.com';

$res = $sb->request('GET', '/rest/v1/profiles?email=eq.' . urlencode($email) . '&select=*');
echo "Lookup by Email:\n";
print_r($res['data']);

$res2 = $sb->request('GET', '/rest/v1/profiles?name=ilike.*' . urlencode($name) . '*&select=*');
echo "\nLookup by Name (fuzzy):\n";
print_r($res2['data']);
?>
