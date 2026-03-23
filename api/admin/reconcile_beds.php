<?php
// API: Reconcile Bed Occupancy with Admissions (v1.1 - Fixed WHERE clause)
// Last Updated: 2026-03-23 15:43
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

// 1. Fetch all active admissions
$admRes = $sb->request('GET', '/rest/v1/admissions?status=eq.active&select=id,ward_id,bed_number', null, true);
$admissions = ($admRes['status'] === 200) ? $admRes['data'] : [];

$reassignedCount = 0;

foreach ($admissions as $adm) {
    $admId = $adm['id'];
    $wardId = $adm['ward_id'];
    $currentBed = $adm['bed_number'];

    // Check if this bed exists in our new beds table for this ward
    $bedCheck = $sb->request('GET', '/rest/v1/beds?ward_id=eq.' . $wardId . '&bed_number=eq.' . urlencode($currentBed) . '&select=id', null, true);
    
    if ($bedCheck['status'] === 200 && empty($bedCheck['data'])) {
        // Bed not found in new system! Let's find the first available new bed in this ward.
        $availableBedRes = $sb->request('GET', '/rest/v1/beds?ward_id=eq.' . $wardId . '&status=eq.available&select=bed_number&limit=1&order=bed_number.asc', null, true);
        
        if ($availableBedRes['status'] === 200 && !empty($availableBedRes['data'])) {
            $newBedNumber = $availableBedRes['data'][0]['bed_number'];
            
            // Update the admission to use the new bed
            $sb->request('PATCH', '/rest/v1/admissions?id=eq.' . $admId, ['bed_number' => $newBedNumber], true);
            
            // Mark it occupied in the beds table (will also be ensured by the SQL below)
            $sb->request('PATCH', '/rest/v1/beds?ward_id=eq.' . $wardId . '&bed_number=eq.' . urlencode($newBedNumber), ['status' => 'occupied'], true);
            
            $reassignedCount++;
        }
    }
}

// 2. The standard SQL reconciliation logic to sync statuses and counts
$sql = "
-- Reset
UPDATE beds SET status = 'available' WHERE true;
UPDATE wards SET occupied_beds = 0 WHERE true;

-- Mark beds as occupied based on ACTIVE admissions
UPDATE beds b
SET status = 'occupied', last_occupied_at = a.admission_date
FROM admissions a
WHERE a.status = 'active'
AND a.ward_id = b.ward_id
AND a.bed_number = b.bed_number;

-- Re-calculate ward occupancy counts
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
    if ($reassignedCount > 0) {
        echo "<p style='color: #1a237e;'><b>Note:</b> $reassignedCount active admissions were mapped from old bed numbers (e.g., BED-A5) to new valid ones (e.g., GEN-01) automatically.</p>";
    }
    echo "<p><a href='/dashboard_admin.php'>Return to Dashboard</a></p>";
} else {
    echo "<h1 style='color:red;'>Reconciliation Failed</h1>";
    echo "<p>Status Code: " . $res['status'] . "</p>";
    echo "<p>Error Detail: " . json_encode($res['data'] ?? 'No detail available') . "</p>";
    echo "<h3>Manual Fix:</h3>";
    echo "<p>If the error mentions 'exec_sql' is missing, please run the SQL below in your <b>Supabase SQL Editor</b>:</p>";
    echo "<pre style='background:#f4f4f4; padding:15px; border-radius:8px; overflow:auto;'>" . htmlspecialchars($sql) . "</pre>";
}
