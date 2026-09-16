<?php
if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/database.php';
}

// Secure session settings (only if session is not active)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    $session_lifetime = 30 * 24 * 60 * 60; // 30 hari
    ini_set('session.gc_maxlifetime', $session_lifetime);
    session_set_cookie_params([
        'lifetime' => $session_lifetime,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function get_csrf_token() {
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Cek apakah user sudah login. Jika tidak, redirect ke halaman login.
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'login.php');
    exit();
}

// Helper to check if a user is a wali kelas and return their class information
function get_wali_kelas_info() {
    global $conn;
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        return null;
    }
    $user_id = (int)$_SESSION['user_id'];
    // Find teacher (guru) id
    $q_guru = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
    if ($g_data = mysqli_fetch_assoc($q_guru)) {
        $guru_id = (int)$g_data['id'];
        // Check if assigned in kelas
        $q_kelas = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas WHERE wali_kelas_id = $guru_id LIMIT 1");
        if ($k_data = mysqli_fetch_assoc($q_kelas)) {
            return [
                'guru_id' => $guru_id,
                'kelas_id' => (int)$k_data['id'],
                'nama_kelas' => $k_data['nama_kelas']
            ];
        }
    }
    return null;
}

// Helper to check if a user is a BK (Bimbingan Konseling) teacher and return their information
function get_bk_info() {
    global $conn;
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'guru') {
        return null;
    }
    $user_id = (int)$_SESSION['user_id'];
    // Find teacher (guru) id
    $q_guru = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
    if ($g_data = mysqli_fetch_assoc($q_guru)) {
        $guru_id = (int)$g_data['id'];
        // Check if assigned to Bimbingan Konseling or BK mapel
        $q_bk = mysqli_query($conn, "SELECT COUNT(*) as count FROM guru_mapel gm
                                     JOIN mata_pelajaran mp ON gm.mapel_id = mp.id
                                     WHERE gm.guru_id = $guru_id AND (mp.nama_mapel LIKE '%Bimbingan Konseling%' OR mp.nama_mapel LIKE '%BK%')");
        $bk_count = mysqli_fetch_assoc($q_bk)['count'] ?? 0;
        if ($bk_count > 0) {
            return [
                'guru_id' => $guru_id
            ];
        }
    }
    return null;
}

// Role Authorization
function authorize_role(array $allowed_roles) {
    $role = $_SESSION['role'] ?? '';

    // Support "wali_kelas" dynamic role
    $is_wali_kelas = false;
    if (in_array('wali_kelas', $allowed_roles) && $role === 'guru') {
        if (get_wali_kelas_info() !== null) {
            $is_wali_kelas = true;
        }
    }

    // Support "guru_bk" dynamic role
    $is_guru_bk = false;
    if (in_array('guru_bk', $allowed_roles) && $role === 'guru') {
        if (get_bk_info() !== null) {
            $is_guru_bk = true;
        }
    }

    if (!isset($_SESSION['role']) || (!in_array($_SESSION['role'], $allowed_roles) && !$is_wali_kelas && !$is_guru_bk)) {
        switch ($role) {
            case 'admin': header('Location: ' . BASE_URL . 'admin/'); break;
            case 'waka': header('Location: ' . BASE_URL . 'waka/'); break;
            case 'guru': header('Location: ' . BASE_URL . 'guru/'); break;
            case 'dudi': header('Location: ' . BASE_URL . 'dudi/'); break;
            case 'siswa': header('Location: ' . BASE_URL . 'siswa/'); break;
            default: header('Location: ' . BASE_URL . 'logout.php'); break;
        }
        exit();
    }
}
?>
