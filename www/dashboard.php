<?php
// Dashboard Router - K.M. General Hospital
session_start();
if (isset($_COOKIE['sb_user'])) { $_SESSION['user'] = json_decode($_COOKIE['sb_user'], true); }
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
    case 'ambulance':
    case 'dispatch_rider':
        header('Location: /dashboard_staff.php');
        break;
    case 'guardian':
        header('Location: /dashboard_guardian.php');
        break;
    default:
        header('Location: /dashboard_patient.php');
        break;
}
exit;
