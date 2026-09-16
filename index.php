<?php
require_once 'config/database.php';

// Cek apakah user sudah login
if (!isset($_SESSION['user_id'])) {
    // Jika belum, redirect ke halaman login
    // Gunakan file login.php secara langsung untuk menghindari masalah redirect loop jika .htaccess belum aktif
    if (file_exists('login.php')) {
        include 'login.php';
    } else {
        header('Location: ' . BASE_URL . 'login');
    }
    exit();
}

// Jika sudah login, redirect berdasarkan role
$role = $_SESSION['role'];

switch ($role) {
    case 'admin':
        header('Location: ' . BASE_URL . 'admin/');
        break;
    case 'waka':
        header('Location: ' . BASE_URL . 'waka/');
        break;
    case 'guru':
        header('Location: ' . BASE_URL . 'guru/');
        break;
    case 'dudi':
        header('Location: ' . BASE_URL . 'dudi/');
        break;
    case 'siswa':
        header('Location: ' . BASE_URL . 'siswa/');
        break;
    default:
        header('Location: ' . BASE_URL . 'logout');
        break;
}
exit();
?>
