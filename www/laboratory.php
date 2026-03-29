<?php
// Laboratory Module - K.M. General Hospital
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laboratory - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Laboratory & Radiology</h2>
                <p class="text-muted">Diagnostics workflow and result entry.</p>
            </div>
            <div class="d-flex gap-2">
                 <button class="btn btn-outline-primary rounded-pill px-4">Archived Results</button>
            </div>
        </header>

        <div class="card border-0 shadow-sm rounded-5 p-4 mb-4">
            <h5 class="fw-bold mb-4">Diagnostic Requests</h5>
            <div class="table-responsive">
                <table class="table align-middle table-hover">
                    <thead class="small text-muted border-0">
                        <tr>
                            <th>Patient</th>
                            <th>Test/Scan</th>
                            <th>Priority</th>
                            <th>Ordered By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="fw-bold">Kwame Mensah</div>
                                <small class="text-muted">P-1002</small>
                            </td>
                            <td>Full Blood Count (FBC)</td>
                            <td><span class="badge bg-primary-soft text-primary rounded-pill px-3">Normal</span></td>
                            <td>Dr. Anderson</td>
                            <td><button class="btn btn-sm btn-primary rounded-pill px-3">Enter Results</button></td>
                        </tr>
                         <tr>
                            <td>
                                <div class="fw-bold">Abena Osei</div>
                                <small class="text-muted">P-4402</small>
                            </td>
                            <td>Chest X-Ray</td>
                            <td><span class="badge bg-danger-soft text-danger rounded-pill px-3">Urgent</span></td>
                            <td>Dr. Mensah</td>
                            <td><button class="btn btn-sm btn-primary rounded-pill px-3">Upload Scan</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
