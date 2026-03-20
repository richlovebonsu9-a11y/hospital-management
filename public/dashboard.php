<?php
// Dashboard Router - GGHMS
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit;
}

$user = $_SESSION['user'];
$role = $user['user_metadata']['role'] ?? 'patient';

switch ($role) {
    case 'doctor':
        header('Location: /dashboard_doctor.php');
        break;
    case 'admin':
        header('Location: /dashboard_admin.php');
        break;
    case 'nurse':
    case 'pharmacist':
    case 'technician':
        header('Location: /dashboard_staff.php');
        break;
    default:
        header('Location: /dashboard_patient.php');
        break;
}
exit;
