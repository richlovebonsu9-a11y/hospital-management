<?php
require_once __DIR__ . '/src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$resLab = $sb->request('GET', '/rest/v1/lab_requests?status=eq.pending&select=*,patient:profiles!patient_id(name)&order=created_at.asc', null, true);
echo "Lab Query Code: " . $resLab['status'] . "\n";
print_r($resLab['data']);

$resLab2 = $sb->request('GET', '/rest/v1/lab_requests?status=eq.pending&select=*,patient:patient_id(name)&order=created_at.asc', null, true);
echo "\nLab Query 2 Code: " . $resLab2['status'] . "\n";
print_r($resLab2['data']);
