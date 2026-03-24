<?php
// Consultation Interface - Kobby Moore Hospital
session_start();
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;

if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'doctor') {
    header('Location: /login');
    exit;
}

$patient_id = $_GET['patient_id'] ?? '';
if (!$patient_id) { header('Location: /dashboard_doctor.php'); exit; }

$sb = new Supabase();
// Fetch existing lab requests for this patient to show in the table
$labsRes = $sb->request('GET', '/rest/v1/lab_requests?patient_id=eq.' . $patient_id . '&order=created_at.desc');
$labRequests = ($labsRes['status'] === 200) ? $labsRes['data'] : [];

// Fetch latest vitals for this patient
$vitalsRes = $sb->request('GET', '/rest/v1/vitals?patient_id=eq.' . $patient_id . '&order=recorded_at.desc&limit=1', null, true);
$latestVitals = ($vitalsRes['status'] === 200 && !empty($vitalsRes['data'])) ? $vitalsRes['data'][0] : null;

$patient_name = "Patient " . substr($patient_id, 0, 8);

// Fetch available drugs for the prescription list
$drugsRes = $sb->request('GET', '/rest/v1/drug_inventory?select=id,drug_name,stock_count&order=drug_name.asc', null, true);
$availableDrugs = ($drugsRes['status'] === 200) ? $drugsRes['data'] : [];

// Fetch available wards for the admission selection
$wardsRes = $sb->request('GET', '/rest/v1/wards?select=*&order=ward_name.asc', null, true);
$wards = ($wardsRes['status'] === 200) ? $wardsRes['data'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consultation - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        let medCount = 1;

        document.getElementById('add-med-consult-btn').addEventListener('click', function() {
            const container = document.getElementById('medication-list-consult');
            const firstItem = container.querySelector('.medication-item');
            const newItem = firstItem.cloneNode(true);
            
            newItem.querySelector('.remove-med-btn').classList.remove('d-none');
            newItem.querySelectorAll('input').forEach(input => input.value = '');
            newItem.querySelectorAll('select').forEach(select => select.selectedIndex = 0);
            
            const inputs = newItem.querySelectorAll('[name^="meds[0]"]');
            inputs.forEach(input => {
                const oldName = input.getAttribute('name');
                const newName = oldName.replace('meds[0]', `meds[${medCount}]`);
                input.setAttribute('name', newName);
            });
            
            newItem.querySelector('h6').innerHTML = `<i class="bi bi-plus-circle-fill text-primary"></i> Med ${String(medCount + 1).padStart(2, '0')}`;
            
            container.appendChild(newItem);
            medCount++;
        });

        function toggleAdmissionFields() {
            const check = document.getElementById('admissionCheck');
            const fields = document.getElementById('admission-fields');
            if (check.checked) {
                fields.classList.remove('d-none');
            } else {
                fields.classList.add('d-none');
            }
        }

        function updateBedDisplay() {
            // Optional: Can add logic to suggest next available bed via API
        }
    </script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
