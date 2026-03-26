<?php
// API: Get Admin Reports (Advanced)
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
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$filterDrug = $_GET['drug_id'] ?? null;
$filterWard = $_GET['ward_id'] ?? null;

$sb = new Supabase();
$reportData = [];

// Base Filters for most queries
$dateFilter = 'created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59';

if ($type === 'inventory') {
    // 1. Most Prescribed Drugs (By Doctor if needed?)
    $prescUrl = '/rest/v1/prescriptions?select=medication_name,status&' . $dateFilter;
    if ($filterDrug) $prescUrl .= '&drug_id=eq.' . $filterDrug;
    
    $resMost = $sb->request('GET', $prescUrl, null, true);
    if ($resMost['status'] === 200) {
        $counts = [];
        foreach ($resMost['data'] as $p) {
            $name = $p['medication_name'] ?: 'Unknown';
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        arsort($counts);
        $reportData['most_prescribed'] = array_slice($counts, 0, 10, true);
    }

    // 2. Comprehensive Drug Revenue (Prescriptions + Emergency Dispatches)
    // A. Dispensed Prescriptions
    $dispUrl = '/rest/v1/prescriptions?select=medication_name,quantity,drug_inventory(unit_price)&status=eq.dispensed&' . $dateFilter;
    if ($filterDrug) $dispUrl .= '&drug_id=eq.' . $filterDrug;
    $resRev = $sb->request('GET', $dispUrl, null, true);
    
    $revPerDrug = [];
    $totalMedRev = 0;
    
    if ($resRev['status'] === 200) {
        foreach ($resRev['data'] as $p) {
            $name = $p['medication_name'];
            $qty = (int)($p['quantity'] ?: 1);
            $price = (float)($p['drug_inventory']['unit_price'] ?? 0);
            $amount = $qty * $price;
            $revPerDrug[$name] = ($revPerDrug[$name] ?? 0) + $amount;
            $totalMedRev += $amount;
        }
    }

    // B. Emergency Supplies (from invoice_items)
    $emergUrl = '/rest/v1/invoice_items?select=description,amount&description=ilike.*Emergency Supply*&' . $dateFilter;
    $resEmerg = $sb->request('GET', $emergUrl, null, true);
    if ($resEmerg['status'] === 200) {
        foreach ($resEmerg['data'] as $item) {
            // "Emergency Supply: Paracetamol"
            $parts = explode(':', $item['description']);
            $name = isset($parts[1]) ? trim($parts[1]) : 'Emergency Item';
            if ($filterDrug && strpos(strtolower($name), strtolower($filterDrug)) === false) continue; // Basic filter for description
            
            $amount = (float)$item['amount'];
            $revPerDrug[$name] = ($revPerDrug[$name] ?? 0) + $amount;
            $totalMedRev += $amount;
        }
    }

    arsort($revPerDrug);
    $reportData['revenue_per_drug'] = $revPerDrug;
    $reportData['total_med_revenue'] = $totalMedRev;

    // 3. Prescriptions per Doctor
    $drUrl = '/rest/v1/prescriptions?select=doctor_id,profiles!prescriptions_doctor_id_fkey(name)&' . $dateFilter;
    $resDr = $sb->request('GET', $drUrl, null, true);
    if ($resDr['status'] === 200) {
        $drPresc = [];
        foreach ($resDr['data'] as $p) {
            $name = $p['profiles']['name'] ?? 'System / Other';
            $drPresc[$name] = ($drPresc[$name] ?? 0) + 1;
        }
        arsort($drPresc);
        $reportData['prescriptions_per_doctor'] = $drPresc;
    }

} elseif ($type === 'ward') {
    // 1. Occupancy Rates
    $resWards = $sb->request('GET', '/rest/v1/wards?select=ward_name,total_beds,occupied_beds', null, true);
    if ($resWards['status'] === 200) {
        $reportData['occupancy'] = $resWards['data'];
    }

    // 2. Detailed Ward Revenue
    $wardFilter = $filterWard ? '&description=ilike.*' . urlencode($filterWard) . '*' : '';
    $resFees = $sb->request('GET', '/rest/v1/invoice_items?select=description,amount&description=ilike.*Admission*&' . $dateFilter . $wardFilter, null, true);
    
    if ($resFees['status'] === 200) {
        $wardRevenue = [];
        $totalWardRev = 0;
        foreach ($resFees['data'] as $item) {
            $desc = $item['description'];
            $wardName = 'Unspecified Ward';
            
            // "Admission Fee: General Ward" or "Admission Extension (2 days): ICU"
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
} elseif ($type === 'financial') {
    // Overall Financial Summary
    $resInv = $sb->request('GET', '/rest/v1/invoices?select=total_amount,status&' . $dateFilter, null, true);
    if ($resInv['status'] === 200) {
        $totalRev = 0;
        $paidRev = 0;
        foreach ($resInv['data'] as $inv) {
            $amt = (float)$inv['total_amount'];
            $totalRev += $amt;
            if (($inv['status'] ?? '') === 'paid') $paidRev += $amt;
        }
        $reportData['total_estimated_revenue'] = $totalRev;
        $reportData['total_collected_revenue'] = $paidRev;
    }
}

echo json_encode(['success' => true, 'report' => $reportData]);
exit;
