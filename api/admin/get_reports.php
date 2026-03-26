<?php
// API: Get Admin Reports
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

if (!isset($_COOKIE['sb_user'])) {
    http_response_code(401); exit;
}
$u = json_decode($_COOKIE['sb_user'], true);
if (($u['user_metadata']['role'] ?? '') !== 'admin') {
    http_response_code(403); exit;
}

$type = $_GET['type'] ?? 'inventory';
$startDate = $_GET['start_date'] ?? date('Y-m-01'); // Start of month
$endDate = $_GET['end_date'] ?? date('Y-m-d');

$sb = new Supabase();
$reportData = [];

if ($type === 'inventory') {
    // 1. Most Prescribed Drugs
    $resMost = $sb->request('GET', '/rest/v1/prescriptions?select=medication_name&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resMost['status'] === 200) {
        $counts = [];
        foreach ($resMost['data'] as $p) {
            $name = $p['medication_name'] ?: 'Unknown';
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        arsort($counts);
        $reportData['most_prescribed'] = array_slice($counts, 0, 10, true);
    }

    // 2. Revenue per Drug (Actual sales from prescriptions)
    $resRev = $sb->request('GET', '/rest/v1/prescriptions?select=medication_name,quantity_requested,drug_inventory(unit_price)&status=eq.dispensed&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resRev['status'] === 200) {
        $revPerDrug = [];
        $totalMedRev = 0;
        foreach ($resRev['data'] as $p) {
            $name = $p['medication_name'];
            $qty = (int)($p['quantity_requested'] ?: 1);
            $price = (float)($p['drug_inventory']['unit_price'] ?? 0);
            $amount = $qty * $price;
            $revPerDrug[$name] = ($revPerDrug[$name] ?? 0) + $amount;
            $totalMedRev += $amount;
        }
        arsort($revPerDrug);
        $reportData['revenue_per_drug'] = $revPerDrug;
        $reportData['total_med_revenue'] = $totalMedRev;
    }
} elseif ($type === 'ward') {
    // 1. Occupancy Rates
    $resWards = $sb->request('GET', '/rest/v1/wards?select=ward_name,total_beds,occupied_beds', null, true);
    if ($resWards['status'] === 200) {
        $reportData['occupancy'] = $resWards['data'];
    }

    // 2. Ward Revenue (From Admission Fees in invoice_items)
    $resFees = $sb->request('GET', '/rest/v1/invoice_items?select=description,amount&description=ilike.*Admission*&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resFees['status'] === 200) {
        $wardRevenue = [];
        $totalWardRev = 0;
        foreach ($resFees['data'] as $item) {
            // Description example: "Admission Fee: General Ward" or "Admission Extension (2 days): ICU"
            $desc = $item['description'];
            $wardName = 'General';
            if (strpos($desc, ':') !== false) {
                $parts = explode(':', $desc);
                $wardName = trim(explode('(', $parts[1])[0]);
            }
            $amt = (float)$item['amount'];
            $wardRevenue[$wardName] = ($wardRevenue[$wardName] ?? 0) + $amt;
            $totalWardRev += $amt;
        }
        $reportData['ward_revenue'] = $wardRevenue;
        $reportData['total_ward_revenue'] = $totalWardRev;
    }
}

echo json_encode(['success' => true, 'report' => $reportData]);
exit;
