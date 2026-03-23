<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
echo "--- LAB REQUESTS ---\n";
$res = $sb->request('GET', '/rest/v1/lab_requests?select=*,profiles!patient_id(name)', null, true);
print_r($res['data']);

echo "\n--- VITALS ---\n";
$res = $sb->request('GET', '/rest/v1/vitals?select=*,profiles!patient_id(name)', null, true);
print_r($res['data']);

echo "\n--- CONSULTATIONS ---\n";
$res = $sb->request('GET', '/rest/v1/consultations?select=*,profiles!patient_id(name)', null, true);
print_r($res['data']);
