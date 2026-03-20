<?php
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

$sb = new Supabase();
// We need to know the guardian_id. Let's list ALL guardians to see what's there.
$res = $sb->request('GET', '/rest/v1/guardians?select=*,guardian:guardian_id(name),patient:patient_id(name)', null, true);

echo "All Guardian Links (Admin View):\n";
print_r($res['data']);
?>
