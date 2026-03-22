<?php
// Appointment Booking - Kobby Moore Hospital
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Kobby Moore Hospital</title>
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
                                    <select name="specialty" class="form-select rounded-pill px-4 py-3 border-light bg-light" required>
                                        <option value="general">General OPD</option>
                                        <option value="pediatrics">Pediatrics</option>
                                        <option value="cardiology">Cardiology</option>
                                        <option value="maternity">Maternity</option>
                                        <option value="dental">Dental</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold">Preferred Doctor (Optional)</label>
                                    <select name="doctor_id" class="form-select rounded-pill px-4 py-3 border-light bg-light">
                                        <option value="">Any Available Specialist</option>
                                        <option value="dr-michael">Dr. Michael Anderson</option>
                                        <option value="dr-sarah">Dr. Sarah Mensah</option>
                                        <option value="dr-robert">Dr. Robert Asante</option>
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
</body>
</html>
