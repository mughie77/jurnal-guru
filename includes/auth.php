<?php
// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_secure', 1);
}

if (session_status() == PHP_SESSION_NONE) {
    // Shared session logic from database.php
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

// Role Authorization
function authorize_role(array $allowed_roles) {
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed_roles)) {
        $role = $_SESSION['role'] ?? '';
        switch ($role) {
            case 'admin': header('Location: ' . BASE_URL . 'admin/'); break;
            case 'waka': header('Location: ' . BASE_URL . 'waka/'); break;
            case 'guru': header('Location: ' . BASE_URL . 'guru/'); break;
            default: header('Location: ' . BASE_URL . 'logout.php'); break;
        }
        exit();
    }
}
?>
