<?php
// Login page implementation
session_start();
if (isset($_SESSION['user'])) {
    header('Location: /dashboard');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center py-5 min-vh-100 w-100" style="background-color: var(--bg-light); position: relative; overflow-x: hidden;">
    <!-- Vibrant Background Elements -->
    <div class="position-absolute" style="top: -15%; right: -10%; width: 60vw; height: 60vw; background: radial-gradient(circle, var(--theme-blue-soft) 0%, transparent 70%); z-index: 0; animation: pulse-blue 15s infinite alternate;"></div>
    <div class="position-absolute" style="bottom: -20%; left: -10%; width: 70vw; height: 70vw; background: radial-gradient(circle, var(--theme-green-soft) 0%, transparent 70%); z-index: 0; animation: pulse-blue 12s infinite alternate-reverse;"></div>

    <div class="container position-relative w-100" style="z-index: 1;">
        <div class="row justify-content-center w-100 mx-0">
            <div class="col-lg-10 col-xl-9">
                
                <!-- Split Login Card -->
                <div class="card border-0 rounded-5 overflow-hidden shadow-lg bg-white d-flex flex-column flex-lg-row" style="transform: translateY(0); transition: all 0.4s ease;">
                    
                    <!-- Left Side: Promotional Message -->
                    <div class="col-lg-5 text-white p-5 d-flex flex-column justify-content-center position-relative" style="background: linear-gradient(135deg, var(--theme-blue) 0%, var(--theme-green) 100%);">
                        <!-- Subtle Pattern Overlay -->
                        <div class="position-absolute top-0 start-0 w-100 h-100 opacity-25" style="background-image: radial-gradient(circle at 2px 2px, rgba(255,255,255,0.4) 1px, transparent 0); background-size: 24px 24px; pointer-events: none;"></div>
                        
                        <div class="position-relative z-1 pe-lg-4">
                            <div class="d-inline-flex align-items-center justify-content-center bg-white bg-opacity-25 rounded-4 mb-4 shadow-sm" style="width: 72px; height: 72px; backdrop-filter: blur(8px);">
                                <i class="bi bi-shield-check fs-1 text-white"></i>
                            </div>
                            <h2 class="fw-bold mb-4 display-6" style="line-height: 1.25; letter-spacing: -1px;">Welcome Back to Premium Care.</h2>
                            <p class="fs-4 mb-0 opacity-100" style="font-weight: 300; letter-spacing: 0.5px;">Log in to access your dashboard.</p>
                        </div>
                    </div>

                    <!-- Right Side: Login Form -->
                    <div class="col-lg-7 p-4 p-md-5">
                        <div class="text-center mb-5 d-lg-none">
                            <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.5px;">Welcome Back</h3>
                            <p class="text-muted small">Login to K.M. General Hospital</p>
                        </div>
                        <div class="text-start mb-4 d-none d-lg-block">
                            <h3 class="fw-bold text-dark mb-1" style="letter-spacing: -0.5px;">Sign In</h3>
                            <p class="text-muted small">Access your K.M. General Hospital account.</p>
                        </div>
                        
                        <?php if (isset($_GET['error'])): ?>
                            <div class="alert alert-danger bg-danger text-white border-0 rounded-4 mb-4 small fw-bold shadow-sm d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> 
                                <?php echo htmlspecialchars($_GET['error'] === 'invalid_grant' ? 'Invalid email or password.' : $_GET['error']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form action="/api/auth/login" method="POST" autocomplete="off">
                            <div class="row g-3 g-md-4">
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Email Address</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-envelope"></i></span>
                                        <input type="email" name="email" class="form-control border-0 bg-white" required placeholder="" autocomplete="off" style="box-shadow: none;">
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold text-muted text-uppercase" style="letter-spacing: 1px;">Password</label>
                                    <div class="input-group input-group-lg shadow-sm rounded-4 overflow-hidden border border-light">
                                        <span class="input-group-text bg-white border-0 text-muted px-3"><i class="bi bi-shield-lock"></i></span>
                                        <input type="password" name="password" class="form-control border-0 bg-white" required placeholder="" autocomplete="new-password" style="box-shadow: none;">
                                    </div>
                                </div>
                            </div>
                            <div class="mt-4 pt-2">
                                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow-sm d-flex justify-content-center align-items-center gap-2" style="font-size: 1.05rem;">
                                    Sign In <i class="bi bi-arrow-right fs-4"></i>
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4 pt-4 border-top border-light">
                            <p class="text-muted mb-0 small">New to K.M. General Hospital? <br><a href="/signup" class="text-success fw-bold text-decoration-none fs-6 d-inline-block mt-1">Create Account</a></p>
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
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
