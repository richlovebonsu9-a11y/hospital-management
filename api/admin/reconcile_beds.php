<?php
// API: Reconcile Bed Occupancy with Admissions
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

// Admin check
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo "Unauthorized. Admin access required.";
    exit;
}

$sb = new Supabase();

// The reconciliation SQL logic
$sql = "
-- 1. Reset all beds to available
UPDATE beds SET status = 'available';

-- 2. Reset all ward occupancy to zero
UPDATE wards SET occupied_beds = 0;

-- 3. Mark beds as occupied based on ACTIVE admissions
-- We match by ward_id and bed_number string
UPDATE beds b
SET status = 'occupied', last_occupied_at = a.admission_date
FROM admissions a
WHERE a.status = 'active'
AND a.ward_id = b.ward_id
AND a.bed_number = b.bed_number;

-- 4. Re-calculate ward occupancy counts
UPDATE wards w
SET occupied_beds = sub.count
FROM (
    SELECT ward_id, count(*) as count
    FROM admissions
    WHERE status = 'active'
    GROUP BY ward_id
) sub
WHERE w.id = sub.ward_id;
";

// Execute migration
$res = $sb->request('POST', '/rest/v1/rpc/exec_sql', ['query' => $sql], true);

header('Content-Type: text/html');
if ($res['status'] === 200 || $res['status'] === 204) {
    // Also fetch the current count for confirmation
    $admCountRes = $sb->request('GET', '/rest/v1/admissions?status=eq.active&select=count', null, true, ['Prefer' => 'count=exact']);
    $count = $admCountRes['status'] === 200 ? ($admCountRes['data'][0]['count'] ?? 'some') : 'some';

    echo "<h1>Reconciliation Success!</h1>";
    echo "<p>The system has been synchronized. All beds and ward counts now reflect the <b>$count</b> active admissions currently in your database.</p>";
    echo "<p><a href='/dashboard_admin.php'>Return to Dashboard</a></p>";
} else {
    echo "<h1 style='color:red;'>Reconciliation Failed</h1>";
    echo "<p>Status Code: " . $res['status'] . "</p>";
    echo "<p>Error Detail: " . json_encode($res['data'] ?? 'No detail available') . "</p>";
    echo "<h3>Manual Fix:</h3>";
    echo "<p>If the error mentions 'exec_sql' is missing, please run the SQL below in your <b>Supabase SQL Editor</b>:</p>";
    echo "<pre style='background:#f4f4f4; padding:15px; border-radius:8px; overflow:auto;'>" . htmlspecialchars($sql) . "</pre>";
}
