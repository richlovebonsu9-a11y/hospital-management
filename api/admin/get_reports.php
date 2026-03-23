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
    // Link prescriptions -> drug_inventory
    $resMost = $sb->request('GET', '/rest/v1/prescriptions?select=drug_id,medication_name&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resMost['status'] === 200) {
        $counts = [];
        foreach ($resMost['data'] as $p) {
            $name = $p['medication_name'];
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }
        arsort($counts);
        $reportData['most_prescribed'] = array_slice($counts, 0, 10, true);
    }

    // 2. Prescriptions per Patient/Doctor
    $resStats = $sb->request('GET', '/rest/v1/prescriptions?select=patient_id,doctor_id&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resStats['status'] === 200) {
        $perPatient = [];
        $perDoctor = [];
        foreach ($resStats['data'] as $p) {
            $perPatient[$p['patient_id']] = ($perPatient[$p['patient_id']] ?? 0) + 1;
            $perDoctor[$p['doctor_id']] = ($perDoctor[$p['doctor_id']] ?? 0) + 1;
        }
        $reportData['per_patient'] = $perPatient;
        $reportData['per_doctor'] = $perDoctor;
    }

    // 3. Revenue per Drug (Estimated)
    // We join prescriptions with drug_inventory to get unit_price
    $resRev = $sb->request('GET', '/rest/v1/prescriptions?select=medication_name,quantity_requested,drug_id,drug_inventory(unit_price)&status=eq.dispensed&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resRev['status'] === 200) {
        $revPerDrug = [];
        foreach ($resRev['data'] as $p) {
            $name = $p['medication_name'];
            $qty = $p['quantity_requested'] ?: 1;
            $price = $p['drug_inventory']['unit_price'] ?? 0;
            $revPerDrug[$name] = ($revPerDrug[$name] ?? 0) + ($qty * $price);
        }
        arsort($revPerDrug);
        $reportData['revenue_per_drug'] = $revPerDrug;
    }
} elseif ($type === 'ward') {
    // 1. Occupancy Rates
    $resWards = $sb->request('GET', '/rest/v1/wards?select=ward_name,total_beds,occupied_beds', null, true);
    if ($resWards['status'] === 200) {
        $reportData['occupancy'] = $resWards['data'];
    }

    // 2. Financial Summary (Admission Fees)
    // Search invoice_items for "Admission Fee"
    $resFees = $sb->request('GET', '/rest/v1/invoice_items?select=description,amount&description=ilike.*Admission%20Fee*&created_at=gte.' . $startDate . '&created_at=lte.' . $endDate . 'T23:59:59', null, true);
    if ($resFees['status'] === 200) {
        $wardRevenue = [];
        foreach ($resFees['data'] as $item) {
            // Description format: "Admission Fee: [Ward Name]"
            $parts = explode(':', $item['description']);
            $wardName = trim($parts[1] ?? 'General');
            $wardName = explode('(', $wardName)[0]; // Remove (NHIS...) if any
            $wardName = trim($wardName);
            $wardRevenue[$wardName] = ($wardRevenue[$wardName] ?? 0) + (float)$item['amount'];
        }
        $reportData['ward_revenue'] = $wardRevenue;
    }
}

echo json_encode(['success' => true, 'report' => $reportData]);
