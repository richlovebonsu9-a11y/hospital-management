<?php
session_start();
require_once __DIR__ . '/../../src/lib/Supabase.php';
use App\Lib\Supabase;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$sb = new Supabase();
$emergencyId = $_POST['emergency_id'] ?? '';
$dispatchNotes = $_POST['dispatch_notes'] ?? '';
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
$staffId = $_SESSION['user']['id'] ?? null;

if (!$staffId || empty($emergencyId)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized or missing ID']);
    exit;
}

// 1. Fetch Emergency details
$eRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=*', null, true);
$emergency = ($eRes['status'] === 200 && !empty($eRes['data'])) ? $eRes['data'][0] : null;

if (!$emergency) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}

$type = $emergency['emergency_type'];
$patientId = $emergency['reporter_id'];

// Pricing and Items mapping
$emergencyDataConfig = [
    'cardiac_emergencies'      => ['fee' => 250, 'items' => ['Aspirin', 'Oxygen', 'Defibrillator', 'Nitroglycerin']],
    'diabetic_emergencies'     => ['fee' => 150, 'items' => ['Glucose gel', 'Glucagon injection', 'Insulin']],
    'asthmatic_attacks'        => ['fee' => 120, 'items' => ['Ventolin Inhaler', 'Nebulizer Set']],
    'snake_bite'               => ['fee' => 300, 'items' => ['Snake Antivenom', 'Immobilisation Bandage']],
    'dog_bite'                 => ['fee' => 200, 'items' => ['Antibiotic Cream', 'Rabies Vaccine', 'Tetanus Shot']],
    'scorpion_bite'            => ['fee' => 180, 'items' => ['Scorpion Antivenom', 'Lidocaine', 'Antihistamine']],
    'car_and_motor_accident'   => ['fee' => 500, 'items' => []],
    'labour'                   => ['fee' => 450, 'items' => []],
    'sudden_consciousness_loss'=> ['fee' => 450, 'items' => []],
    'breathing_difficulty'     => ['fee' => 400, 'items' => []],
];

$config = $emergencyDataConfig[$type] ?? null;

// AI Triage for 'Other' type: If type is 'other', try to infer the config from symptoms
if ($type === 'other' || !$config) {
    $symptoms = strtolower($emergency['symptoms'] ?? '');
    if (strpos($symptoms, 'heart') !== false || strpos($symptoms, 'cardiac') !== false || strpos($symptoms, 'chest pain') !== false) {
        $config = $emergencyDataConfig['cardiac_emergencies'];
    } elseif (strpos($symptoms, 'asthma') !== false || strpos($symptoms, 'breath') !== false || strpos($symptoms, 'inhaler') !== false) {
        $config = $emergencyDataConfig['asthmatic_attacks'];
    } elseif (strpos($symptoms, 'sugar') !== false || strpos($symptoms, 'diabet') !== false || strpos($symptoms, 'insulin') !== false) {
        $config = $emergencyDataConfig['diabetic_emergencies'];
    } elseif (strpos($symptoms, 'accident') !== false || strpos($symptoms, 'crash') !== false || strpos($symptoms, 'collision') !== false) {
        $config = $emergencyDataConfig['car_and_motor_accident'];
    } elseif (strpos($symptoms, 'snake') !== false) {
        $config = $emergencyDataConfig['snake_bite'];
    } elseif (strpos($symptoms, 'dog') !== false) {
        $config = $emergencyDataConfig['dog_bite'];
    } elseif (strpos($symptoms, 'scorpion') !== false) {
        $config = $emergencyDataConfig['scorpion_bite'];
    } elseif (strpos($symptoms, 'labour') !== false || strpos($symptoms, 'birth') !== false || strpos($symptoms, 'pregnant') !== false) {
        $config = $emergencyDataConfig['labour'];
    } else {
        // Fallback for truly unknown 'other' cases
        $config = ['fee' => 100, 'items' => []];
    }
}

$responseFee = $config['fee'];
$itemsToDeduct = $config['items'];

