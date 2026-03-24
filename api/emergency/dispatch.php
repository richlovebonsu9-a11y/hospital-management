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
$eRes = $sb->request('GET', '/rest/v1/emergencies?id=eq.' . $emergencyId . '&select=*,reporter:profiles(*)');
$emergency = ($eRes['status'] === 200 && !empty($eRes['data'])) ? $eRes['data'][0] : null;

if (!$emergency) {
    echo json_encode(['success' => false, 'error' => 'Emergency not found']);
    exit;
}

$type = $emergency['emergency_type'];
$patientId = $emergency['reporter_id'];

// Pricing and Items mapping
$emergencyDataConfig = [
    'cardiac_emergencies' => ['fee' => 250, 'items' => ['Aspirin', 'Oxygen', 'Defibrillator', 'Nitroglycerin']],
    'diabetic_emergencies' => ['fee' => 150, 'items' => ['Glucose gel', 'Glucagon injection', 'Insulin']],
    'asthmatic_attacks' => ['fee' => 120, 'items' => ['Ventolin Inhaler', 'Nebulizer Set']],
    'snake_bite' => ['fee' => 300, 'items' => ['Snake Antivenom', 'Immobilisation Bandage']],
    'dog_bite' => ['fee' => 200, 'items' => ['Antibiotic Cream', 'Rabies Vaccine', 'Tetanus Shot']],
    'scorpion_bite' => ['fee' => 180, 'items' => ['Scorpion Antivenom', 'Lidocaine', 'Antihistamine']],
    'car_and_motor_accident' => ['fee' => 500, 'items' => []],
    'labour' => ['fee' => 450, 'items' => []],
    'sudden_consciousness_loss' => ['fee' => 450, 'items' => []],
    'breathing_difficulty' => ['fee' => 400, 'items' => []]
];

$config = $emergencyDataConfig[$type] ?? ['fee' => 100, 'items' => []];
$responseFee = $config['fee'];
$itemsToDeduct = $config['items'];

// 2. Update Emergency Status
$updateRes = $sb->request('PATCH', '/rest/v1/emergencies?id=eq.' . $emergencyId, [
    'status' => 'dispatched',
    'assigned_to' => $staffId,
    'response_fee' => $responseFee
], true);

if ($updateRes['status'] < 200 || $updateRes['status'] >= 300) {
    echo json_encode(['success' => false, 'error' => 'Update failed: ' . json_encode($updateRes)]);
    exit;
}

// 3. Inventory & Billing
$billedAmount = $responseFee;
$itemDetails = [];

foreach ($itemsToDeduct as $itemName) {
    $invRes = $sb->request('GET', '/rest/v1/inventory?drug_name=ilike.*' . urlencode($itemName) . '*&select=*', null, true);
    if ($invRes['status'] === 200 && !empty($invRes['data'])) {
        $item = $invRes['data'][0];
        if ($item['stock_count'] > 0) {
            $sb->request('PATCH', '/rest/v1/inventory?id=eq.' . $item['id'], ['stock_count' => $item['stock_count'] - 1], true);
            $billedAmount += ($item['unit_price'] ?? 0);
            $itemDetails[] = $item['drug_name'];
        }
    }
}

// 4. Create Bill
$sb->request('POST', '/rest/v1/bills', [
    'patient_id' => $patientId,
    'amount' => $billedAmount,
    'type' => 'Emergency Response',
    'status' => 'unpaid',
    'description' => "Emergency: " . str_replace('_', ' ', $type) . ". Includes dispatch fee and items: " . implode(', ', $itemDetails)
], true);

// 5. Notify Patient
$sb->request('POST', '/rest/v1/notifications', [
    'user_id' => $patientId,
    'message' => "Emergency help has been dispatched! Our team is on the way.",
    'type' => 'emergency_dispatch'
], true);

echo json_encode(['success' => true]);
