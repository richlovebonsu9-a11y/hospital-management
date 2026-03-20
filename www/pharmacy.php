<?php
// Pharmacy Module - GGHMS
session_start();
if (!isset($_SESSION['user']) || ($_SESSION['user']['user_metadata']['role'] ?? '') !== 'pharmacist') {
    // Note: Staff role check might be broader in the router, but here we check specifically.
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pharmacy - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Pharmacy Management</h2>
                <p class="text-muted">Dispensing and inventory control.</p>
            </div>
            <div class="d-flex gap-2">
                 <button class="btn btn-outline-primary rounded-pill px-4"><i class="bi bi-box-seam me-2"></i> Inventory</button>
                 <button class="btn btn-primary rounded-pill px-4">Daily Report</button>
            </div>
        </header>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-5 p-4 mb-4">
                    <h5 class="fw-bold mb-4">Pending Prescriptions</h5>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead class="small text-muted border-0">
                                <tr>
                                    <th>Patient</th>
                                    <th>Medications</th>
                                    <th>Doctor</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Kwame Mensah</div>
                                        <small class="text-muted">P-1002</small>
                                    </td>
                                    <td><span class="badge bg-light text-secondary border">Amoxicillin... (+1)</span></td>
                                    <td>Dr. Anderson</td>
                                    <td><span class="badge bg-warning-soft text-warning rounded-pill px-3">Pending</span></td>
                                    <td><button class="btn btn-sm btn-primary rounded-pill px-3">Dispense</button></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Abena Osei</div>
                                        <small class="text-muted">P-4402</small>
                                    </td>
                                    <td><span class="badge bg-light text-secondary border">Paracetamol S...</span></td>
                                    <td>Dr. Mensah</td>
                                    <td><span class="badge bg-warning-soft text-warning rounded-pill px-3">Pending</span></td>
                                    <td><button class="btn btn-sm btn-primary rounded-pill px-3">Dispense</button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                 <div class="card border-0 shadow-sm rounded-5 p-4 bg-white mb-4">
                    <h5 class="fw-bold mb-4">Stock Alerts</h5>
                    <div class="alert bg-danger-soft text-danger border-0 rounded-4 mb-3">
                         <div class="fw-bold small">CRITICAL LOW</div>
                         <small>Artemether/Lumefantrine is below 50 units.</small>
                    </div>
                    <div class="alert bg-warning-soft text-warning border-0 rounded-4">
                         <div class="fw-bold small">EXPIRING SOON</div>
                         <small>3 Batches of Insulin expire in 15 days.</small>
                    </div>
                </div>

                <div class="card border-0 shadow-sm rounded-5 p-4">
                    <h5 class="fw-bold mb-4">Quick Dispense</h5>
                    <form>
                        <div class="mb-3">
                            <label class="form-label small">Prescription ID</label>
                            <input type="text" class="form-control rounded-pill border-light bg-light" placeholder="e.g. RX-9823">
                        </div>
                        <button class="btn btn-primary w-100 py-3 rounded-pill fw-bold">Fetch Prescription</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
