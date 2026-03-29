<?php
// Main entry point for the PHP application
session_start();

$page = $_GET['page'] ?? 'home';
$title = "K.M. General Hospital - Your Health, Our Priority";

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Google Fonts: Montserrat -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #2563EB 0%, #3B82F6 100%);
            --secondary-gradient: linear-gradient(135deg, #10B981 0%, #34D399 100%);
            --glass-bg: rgba(255, 255, 255, 0.9);
            --glass-border: rgba(255, 255, 255, 0.4);
        }

        body {
            font-family: 'Montserrat', sans-serif !important;
            background-color: #F8FAFC;
            color: #1E293B;
            overflow-x: hidden;
        }

        /* Hero Refinement */
        .hero-section {
            padding: 140px 0 100px;
            position: relative;
            background: radial-gradient(circle at top right, rgba(59, 130, 246, 0.05), transparent 400px),
                        radial-gradient(circle at bottom left, rgba(16, 185, 129, 0.05), transparent 400px);
        }

        .hero-card-modern {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: 2.5rem;
            padding: 3.5rem;
            box-shadow: 0 40px 100px rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 2;
        }

        .hero-image-container {
            position: relative;
            z-index: 1;
        }

        .hero-image-container::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 110%;
            height: 110%;
            background: var(--primary-gradient);
            border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%;
            opacity: 0.1;
            z-index: -1;
            animation: morph 20s linear infinite;
        }

        @keyframes morph {
            0%, 100% { border-radius: 30% 70% 70% 30% / 30% 30% 70% 70%; }
            25% { border-radius: 58% 42% 75% 25% / 76% 46% 54% 24%; }
            50% { border-radius: 50% 50% 33% 67% / 55% 27% 73% 45%; }
            75% { border-radius: 33% 67% 58% 42% / 63% 68% 32% 37%; }
        }

        /* Service Cards */
        .service-card {
            background: white;
            border-radius: 2rem;
            padding: 2.5rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.03);
            height: 100%;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.06);
            border-color: var(--theme-blue-light);
        }

        .service-icon {
            width: 70px;
            height: 70px;
            background: var(--theme-blue-soft);
            color: var(--theme-blue);
            border-radius: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .service-card:hover .service-icon {
            background: var(--theme-blue);
            color: white;
            transform: rotate(-10deg) scale(1.1);
        }

        /* Specialist Cards */
        .specialist-card-modern {
            background: white;
            border-radius: 2.5rem;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.02);
        }

        .specialist-card-modern:hover {
            transform: translateY(-8px);
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.08);
        }

        .specialist-image-box {
            position: relative;
            overflow: hidden;
            height: 320px;
        }

        .specialist-img-modern {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.6s ease;
        }

        .specialist-card-modern:hover .specialist-img-modern {
            transform: scale(1.1);
        }

        .specialist-info {
            padding: 2rem;
            text-align: center;
        }

        /* Floating Stats */
        .floating-stats {
            background: white;
            border-radius: 1.5rem;
            padding: 1.5rem 2rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 1.5rem;
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            z-index: 10;
        }

        .stat-item {
            text-align: center;
        }
        .stat-val { font-weight: 800; color: var(--theme-blue); font-size: 1.25rem; }
        .stat-lbl { color: #64748B; font-size: 0.75rem; text-uppercase: tracking-wider; font-weight: 600; }

        /* Section Spacing */
        .section-padding { padding: 120px 0; }
        
        .badge-soft {
            padding: 0.5rem 1.2rem;
            border-radius: 2rem;
            font-size: 0.85rem;
            font-weight: 700;
            background: var(--theme-blue-soft);
            color: var(--theme-blue);
            display: inline-block;
            margin-bottom: 1.5rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
    </style>
</head>
<body>
    <?php include 'components/navbar.php'; ?>

    <main>
        <!-- MODERN HERO SECTION -->
        <section class="hero-section">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-5 mb-lg-0 order-2 order-lg-1">
                        <div class="hero-card-modern shadow-xl">
                            <span class="badge-soft"><i class="bi bi-shield-check me-2"></i>Health First</span>
                            <h1 class="display-3 fw-900 text-dark mb-4" style="line-height: 1.1;">Advanced Care <br><span class="text-primary">Beautifully Delivered.</span></h1>
                            <p class="lead text-muted mb-5" style="font-size: 1.15rem; line-height: 1.8;">K.M. General Hospital (KMGH) merges state-of-the-art medical technology with high-touch clinical excellence. We offer automated emergency response and paperless medical records for a seamless health journey.</p>
                            
                            <div class="d-flex flex-wrap gap-3">
                                <a href="/signup" class="btn btn-primary btn-lg rounded-pill px-5 py-3 shadow-lg">Start Journey &rarr;</a>
                                <a href="#services" class="btn btn-outline-primary btn-lg rounded-pill px-5 py-3">Our Services</a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 order-1 order-lg-2 mb-5 mb-lg-0">
                        <div class="hero-image-container text-center">
                            <img src="/assets/img/doctor_hero.png" alt="Doctor Hero" class="img-fluid rounded-5 shadow-2xl" style="max-height: 600px; transform: rotate(-2deg);">
                            
                            <div class="floating-stats d-none d-md-flex">
                                <div class="stat-item">
                                    <div class="stat-val">24/7</div>
                                    <div class="stat-lbl">Rescue</div>
                                </div>
                                <div class="vr"></div>
                                <div class="stat-item">
                                    <div class="stat-val">50+</div>
                                    <div class="stat-lbl">Doctors</div>
                                </div>
                                <div class="vr"></div>
                                <div class="stat-item">
                                    <div class="stat-val">10k+</div>
                                    <div class="stat-lbl">Patients</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- SERVICE HUB -->
        <section id="services" class="section-padding bg-white">
            <div class="container">
                <div class="text-center mb-5 pb-4">
                    <span class="badge-soft">Medical Excellence</span>
                    <h2 class="display-5 fw-800">Our Comprehensive Services</h2>
                    <div class="mx-auto mt-3" style="width: 60px; height: 5px; background: var(--primary-gradient); border-radius: 10px;"></div>
                </div>
                
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="service-card shadow-sm">
                            <div class="service-icon">
                                <i class="bi bi-heart-pulse"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Emergency Care</h4>
                            <p class="text-muted mb-0">Automated 24/7 dispatch system pinpointing your exact location for rapid medical intervention.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="service-card shadow-sm">
                            <div class="service-icon">
                                <i class="bi bi-file-earmark-medical"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Smart EMR</h4>
                            <p class="text-muted mb-0">Secure, digital access to your entire medical history, prescriptions, and lab results in real-time.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="service-card shadow-sm">
                            <div class="service-icon">
                                <i class="bi bi-capsule"></i>
                            </div>
                            <h4 class="fw-bold mb-3">Modern Pharmacy</h4>
                            <p class="text-muted mb-0">Automated inventory and prescription fulfillment ensuring you get the right meds at the right time.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ABOUT SECTION -->
        <section id="about" class="section-padding">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-lg-6 mb-5 mb-lg-0">
                        <div class="position-relative pe-lg-5">
                            <img src="https://images.unsplash.com/photo-1512678080530-7760d81faba6?ixlib=rb-4.0.3&auto=format&fit=crop&w=800&q=80" alt="Clinic Interior" class="img-fluid rounded-5 shadow-lg">
                            <div class="bg-primary text-white p-4 rounded-4 shadow-lg position-absolute d-none d-md-block" style="bottom: -30px; right: 20px; width: 220px;">
                                <h3 class="fw-900 mb-0">12+ Years</h3>
                                <p class="small mb-0 opacity-75">Of Clinical Excellence</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 ps-lg-5">
                        <span class="badge-soft">About Our Center</span>
                        <h2 class="display-5 fw-800 mb-4">Patient-Centric Health Solutions</h2>
                        <p class="text-secondary mb-5" style="font-size: 1.1rem; line-height: 1.8;">We are committed to providing the highest quality healthcare services in Ghana. Our system integrates digital addresses via GhanaPostGPS for rapid emergency response and offers optional NHIS membership management for seamless billing. We believe that technology should empower care, not replace it.</p>
                        
                        <div class="row g-4 mb-5">
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-3 p-2 bg-primary-soft text-primary rounded-circle"><i class="bi bi-check-lg fs-5"></i></div>
                                    <span class="fw-bold">Top Specialists</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex align-items-center">
                                    <div class="me-3 p-2 bg-success-soft text-success rounded-circle"><i class="bi bi-check-lg fs-5"></i></div>
                                    <span class="fw-bold">Modern Equipment</span>
                                </div>
                            </div>
                        </div>
                        
                        <a href="/login" class="btn btn-primary px-5 py-3 rounded-pill fw-bold">Learn More About Us</a>
                    </div>
                </div>
            </div>
        </section>

        <!-- SPECIALISTS SECTION -->
        <section id="specialists" class="section-padding bg-white">
            <div class="container">
                <div class="text-center mb-5 pb-4">
                    <span class="badge-soft">Expert Team</span>
                    <h2 class="display-5 fw-800">Our Leading Specialists</h2>
                    <p class="text-muted mx-auto mt-3" style="max-width: 600px;">Meet the dedicated team of experts delivering world-class medical care at K.M. General Hospital.</p>
                </div>
                
                <div class="row g-4 mt-2">
                    <div class="col-md-3">
                        <div class="specialist-card-modern">
                            <div class="specialist-image-box">
                                <img src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Michael Anderson" class="specialist-img-modern">
                            </div>
                            <div class="specialist-info">
                                <h5 class="fw-bold mb-1">Dr. Michael Anderson</h5>
                                <p class="text-primary small fw-bold text-uppercase tracking-wider">Chief Medical Officer</p>
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-linkedin"></i></a>
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-twitter-x"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="specialist-card-modern">
                            <div class="specialist-image-box">
                                <img src="https://images.unsplash.com/photo-1594824476967-48c8b964273f?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Sarah Mensah" class="specialist-img-modern">
                            </div>
                            <div class="specialist-info">
                                <h5 class="fw-bold mb-1">Dr. Sarah Mensah</h5>
                                <p class="text-primary small fw-bold text-uppercase tracking-wider">Pediatrician</p>
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-linkedin"></i></a>
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-twitter-x"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="specialist-card-modern">
                            <div class="specialist-image-box">
                                <img src="https://images.unsplash.com/photo-1537368910025-700350fe46c7?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Robert Asante" class="specialist-img-modern">
                            </div>
                            <div class="specialist-info">
                                <h5 class="fw-bold mb-1">Dr. Robert Asante</h5>
                                <p class="text-primary small fw-bold text-uppercase tracking-wider">Cardiologist</p>
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-linkedin"></i></a>
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-twitter-x"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="specialist-card-modern">
                            <div class="specialist-image-box">
                                <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?ixlib=rb-4.0.3&auto=format&fit=crop&w=400&q=80" alt="Martin Owusu" class="specialist-img-modern">
                            </div>
                            <div class="specialist-info">
                                <h5 class="fw-bold mb-1">Dr. Martin Owusu</h5>
                                <p class="text-primary small fw-bold text-uppercase tracking-wider">Senior Surgeon</p>
                                <div class="d-flex justify-content-center gap-2 mt-3">
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-linkedin"></i></a>
                                    <a href="#" class="btn btn-light btn-sm rounded-circle"><i class="bi bi-twitter-x"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-5 pt-3">
                    <a href="/specialists" class="btn btn-primary px-5 py-3 rounded-pill fw-bold shadow-lg">View All Medical Staff</a>
                </div>
            </div>
        </section>

        <!-- CTA SECTION -->
        <section class="section-padding">
            <div class="container">
                <div class="bg-primary rounded-5 p-5 text-center text-white position-relative overflow-hidden">
                    <div class="position-relative z-1">
                        <h2 class="display-5 fw-900 mb-4">Start Your Personalized <br>Health Journey Today</h2>
                        <p class="mb-5 mx-auto" style="max-width: 600px; opacity: 0.9;">Join K.M. General Hospital and experience healthcare designed for the digital age. Secure, efficient, and deeply human.</p>
                        <a href="/signup" class="btn btn-light btn-lg rounded-pill px-5 py-3 fw-bold text-primary">Get Started Now &rarr;</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-white py-5 mt-auto border-top">
        <div class="container">
            <div class="row g-5 mb-5 align-items-center">
                <div class="col-lg-4 text-center text-lg-start">
                    <a class="navbar-brand fw-bold d-flex align-items-center justify-content-center justify-content-lg-start mb-4" href="/">
                        <img src="/assets/img/logo.png" alt="KMG Logo" style="width: 36px; height: 36px; object-fit: contain;" class="me-2 rounded-3 shadow-sm">
                        <span class="text-dark fs-4">K.M. General Hospital</span>
                    </a>
                    <p class="text-muted small">The Best Medical and Treatment Center in the heart of Ghana. Delivering excellence in care through innovation and dedication.</p>
                </div>
                <div class="col-lg-4 text-center">
                    <div class="d-flex justify-content-center gap-4 mb-4">
                        <a href="#" class="text-muted text-decoration-none">Terms</a>
                        <a href="#" class="text-muted text-decoration-none">Privacy</a>
                        <a href="#" class="text-muted text-decoration-none">Contact</a>
                    </div>
                </div>
                <div class="col-lg-4 text-center text-lg-end">
                    <div class="d-flex justify-content-center justify-content-lg-end gap-3">
                        <a href="#" class="btn btn-outline-primary btn-sm rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-facebook"></i></a>
                        <a href="#" class="btn btn-outline-primary btn-sm rounded-circle" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;"><i class="bi bi-instagram"></i></a>
                    </div>
                </div>
            </div>
            <div class="border-top pt-5 text-center">
                <p class="text-muted small mb-0">&copy; <?php echo date('Y'); ?> K.M. General Hospital. Registered Ghana Medical Association Affiliate.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>

