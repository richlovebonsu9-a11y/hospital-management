<?php
// Main entry point for the PHP application
session_start();

$page = $_GET['page'] ?? 'home';
$title = "Kobby Moore Hospital - " . ucfirst($page);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'components/navbar.php'; ?>

    <main style="margin-top: 100px;">
        <!-- Hero Section -->
        <section class="hero-section">
            <div class="hero-blob"></div>
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-5 mb-5 mb-lg-0">
                        <div class="hero-card shadow-lg">
                            <span class="hero-badge">Health is Wealth</span>
                            <h1 class="display-4 fw-bold mb-4">The Best Medical and Treatment Center for You</h1>
                            <p class="mb-4 opacity-75">Kobby Moore Hospital (Kobby Moore Hospital) provides state-of-the-art care with automated emergency response and digital medical records.</p>
                            <div class="d-grid d-md-block">
                                <a href="/signup" class="btn btn-light btn-lg rounded-pill px-5 py-3 fw-bold text-primary">Book online &rarr;</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7 text-center">
                        <img src="/assets/img/doctor_hero.png" alt="Doctor" class="img-fluid rounded-4 shadow-lg" style="max-height: 500px; object-fit: cover; width: auto;">
                    </div>
                </div>
            </div>
        </section>

        <!-- Stats Section (About Our Center) -->
        <section id="about" class="py-5 bg-white">
            <div class="container py-5">
                <div class="row align-items-center mb-5">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <img src="https://images.unsplash.com/photo-1512678080530-7760d81faba6?ixlib=rb-4.0.3&auto=format&fit=crop&w=600&q=80" alt="Clinic Interior" class="img-fluid rounded-5 shadow-sm">
                    </div>
                    <div class="col-md-6 ps-lg-5">
                        <h6 class="text-primary fw-bold text-uppercase mb-3">About Our Center</h6>
                        <h2 class="display-5 fw-bold mb-4">Kobby Moore Hospital</h2>
                        <p class="text-muted mb-5">We are committed to providing the highest quality healthcare services in Ghana. Our system integrates digital addresses via GhanaPostGPS for rapid emergency response and offers optional NHIS membership management for seamless billing.</p>
                        
                        <div class="row g-4 text-center">
                            <div class="col-4">
                                <div class="stat-number">600+</div>
                                <div class="text-muted small">Daily Patients</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-number">50+</div>
                                <div class="text-muted small">Specialists</div>
                            </div>
                            <div class="col-4">
                                <div class="stat-number">24/7</div>
                                <div class="text-muted small">Emergency</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Specialists Section -->
        <section id="specialists" class="py-5">
            <div class="container py-5 text-center">
                <h6 class="text-primary fw-bold text-uppercase mb-3">Meet Our</h6>
                <h2 class="display-5 fw-bold mb-5">Expert Specialists</h2>
                
                <div class="row g-4 mt-2">
                    <div class="col-md-3">
                        <div class="specialist-card">
                            <img src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Specialist" class="specialist-img mb-4">
                            <h5 class="fw-bold mb-1">Dr. Michael Anderson</h5>
                            <p class="text-primary small fw-bold">Chief Medical Officer</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="specialist-card">
                            <img src="https://images.unsplash.com/photo-1594824476967-48c8b964273f?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Specialist" class="specialist-img mb-4">
                            <h5 class="fw-bold mb-1">Dr. Sarah Mensah</h5>
                            <p class="text-primary small fw-bold">Pediatrician</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="specialist-card">
                            <img src="https://images.unsplash.com/photo-1537368910025-700350fe46c7?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Specialist" class="specialist-img mb-4">
                            <h5 class="fw-bold mb-1">Dr. Robert Asante</h5>
                            <p class="text-primary small fw-bold">Cardiologist</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="specialist-card">
                            <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Specialist" class="specialist-img mb-4">
                            <h5 class="fw-bold mb-1">Dr. Emily Owusu</h5>
                            <p class="text-primary small fw-bold">Surgeon</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-5">
                    <a href="/specialists" class="btn btn-primary px-5 py-3 rounded-pill fw-bold">See All Doctors &rarr;</a>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-white py-5 mt-auto border-top">
        <div class="container text-center">
            <h4 class="fw-bold text-primary mb-4">Kobby Moore Hospital</h4>
            <div class="mb-4">
                <a href="#" class="nav-link d-inline mx-2 text-muted">Terms</a>
                <a href="#" class="nav-link d-inline mx-2 text-muted">Privacy</a>
                <a href="#" class="nav-link d-inline mx-2 text-muted">Contact</a>
            </div>
            <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> Kobby Moore Hospital. Aligned with Data Protection Act 2012.</p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
