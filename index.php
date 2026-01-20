<?php
// Memulai session dan memuat konfigurasi database
require_once 'config/database.php';

// Periksa apakah pengguna sudah login
if (isset($_SESSION['user_id'])) {
    // Jika sudah, arahkan berdasarkan peran (role)
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
        default:
            // Jika peran tidak dikenali, arahkan ke halaman logout untuk keamanan
            header('Location: ' . BASE_URL . 'logout.php');
            break;
    }
    exit();
} else {
    // Jika belum login, arahkan ke halaman absensi sebagai default
    header('Location: ' . BASE_URL . 'absen.php');
    exit();
}
?>