// 2. Update Emergency Status
$updateRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, [
    'status'       => 'dispatched',
    'assigned_to'  => $staffId,
    'response_fee' => $responseFee
], true);

if ($updateRes['status'] < 200 || $updateRes['status'] >= 300) {
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . json_encode($updateRes)]);
    exit;
}

// 3. Deduct Inventory (drug_inventory table) and calculate billed amount
$billedAmount = $responseFee;
$itemDetails  = [];

foreach ($itemsToDeduct as $itemName) {
    $invRes = $sb->request('GET', '/rest/v1/drug_inventory?drug_name=ilike.*' . urlencode($itemName) . '*&select=*', null, true);
    if ($invRes['status'] === 200 && !empty($invRes['data'])) {
        $item = $invRes['data'][0];
        if ($item['stock_count'] > 0) {
            $sb->request('PATCH', '/rest/v1/drug_inventory?id=eq.' . $item['id'], [
                'stock_count' => $item['stock_count'] - 1
            ], true);
            $billedAmount += (float)($item['unit_price'] ?? 0);
            $itemDetails[] = $item['drug_name'];
        }
    }
}

// 4. Create Invoice in the correct table
$typeName = str_replace('_', ' ', $type);
$invRes = $sb->request('POST', '/rest/v1/invoices', [
    'patient_id'   => $patientId,
    'total_amount' => $billedAmount,
    'status'       => 'unpaid',
    'nhis_note'    => 'Emergency Response: ' . $typeName
], true, ['Prefer' => 'return=representation']);

$invoiceId = null;
if ($invRes['status'] === 201 && !empty($invRes['data'])) {
    $invoiceId = $invRes['data'][0]['id'];
}

// 4b. Create Invoice Line Items for transparency
if ($invoiceId) {
    // Base dispatch fee
    $sb->request('POST', '/rest/v1/invoice_items', [
        'invoice_id'  => $invoiceId,
        'description' => 'Emergency Dispatch Fee: ' . ucwords($typeName),
        'quantity'    => 1,
        'unit_price'  => $responseFee,
        'amount'      => $responseFee
    ], true);

    // Individual item lines
    foreach ($itemsToDeduct as $itemName) {
        $invRes2 = $sb->request('GET', '/rest/v1/drug_inventory?drug_name=ilike.*' . urlencode($itemName) . '*&select=drug_name,unit_price&limit=1', null, true);
        if ($invRes2['status'] === 200 && !empty($invRes2['data'])) {
            $d = $invRes2['data'][0];
            $itemCost = (float)($d['unit_price'] ?? 0);
            if ($itemCost > 0) {
                $sb->request('POST', '/rest/v1/invoice_items', [
                    'invoice_id'  => $invoiceId,
                    'description' => 'Emergency Supply: ' . $d['drug_name'],
                    'quantity'    => 1,
                    'unit_price'  => $itemCost,
                    'amount'      => $itemCost
                ], true);
            }
        }
    }
}

// 5. Notify Patient with bill details
$itemsText = !empty($itemDetails) ? ' Supplies used: ' . implode(', ', $itemDetails) . '.' : '';
$sb->request('POST', '/rest/v1/notifications', [
    'user_id' => $patientId,
    'message'  => "Emergency help has been dispatched! Our team is on the way. An invoice of GHS {$billedAmount} has been added to your account.{$itemsText}",
    'type'     => 'emergency_dispatch'
], true);

// 6. Notify Admins about the dispatch
$adminsRes = $sb->request('GET', '/rest/v1/profiles?role=eq.admin&select=id', null, true);
if ($adminsRes['status'] === 200) {
    foreach ($adminsRes['data'] as $admin) {
        $sb->request('POST', '/rest/v1/notifications', [
            'user_id'    => $admin['id'],
            'message'    => "Emergency dispatched: " . ucwords($typeName) . " — Emergency team is on the way to the patient.",
            'type'       => 'emergency_alert',
            'related_id' => $emergencyId
        ], true);
    }
}

echo json_encode(['success' => true, 'invoice_id' => $invoiceId, 'billed' => $billedAmount]);
