<?php
// Panggil session_start() jika belum dimulai.
// Ini diletakkan di config/database.php, jadi seharusnya sudah berjalan.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Cek apakah user sudah login. Jika tidak, redirect ke halaman login.
if (!isset($_SESSION['user_id'])) {
    // Simpan URL yang diminta agar bisa redirect kembali setelah login
    // $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

/**
 * Fungsi untuk memverifikasi apakah pengguna memiliki peran (role) yang diizinkan.
 * Jika tidak, redirect ke halaman utama mereka atau halaman login.
 *
 * @param array $allowed_roles Array dari role yang diizinkan, contoh: ['admin', 'waka']
 */
function authorize_role(array $allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        // Jika role tidak diizinkan, redirect ke dashboard default mereka
        // atau ke halaman login jika terjadi sesuatu yang aneh.
        $role = $_SESSION['role'] ?? '';
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
            default:
                header('Location: ' . BASE_URL . 'logout.php');
                break;
        }
        exit();
    }
}
?>