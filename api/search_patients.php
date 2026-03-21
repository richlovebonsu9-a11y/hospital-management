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
// Search by name or email or ghana_card or patient_id (id)
// We use ilike for name and direct matches for ID/Card
$res = $sb->request('GET', '/rest/v1/profiles?role=eq.patient&or=(name.ilike.*' . urlencode($query) . '*,id.eq.' . urlencode($query) . ',ghana_card.eq.' . urlencode($query) . ')&select=id,name,email,ghana_card&limit=10', null, true);

if ($res['status'] === 200) {
    echo json_encode($res['data']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'search_failed', 'details' => $res['data']]);
}
