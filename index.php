<?php
require_once 'config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    // Jika belum, redirect ke halaman login
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Jika sudah login, redirect berdasarkan role
$role = $_SESSION['role'];

switch ($role) {
    case 'admin':
        header('Location: ' . BASE_URL . 'admin/index.php');
        break;
    case 'waka':
        header('Location: ' . BASE_URL . 'waka/index.php');
        break;
    case 'guru':
        header('Location: ' . BASE_URL . 'guru/index.php');
        break;
    case 'siswa':
        header('Location: ' . BASE_URL . 'siswa/index.php');
        break;
    default:
        // Jika role tidak dikenali, logout dan redirect ke login
        header('Location: ' . BASE_URL . 'logout.php');
        break;
}
exit();
?>