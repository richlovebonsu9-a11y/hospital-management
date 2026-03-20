<?php
// Ward Management - GGHMS
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Management - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Ward & Bed Management</h2>
                <p class="text-muted">Tracking inpatient admissions and occupancy.</p>
            </div>
            <button class="btn btn-primary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#admitPatientModal">+ Admit Patient</button>
        </header>

        <div class="row g-4 mb-5">
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <h6 class="text-muted mb-2">Total Beds</h6>
                    <h2 class="fw-bold mb-0">200</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <h6 class="text-muted mb-2">Occupied</h6>
                    <h2 class="fw-bold mb-0 text-danger">164</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <h6 class="text-muted mb-2">Available</h6>
                    <h2 class="fw-bold mb-0 text-success">36</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card p-4 border-0 shadow-sm text-center">
                    <h6 class="text-muted mb-2">Pending Discharge</h6>
                    <h2 class="fw-bold mb-0 text-warning">08</h2>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-5 p-4">
            <h5 class="fw-bold mb-4">Current Admitted Patients</h5>
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="small text-muted border-0">
                        <tr>
                            <th>Ward / Bed</th>
                            <th>Patient Name</th>
                            <th>Adm. Date</th>
                            <th>Condition</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-light text-primary border">Male Ward - B12</span></td>
                            <td>Kwame Mensah</td>
                            <td>Mar 15, 2026</td>
                            <td>Pneumonia (Stable)</td>
                            <td><button class="btn btn-sm btn-outline-danger rounded-pill px-3">Discharge</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Admission Modal -->
    <div class="modal fade" id="admitPatientModal" tabindex="-1">
         <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-5 shadow-lg">
                <div class="modal-body p-5">
                    <h4 class="fw-bold text-center mb-4">Patient Admission</h4>
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Patient Search</label>
                            <input type="text" class="form-control rounded-pill border-light bg-light" placeholder="Name or ID">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Select Ward</label>
                            <select class="form-select rounded-pill border-light bg-light">
                                <option>Male Medical Ward</option>
                                <option>Female Medical Ward</option>
                                <option>Maternity Ward</option>
                                <option>Pediatric Ward</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill">Confirm Admission</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
