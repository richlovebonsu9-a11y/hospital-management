<?php
// Billing & Invoicing - Kobby Moore Hospital
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing & NHIS - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <header class="d-flex justify-content-between align-items-center mb-5">
            <div>
                <h2 class="fw-bold mb-1">Billing & NHIS Support</h2>
                <p class="text-muted">Invoices, payments, and insurance claims.</p>
            </div>
             <button class="btn btn-primary rounded-pill px-4">New Invoice</button>
        </header>

        <div class="row g-4">
             <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-5 p-4">
                    <h5 class="fw-bold mb-4">Recent Invoices</h5>
                    <div class="table-responsive">
                         <table class="table align-middle">
                            <thead class="small text-muted border-0">
                                <tr>
                                    <th>Inv #</th>
                                    <th>Patient</th>
                                    <th>Method</th>
                                    <th>Total (₵)</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>INV-2026-001</td>
                                    <td>Kwame Mensah</td>
                                    <td><span class="badge bg-primary-soft text-primary border-primary">NHIS Co-pay</span></td>
                                    <td class="fw-bold">45.00</td>
                                    <td><span class="badge bg-success-soft text-success rounded-pill px-3">Paid</span></td>
                                    <td><button class="btn btn-sm btn-light rounded-pill"><i class="bi bi-printer"></i></button></td>
                                </tr>
                                <tr>
                                    <td>INV-2026-002</td>
                                    <td>Abena Osei</td>
                                    <td><span class="badge bg-light text-secondary border">Private</span></td>
                                    <td class="fw-bold">120.00</td>
                                    <td><span class="badge bg-warning-soft text-warning rounded-pill px-3">Pending</span></td>
                                    <td><button class="btn btn-sm btn-light rounded-pill"><i class="bi bi-wallet2"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-5 p-4 bg-primary text-white mb-4 hero-card">
                    <h6 class="fw-bold mb-4 opacity-75">NHIS Claim Summary</h6>
                    <div class="mb-3">
                        <small class="d-block opacity-75">Pending Claims</small>
                        <h3 class="fw-bold mb-0">₵ 12,450.00</h3>
                    </div>
                    <div class="mb-4">
                        <small class="d-block opacity-75">Reimbursed (MTD)</small>
                        <h4 class="fw-bold mb-0">₵ 4,200.00</h4>
                    </div>
                    <button class="btn btn-light w-100 py-3 rounded-pill fw-bold text-primary">Process NHIS Batch</button>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
