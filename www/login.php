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
    <title>Login - Kobby Moore Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="d-flex align-items-center min-vh-100" style="background-color: var(--bg-light); position: relative; overflow-x: hidden;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5">
                <div class="card border-0 shadow-lg rounded-5 overflow-hidden glass p-4">
                    <div class="card-body p-4">
                        <div class="text-center mb-5">
                            <div class="bg-primary rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" style="width: 60px; height: 60px;">
                                <div class="bg-white rounded-circle" style="width: 20px; height: 20px;"></div>
                            </div>
                            <h2 class="fw-bold text-secondary">Welcome Back</h2>
                            <p class="text-muted">Login to Kobby Moore Hospital Dashboard</p>
                        </div>
                        <form action="/api/auth/login" method="POST" autocomplete="off">
                            <div class="mb-4">
                                <label class="form-label fw-600">Email Address</label>
                                <input type="email" name="email" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="name@hospital.com" autocomplete="off">
                            </div>
                            <div class="mb-5">
                                <label class="form-label fw-600">Password</label>
                                <input type="password" name="password" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="••••••••" autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-pill shadow">Sign In &rarr;</button>
                        </form>
                        <div class="text-center mt-5">
                            <p class="text-muted mb-0">New to Kobby Moore Hospital? <a href="/signup" class="text-primary fw-bold text-decoration-none">Create Account</a></p>
                        </div>
                    </div>
                </div>
                <div class="text-center mt-4">
                    <a href="/" class="text-white opacity-75 text-decoration-none small fw-bold">&larr; Back to Home</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
