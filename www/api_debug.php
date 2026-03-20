<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
$res = $sb->request('GET', '/rest/v1/guardians?select=*,guardian:guardian_id(name),patient:patient_id(name)', null, true);

header('Content-Type: application/json');
echo json_encode($res['data']);
?>
