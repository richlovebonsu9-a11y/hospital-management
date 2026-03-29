<?php
// Appointment Booking - K.M. General Hospital
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];

// Fetch available doctors
require_once __DIR__ . '/../src/lib/Supabase.php';
use App\Lib\Supabase;
$sb = new Supabase();
$doctorsRes = $sb->request('GET', '/rest/v1/profiles?role=eq.doctor&select=id,name,department', null, true);
$doctors = [];
if ($doctorsRes['status'] === 200 && is_array($doctorsRes['data'])) {
    $doctors = $doctorsRes['data'];
}

$preSelectedDoctorName = $_GET['doctor'] ?? '';
$preSelectedDept = $_GET['dept'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5 mt-5">
        <header class="mb-5 text-center">
            <h2 class="fw-bold mb-1">Book an Appointment</h2>
            <p class="text-muted">Select a specialty and preferred date.</p>
        </header>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-lg rounded-5 overflow-hidden">
                    <div class="card-body p-5">
                        <form action="/api/appointments/book" method="POST">
                            <div class="row g-4 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Select Specialty</label>
                                    <select name="department" class="form-select rounded-pill px-4 py-3 border-light bg-light" required id="deptSelect" onchange="filterDoctors()">
                                        <option value="">Select Specialty First</option>
                                        <option value="General OPD" <?php echo ($preSelectedDept === 'General OPD') ? 'selected' : ''; ?>>General OPD</option>
                                        <option value="Pediatrics" <?php echo ($preSelectedDept === 'Pediatrics') ? 'selected' : ''; ?>>Pediatrics</option>
                                        <option value="Cardiology" <?php echo ($preSelectedDept === 'Cardiology') ? 'selected' : ''; ?>>Cardiology</option>
                                        <option value="Maternity" <?php echo ($preSelectedDept === 'Maternity') ? 'selected' : ''; ?>>Maternity</option>
                                        <option value="Surgery" <?php echo ($preSelectedDept === 'Surgery') ? 'selected' : ''; ?>>Surgery</option>
                                        <option value="Neurology" <?php echo ($preSelectedDept === 'Neurology') ? 'selected' : ''; ?>>Neurology</option>
                                        <option value="Dental" <?php echo ($preSelectedDept === 'Dental') ? 'selected' : ''; ?>>Dental</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Preferred Doctor (Optional)</label>
                                    <select name="doctor_id" id="doctorSelect" class="form-select rounded-pill px-4 py-3 border-light bg-light">
                                        <option value="" data-dept="all">Any Available Specialist</option>
                                        <?php foreach($doctors as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" data-dept="<?php echo htmlspecialchars($d['department']); ?>" <?php echo ($preSelectedDoctorName === $d['name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($d['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Date</label>
                                    <input type="date" name="date" class="form-control rounded-pill px-4 py-3 border-light bg-light" required min="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Time Slot</label>
                                    <select name="time" class="form-select rounded-pill px-4 py-3 border-light bg-light" required>
                                        <option value="08:00">08:00 AM</option>
                                        <option value="09:00">09:00 AM</option>
                                        <option value="10:00">10:00 AM</option>
                                        <option value="11:00">11:00 AM</option>
                                        <option value="14:00">02:00 PM</option>
                                        <option value="15:00">03:00 PM</option>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-bold">Reason for Visit</label>
                                    <textarea name="reason" class="form-control rounded-4 p-4 border-light bg-light" rows="3" placeholder="Briefly describe your symptoms..."></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">Book Appointment Now &rarr;</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/auto_dismiss.js"></script>
    <script>
        function filterDoctors() {
            const dept = document.getElementById('deptSelect').value;
            const docSelect = document.getElementById('doctorSelect');
            let hasVisibleOptions = false;

            Array.from(docSelect.options).forEach(opt => {
                if(opt.value === '') return; // keep 'Any Available Specialist'
                if(dept === '' || opt.getAttribute('data-dept') === dept) {
                    opt.style.display = '';
                    hasVisibleOptions = true;
                } else {
                    opt.style.display = 'none';
                    if(opt.selected) docSelect.value = '';
                }
            });
        }
        // Run on load to set initial state
        document.addEventListener('DOMContentLoaded', filterDoctors);
    </script>
</body>
</html>
