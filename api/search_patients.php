<?php
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (!isset($_COOKIE['sb_user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

$u = json_decode($_COOKIE['sb_user'], true);
$role = $u['user_metadata']['role'] ?? '';
if (!in_array($role, ['doctor', 'nurse', 'pharmacist', 'technician', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json');

$query = $_GET['q'] ?? '';
if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$sb = new Supabase();
// We fetch all patients and filter in PHP to avoid PostgREST syntax errors or missing column failures.
// This is exactly how dashboard_admin.php fetches data successfully.
$res = $sb->request('GET', '/rest/v1/profiles?role=eq.patient&select=*', null, true);

if ($res['status'] === 200) {
    $patients = $res['data'];
    $results = [];
    $q = strtolower($query);

    foreach ($patients as $p) {
        $name = strtolower($p['name'] ?? '');
        $email = strtolower($p['email'] ?? '');
        $id = strtolower($p['id'] ?? '');
        $card = strtolower($p['ghana_card'] ?? '');

        if (str_contains($name, $q) || str_contains($email, $q) || $id === $q || $card === $q) {
            $results[] = [
                'id' => $p['id'],
                'name' => $p['name'],
                'email' => $p['email'] ?? '',
                'ghana_card' => $p['ghana_card'] ?? ''
            ];
        }
    }
    echo json_encode(array_slice($results, 0, 10));
} else {
    http_response_code(500);
    echo json_encode(['error' => 'fetch_failed', 'status' => $res['status']]);
}
