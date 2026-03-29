<?php
// Patient Profile - K.M. General Hospital
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$metadata = $user['user_metadata'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - K.M. General Hospital</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="bg-light">
    <?php include 'components/navbar.php'; ?>

    <div class="container py-5 mt-5">
        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card border-0 shadow-sm rounded-5 p-4 text-center">
                    <div class="bg-primary rounded-circle mx-auto mb-3" style="width: 120px; height: 120px;"></div>
                    <h4 class="fw-bold mb-1"><?php echo htmlspecialchars($metadata['name'] ?? 'User'); ?></h4>
                    <p class="text-muted small text-capitalize mb-4"><?php echo htmlspecialchars($metadata['role'] ?? 'patient'); ?></p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary rounded-pill fw-bold">Update Photo</button>
                        <button class="btn btn-outline-secondary rounded-pill">View Records</button>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-5 p-4">
                    <h5 class="fw-bold mb-4">Account Information</h5>
                    <form action="/api/profile/update" method="POST">
                        <div class="row g-4">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control rounded-pill px-4 py-3 border-light bg-light" value="<?php echo htmlspecialchars($metadata['name'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" name="phone" class="form-control rounded-pill px-4 py-3 border-light bg-light" value="<?php echo htmlspecialchars($metadata['phone'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Ghana Card Number (Optional)</label>
                                <input type="text" name="ghana_card" class="form-control rounded-pill px-4 py-3 border-light bg-light" value="<?php echo htmlspecialchars($metadata['ghana_card'] ?? ''); ?>" placeholder="GHA-7xxxx-x">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">NHIS Membership (Optional)</label>
                                <input type="text" name="nhis_membership_number" class="form-control rounded-pill px-4 py-3 border-light bg-light" value="<?php echo htmlspecialchars($metadata['nhis_membership_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label">GhanaPostGPS Digital Address</label>
                                <input type="text" name="ghana_post_gps" class="form-control rounded-pill px-4 py-3 border-light bg-light" value="<?php echo htmlspecialchars($metadata['ghana_post_gps'] ?? ''); ?>" required>
                                <small class="text-muted mt-1">Found on your GhanaPostGPS app. Required for emergency accuracy.</small>
                            </div>
                            <div class="col-md-12 py-3 border-top mt-4">
                                <h6 class="fw-bold mb-3">Clinical Data (Optional)</h6>
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label">Blood Group</label>
                                        <select name="blood_group" class="form-select rounded-pill px-4 py-3 border-light bg-light">
                                            <option value="">Select...</option>
                                            <option value="A+">A+</option>
                                            <option value="A-">A-</option>
                                            <option value="B+">B+</option>
                                            <option value="B-">B-</option>
                                            <option value="AB+">AB+</option>
                                            <option value="AB-">AB-</option>
                                            <option value="O+">O+</option>
                                            <option value="O-">O-</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label class="form-label">Allergies / Chronic Conditions</label>
                                        <textarea name="allergies" class="form-control rounded-4 p-4 border-light bg-light" rows="3"><?php echo htmlspecialchars($metadata['allergies'] ?? ''); ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary rounded-pill px-5 py-3 fw-bold shadow">Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <script src="/assets/js/auto_dismiss.js"></script>
</body>
</html>
