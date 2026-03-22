<?php
// Emergency Now Page - GGHMS
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EMERGENCY NOW - GGHMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        .emergency-header { background: #dc3545; color: white; padding: 40px 0; }
        .severity-btn { transition: all 0.2s; border: 4px solid transparent; cursor: pointer; }
        .severity-btn:hover { transform: translateY(-5px); }
        input[name="severity"]:checked + .severity-btn { border-color: white; transform: scale(1.1); box-shadow: 0 10px 20px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="bg-light">
    <div class="emergency-header text-center">
        <div class="container">
            <h1 class="display-3 fw-bold mb-3"><i class="bi bi-exclamation-triangle-fill"></i> EMERGENCY NOW</h1>
            <p class="lead mb-0">Help is on the way. Please provide details below.</p>
        </div>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card border-0 shadow-lg rounded-5 p-4">
                    <form action="/api/emergency/report.php" method="POST">
                        <div class="mb-5 text-center">
                            <label class="form-label fw-bold h5 mb-3">Select Severity</label>
                            <div class="d-flex justify-content-center gap-3">
                                <label>
                                    <input type="radio" name="severity" value="medium" class="opacity-0 position-absolute" style="width:0; height:0;" required>
                                    <div class="severity-btn bg-warning p-4 rounded-4 text-white text-center" style="width: 120px;">
                                        <i class="bi bi-heart-pulse h1"></i>
                                        <div class="fw-bold mt-2 text-uppercase">Medium</div>
                                    </div>
                                </label>
                                <label>
                                    <input type="radio" name="severity" value="high" class="opacity-0 position-absolute" style="width:0; height:0;">
                                    <div class="severity-btn bg-orange p-4 rounded-4 text-white text-center" style="width: 120px; background: #fd7e14;">
                                        <i class="bi bi-activity h1"></i>
                                        <div class="fw-bold mt-2 text-uppercase">High</div>
                                    </div>
                                </label>
                                <label>
                                    <input type="radio" name="severity" value="critical" class="opacity-0 position-absolute" style="width:0; height:0;">
                                    <div class="severity-btn bg-danger p-4 rounded-4 text-white text-center" style="width: 120px;">
                                        <i class="bi bi-shield-fill-exclamation h1"></i>
                                        <div class="fw-bold mt-2 text-uppercase">Critical</div>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">Primary Symptoms</label>
                            <textarea name="symptoms" class="form-control rounded-4 p-4 border-light bg-light" rows="3" placeholder="Chest pain, difficulty breathing, car accident, etc." required></textarea>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-bold">GhanaPostGPS Address</label>
                            <input type="text" name="ghana_post_gps" class="form-control rounded-pill px-4 py-3 border-light bg-light" required placeholder="AK-485-9323" value="<?php echo $_SESSION['user']['user_metadata']['ghana_post_gps'] ?? ''; ?>">
                            <small class="text-muted mt-2 d-block">This is mandatory for rapid response accuracy in Ghana.</small>
                        </div>

                        <div class="d-grid mt-5">
                            <button type="submit" class="btn btn-danger btn-lg py-3 fw-bold rounded-pill shadow-lg">REQUEST IMMEDIATE DISPATCH &rarr;</button>
                        </div>
                    </form>
                </div>
                <div class="text-center mt-4">
                    <a href="/dashboard" class="text-muted text-decoration-none small">&larr; Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
