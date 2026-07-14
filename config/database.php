<?php
// Set Timezone to UTC+7 (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// --- Koneksi Database ---
$db_host = 'localhost';
$db_user = 'root'; // Sesuaikan dengan username database Anda
$db_pass = ''; // Sesuaikan dengan password database Anda
$db_name = 'jurnal_mengajar';

$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn) {
    die("Koneksi Gagal: " . mysqli_connect_error());
}

// --- URL Konfigurasi ---
// Jika aplikasi dipindah folder atau dihosting, isi URL dasar di sini (akhiri dengan slash /)
// Contoh: $base_url_config = 'http://localhost/jurnal/';
$base_url_config = '';

if (!empty($base_url_config)) {
    define('BASE_URL', $base_url_config);
} else {
    // Deteksi URL dasar secara otomatis
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'];

    // Cari path root aplikasi (naik dari folder core jika perlu)
    $app_root_path = str_replace(['/admin', '/guru', '/waka', '/api', '/siswa', '/error'], '/', dirname($scriptName));

    // Normalisasi path agar selalu diakhiri dengan satu slash
    $app_root_path = str_replace('\\', '/', $app_root_path);
    $app_root_path = rtrim($app_root_path, '/') . '/';

    define('BASE_URL', $protocol . $domainName . $app_root_path);
}

// --- Mulai Session ---
// Panggil session_start() di sini agar tersedia di semua halaman
if (session_status() == PHP_SESSION_NONE) {
    // Check for persistent session before starting
    if (isset($_COOKIE[session_name()])) {
        // If we want to support long sessions, we might need to increase gc_maxlifetime
        // Default is usually 1440 (24 mins). Let's set it to 30 days if remember_me was used.
        // But we don't know yet if remember_me was used until session is started.
        ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
    }
    session_start();

    // After starting, if remember_me is set, we can ensure cookie is refreshed if needed
    // though usually browser handles the expiration set during login.
}

// --- Fungsi Helper untuk Tahun Pelajaran Aktif ---
function get_active_tahun_pelajaran_id($conn) {
    $query = "SELECT id FROM tahun_pelajaran WHERE status = 'aktif' LIMIT 1";
    $result = mysqli_query($conn, $query);
    if ($row = mysqli_fetch_assoc($result)) {
        return (int)$row['id'];
    }
    return null; // Mengembalikan null jika tidak ada yang aktif
}

// Ambil dan simpan ID tahun pelajaran aktif dalam variabel global
$active_tahun_id = get_active_tahun_pelajaran_id($conn);
?>