<?php
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
// Deteksi URL dasar secara otomatis
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$domainName = $_SERVER['HTTP_HOST'];
$scriptName = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
// Jika script dipanggil dari root tapi diakses lewat path tertentu (misal API)
$base_url = $protocol . $domainName . $scriptName;
// Pastikan diakhiri dengan slash
if (substr($base_url, -1) !== '/') {
    // Cari posisi folder terakhir jika di dalam subfolder
    $base_url = preg_replace('/[^\/]+\.php$/', '', $base_url);
    if (substr($base_url, -1) !== '/') $base_url .= '/';
}

// Untuk keperluan API atau CLI yang mungkin tidak punya HTTP_HOST
if (empty($domainName)) {
    define('BASE_URL', '/');
} else {
    // Normalisasi: jika kita di dalam folder 'admin' atau 'guru', kita perlu naik ke root
    $base_path = str_replace(['/admin/', '/guru/', '/waka/', '/api/'], '/', $scriptName);
    define('BASE_URL', $protocol . $domainName . $base_path);
}

// --- Mulai Session ---
// Panggil session_start() di sini agar tersedia di semua halaman
if (session_status() == PHP_SESSION_NONE) {
    session_start();
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