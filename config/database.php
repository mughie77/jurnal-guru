<?php
// Set Timezone to UTC+7 (Asia/Jakarta)
date_default_timezone_set('Asia/Jakarta');

// --- Security Headers to Prevent Deface, Clickjacking & XSS ---
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");

// --- Mulai Session dengan Pengaturan Aman ---
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    ini_set('session.gc_maxlifetime', 30 * 24 * 60 * 60);
    session_start();
}

// --- Anti DDOS / Rate Limiting (Maksimal 150 request per menit per session) ---
if (isset($_SESSION)) {
    if (!isset($_SESSION['req_count'])) {
        $_SESSION['req_count'] = 0;
        $_SESSION['req_start_time'] = time();
    }
    $_SESSION['req_count']++;
    if (time() - $_SESSION['req_start_time'] > 60) {
        $_SESSION['req_count'] = 1;
        $_SESSION['req_start_time'] = time();
    }
    if ($_SESSION['req_count'] > 150) {
        http_response_code(429);
        die("<h1>429 Too Many Requests</h1><p>Terlalu banyak permintaan (Spam/DDOS Terdeteksi). Mohon tunggu beberapa saat sebelum mencoba kembali.</p>");
    }
}

// --- Anti Session Hijacking (Kunci Session ke User Agent) ---
if (isset($_SESSION['user_id'])) {
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        // Kemungkinan Session di-hijack/cloning. Hancurkan session untuk keamanan!
        session_unset();
        session_destroy();
        header('Location: ' . (defined('BASE_URL') ? BASE_URL : '/login.php') . '?error=session_hijacked');
        exit();
    }
}

// --- Koneksi Database ---
$db_host = 'localhost';
$db_user = 'root'; // Sesuaikan dengan username database Anda
$db_pass = ''; // Sesuaikan dengan password database Anda
$db_name = 'jurnal_mengajar';

try {
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
} catch (mysqli_sql_exception $e) {
    $conn = false;
}

if (!$conn) {
    include __DIR__ . '/setup_db.php';
    exit();
}

// Set timezone untuk session koneksi database ke Asia/Jakarta (GMT+7)
mysqli_query($conn, "SET time_zone = '+07:00'");

// --- URL Konfigurasi ---
// Jika aplikasi dipindah folder atau dihosting, isi URL dasar di sini (akhiri dengan slash /)
// Contoh: $base_url_config = 'http://localhost/jurnal/';
$base_url_config = '';

if (!empty($base_url_config)) {
    define('BASE_URL', $base_url_config);
} else {
    // Deteksi URL dasar secara otomatis
    $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')) ? "https://" : "http://";
    $domainName = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'];

    // Cari path root aplikasi (naik dari folder core jika perlu)
    $app_root_path = str_replace(['/admin', '/guru', '/waka', '/api', '/siswa', '/error'], '/', dirname($scriptName));

    // Normalisasi path agar selalu diakhiri dengan satu slash
    $app_root_path = str_replace('\\', '/', $app_root_path);
    $app_root_path = rtrim($app_root_path, '/') . '/';

    define('BASE_URL', $protocol . $domainName . $app_root_path);
}

// Session sudah dimulai di bagian atas dengan proteksi keamanan.

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