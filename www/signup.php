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
<body class="d-flex align-items-center py-5 min-vh-100" style="background-color: var(--bg-light); position: relative; overflow-x: hidden;">
    <!-- Vibrant Background Elements -->
    <div class="position-absolute" style="top: -15%; right: -10%; width: 60vw; height: 60vw; background: radial-gradient(circle, var(--theme-blue-soft) 0%, transparent 70%); z-index: 0; animation: pulse-blue 15s infinite alternate;"></div>
    <div class="position-absolute" style="bottom: -20%; left: -10%; width: 70vw; height: 70vw; background: radial-gradient(circle, var(--theme-green-soft) 0%, transparent 70%); z-index: 0; animation: pulse-blue 12s infinite alternate-reverse;"></div>

    <div class="container position-relative" style="z-index: 1;">
        <div class="row justify-content-center">
            <div class="col-md-9 col-lg-7">
                
                <!-- Signup Card -->
                <div class="card border-0 rounded-4 overflow-hidden shadow-lg bg-white" style="transform: translateY(0); transition: all 0.4s ease;">
                    <!-- Elegant Top Color Bar -->
                    <div class="d-flex" style="height: 6px;">
                        <div class="w-50 bg-primary"></div>
                        <div class="w-50 bg-success"></div>
                    </div>
                    
                    <div class="card-body p-5">
                        <div class="text-center mb-5">
                            <!-- Logo Icon -->
                            <div class="d-inline-flex align-items-center justify-content-center bg-success-soft text-success rounded-4 mb-3 shadow-sm" style="width: 72px; height: 72px; transform: rotate(10deg);">
                                <i class="bi bi-person-plus-fill fs-1" style="transform: rotate(-10deg);"></i>
                            </div>
                            <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.5px;">Join GGHMS</h3>
                            <p class="text-muted small">Register to experience modern healthcare.</p>
                        </div>
                        
                        <form action="/api/auth/signup" method="POST" autocomplete="off">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Full Name</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-person"></i></span>
                                        <input type="text" name="name" class="form-control border-0 bg-white" required placeholder="John Doe" autocomplete="off" style="box-shadow: none;">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Primary Role</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-tag"></i></span>
                                        <select name="role" class="form-select border-0 bg-white" required style="box-shadow: none;">
                                            <option value="patient">Patient</option>
                                            <option value="guardian">Guardian</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Phone Number</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-telephone"></i></span>
                                        <input type="tel" name="phone" class="form-control border-0 bg-white" required placeholder="+233 ..." autocomplete="off" style="box-shadow: none;">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">GhanaPostGPS</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-geo-alt"></i></span>
                                        <input type="text" name="ghana_post_gps" class="form-control border-0 bg-white" required placeholder="AK-485-9323" autocomplete="off" style="box-shadow: none;">
                                    </div>
                                </div>
                                
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Email Address</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="email" class="form-control border-0 bg-white" required placeholder="name@example.com" autocomplete="off" style="box-shadow: none;">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Password</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-shield-lock"></i></span>
                                        <input type="password" name="password" class="form-control border-0 bg-white" required placeholder="••••••••" autocomplete="new-password" style="box-shadow: none;">
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Confirm Password</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-shield-check"></i></span>
                                        <input type="password" name="password_confirm" class="form-control border-0 bg-white" required placeholder="••••••••" autocomplete="new-password" style="box-shadow: none;">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Internal App Signup (Hidden by default unless query param specific, simplified here) -->
                            <div class="mt-5">
                                <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-pill shadow-sm d-flex justify-content-center align-items-center gap-2" style="font-size: 1.05rem;">
                                    Create My Account <i class="bi bi-person-check fs-4"></i>
                                </button>
                            </div>
                        </form>

                        <div class="text-center mt-4 pt-4 border-top border-light">
                            <p class="text-muted mb-0 small">Already a member? <br><a href="/login" class="text-primary fw-bold text-decoration-none fs-6 d-inline-block mt-1">Sign In</a></p>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-5">
                    <a href="/" class="text-muted text-decoration-none small fw-bold transition-all btn btn-white rounded-pill px-4 shadow-sm bg-white border-0">
                        <i class="bi bi-house-door me-2"></i> Back to Home
                    </a>
                </div>
                
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
