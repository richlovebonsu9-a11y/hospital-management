<?php
// Specialists Directory - K.M. General Hospital
session_start();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
$loggedIn = isset($_SESSION['user']);
$role = $loggedIn ? ($SESSION['user']['user_metadata']['role'] ?? '') : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Specialist Doctors - K.M. General Hospital</title>
    <meta name="description" content="Meet the expert specialist doctors at K.M. General Hospital — Cardiology, Pediatrics, Surgery, Maternity and more.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #f8fafc; }
        .specialist-card {
            background: white;
            border-radius: 20px;
            padding: 28px 20px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
        }
        .specialist-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.12);
        }
        .specialist-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #e0f2fe;
        }
        .department-badge {
            display: inline-block;
            background: #eff6ff;
            color: #2563eb;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 0.78rem;
            font-weight: 600;
            margin-bottom: 10px;
        }
        .filter-btn { border-radius: 50px; padding: 8px 22px; font-weight: 600; transition: all 0.2s; }
        .filter-btn.active, .filter-btn:hover { background: #2563eb; color: white; border-color: #2563eb; }
        .hero-section { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); color: white; padding: 80px 0; }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary fs-4" href="/">K.M. General Hospital</a>
            <div class="d-flex gap-2 ms-auto">
                <?php if ($loggedIn): ?>
                    <a href="/dashboard" class="btn btn-primary rounded-pill px-4">My Dashboard</a>
                <?php else: ?>
                    <a href="/login" class="btn btn-outline-primary rounded-pill px-4">Sign In</a>
                    <a href="/signup" class="btn btn-primary rounded-pill px-4">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="hero-section">
        <div class="container text-center">
            <h6 class="text-uppercase fw-bold opacity-75 mb-3 ls-wide">K.M. General Hospital Medical Team</h6>
            <h1 class="display-4 fw-bold mb-3">Our Expert Specialists</h1>
            <p class="lead opacity-75 mb-0">World-class specialists dedicated to your health and wellbeing</p>
        </div>
    </section>

    <!-- Filter Bar -->
    <section class="bg-white py-4 shadow-sm">
        <div class="container">
            <div class="d-flex flex-wrap gap-2 justify-content-center" id="filterBar">
                <button class="btn btn-outline-primary filter-btn active" data-dept="all">All Specialties</button>
                <button class="btn btn-outline-primary filter-btn" data-dept="cardiology">Cardiology</button>
                <button class="btn btn-outline-primary filter-btn" data-dept="pediatrics">Pediatrics</button>
                <button class="btn btn-outline-primary filter-btn" data-dept="surgery">Surgery</button>
                <button class="btn btn-outline-primary filter-btn" data-dept="maternity">Maternity</button>
                <button class="btn btn-outline-primary filter-btn" data-dept="neurology">Neurology</button>
                <button class="btn btn-outline-primary filter-btn" data-dept="opd">General OPD</button>
            </div>
        </div>
    </section>

    <!-- Specialists Grid -->
    <section class="py-5">
        <div class="container">
            <div class="row g-4" id="specialistsGrid">

                <!-- Cardiology -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="cardiology">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1537368910025-700350fe46c7?w=400&q=80" alt="Dr. Robert Asante" class="specialist-img mb-3">
                        <span class="department-badge">Cardiology</span>
                        <h5 class="fw-bold mb-1">Dr. Robert Asante</h5>
                        <p class="text-muted small mb-3">Senior Cardiologist · 14 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Robert%20Asante&dept=Cardiology" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- Pediatrics -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="pediatrics">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1594824476967-48c8b964273f?w=400&q=80" alt="Dr. Sarah Mensah" class="specialist-img mb-3">
                        <span class="department-badge">Pediatrics</span>
                        <h5 class="fw-bold mb-1">Dr. Sarah Mensah</h5>
                        <p class="text-muted small mb-3">Consultant Pediatrician · 10 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Sarah%20Mensah&dept=Pediatrics" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- Surgery -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="surgery">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1622253692010-333f2da6031d?w=400&q=80" alt="Dr. Martin Owusu" class="specialist-img mb-3">
                        <span class="department-badge">Surgery</span>
                        <h5 class="fw-bold mb-1">Dr. Martin Owusu</h5>
                        <p class="text-muted small mb-3">General Surgeon · 12 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Martin%20Owusu&dept=Surgery" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- General OPD -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="opd">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=400&q=80" alt="Dr. Michael Anderson" class="specialist-img mb-3">
                        <span class="department-badge">General OPD</span>
                        <h5 class="fw-bold mb-1">Dr. Michael Anderson</h5>
                        <p class="text-muted small mb-3">Chief Medical Officer · 18 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Michael%20Anderson&dept=General%20OPD" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- Maternity -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="maternity">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&q=80" alt="Dr. Abena Boateng" class="specialist-img mb-3">
                        <span class="department-badge">Maternity</span>
                        <h5 class="fw-bold mb-1">Dr. Abena Boateng</h5>
                        <p class="text-muted small mb-3">Obstetrician & Gynaecologist · 9 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Abena%20Boateng&dept=Maternity" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- Neurology -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="neurology">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1582750433449-648ed127bb54?w=400&q=80" alt="Dr. Kwame Ofori" class="specialist-img mb-3">
                        <span class="department-badge">Neurology</span>
                        <h5 class="fw-bold mb-1">Dr. Kwame Ofori</h5>
                        <p class="text-muted small mb-3">Consultant Neurologist · 11 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Kwame%20Ofori&dept=Neurology" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- Cardiology 2 -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="cardiology">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?w=400&q=80" alt="Dr. James Appiah" class="specialist-img mb-3">
                        <span class="department-badge">Cardiology</span>
                        <h5 class="fw-bold mb-1">Dr. James Appiah</h5>
                        <p class="text-muted small mb-3">Interventional Cardiologist · 16 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20James%20Appiah&dept=Cardiology" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

                <!-- Pediatrics 2 -->
                <div class="col-md-4 col-lg-3 specialist-item" data-dept="pediatrics">
                    <div class="specialist-card">
                        <img src="https://images.unsplash.com/photo-1651008376811-b90baee60c1f?w=400&q=80" alt="Dr. Grace Tetteh" class="specialist-img mb-3">
                        <span class="department-badge">Pediatrics</span>
                        <h5 class="fw-bold mb-1">Dr. Grace Tetteh</h5>
                        <p class="text-muted small mb-3">Neonatologist · 8 yrs exp.</p>
                        <a href="/appointments_book.php?doctor=Dr.%20Grace%20Tetteh&dept=Pediatrics" class="btn btn-outline-primary btn-sm rounded-pill px-4">Book Appointment</a>
                    </div>
                </div>

            </div>

            <!-- CTA -->
            <div class="text-center mt-5 pt-3">
                <div class="card border-0 shadow-sm p-5 rounded-5" style="background: linear-gradient(135deg, #eff6ff, #f0fdf4);">
                    <h3 class="fw-bold mb-2">Ready to see a specialist?</h3>
                    <p class="text-muted mb-4">Create a free patient account and book your appointment today.</p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="/appointments_book.php" class="btn btn-primary rounded-pill px-5 py-2 fw-bold">Book Now</a>
                        <a href="/" class="btn btn-outline-secondary rounded-pill px-5 py-2">Back to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-white py-4 border-top mt-5">
        <div class="container text-center text-muted small">
            &copy; <?php echo date('Y'); ?> K.M. General Hospital &mdash; Greater Good Hospital Management System. All rights reserved.
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.querySelectorAll('#filterBar .filter-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('#filterBar .filter-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                const dept = btn.getAttribute('data-dept');
                document.querySelectorAll('.specialist-item').forEach(item => {
                    if (dept === 'all' || item.getAttribute('data-dept') === dept) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
