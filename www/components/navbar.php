<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
// Shared Navbar - K.M. General Hospital
$user_logged_in = isset($_SESSION['user']);
$user_role = $_SESSION['user']['user_metadata']['role'] ?? 'patient';
?>
<nav class="navbar navbar-expand-lg navbar-light fixed-top shadow-sm bg-white py-3">
    <div class="container">
        <a class="navbar-brand fw-800 d-flex align-items-center" href="/" style="letter-spacing: -0.5px;">
            <img src="/assets/img/logo.png" alt="KMG Logo" style="width: 38px; height: 38px; object-fit: contain;" class="me-2 rounded-3 shadow-sm">
            <span class="text-dark">K.M. General <span class="text-primary">Hospital</span></span>
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMain">
            <ul class="navbar-nav ms-auto align-items-center">
                <li class="nav-item"><a class="nav-link px-3" href="/">Home</a></li>
                <?php if ($user_logged_in): ?>
                    <li class="nav-item"><a class="nav-link px-3" href="/dashboard.php">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link px-3" href="/profile.php">Profile</a></li>
                    <li class="nav-item ms-lg-3">
                        <a href="/api/auth/logout.php" class="btn btn-outline-danger rounded-pill px-4">Logout</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link px-3" href="/login.php">Login</a></li>
                    <li class="nav-item ms-lg-3">
                        <a href="/signup.php" class="btn btn-primary rounded-pill px-4 shadow-sm">Sign Up</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div style="margin-top: 85px;"></div>
