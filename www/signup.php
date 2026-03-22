<?php
// Signup page implementation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-primary d-flex align-items-center py-5" style="background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%) !important; min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-0 shadow-lg rounded-5 overflow-hidden glass p-4">
                    <div class="card-body p-4">
                        <div class="text-center mb-5">
                            <div class="bg-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <div class="bg-white rounded-circle" style="width: 20px; height: 20px;"></div>
                            </div>
                            <h2 class="fw-bold text-secondary">Join GGHMS</h2>
                            <p class="text-muted">Register to access hospital services</p>
                        </div>
                        <form action="/api/auth/signup" method="POST">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Full Name</label>
                                    <input type="text" name="name" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="John Doe">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Primary Role</label>
                                     <select name="role" class="form-select rounded-pill px-4 py-3 border-light bg-light" required>
                                         <option value="patient">Patient</option>
                                         <option value="guardian">Guardian</option>
                                     </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Phone Number</label>
                                    <input type="tel" name="phone" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="+233 ...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">GhanaPostGPS Address</label>
                                    <input type="text" name="ghana_post_gps" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="AK-485-9323">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-600">Email Address</label>
                                    <input type="email" name="email" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="name@example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Password</label>
                                    <input type="password" name="password" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="••••••••">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-600">Confirm Password</label>
                                    <input type="password" name="password_confirm" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="••••••••">
                                </div>
                            </div>
                            <div class="mt-5">
                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">Create My Account &rarr;</button>
                            </div>
                        </form>

                        <div class="text-center mt-5">
                            <p class="text-muted mb-0">Already a member? <a href="/login" class="text-primary fw-bold text-decoration-none">Sign In</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
