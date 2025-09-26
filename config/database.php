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
// Pastikan untuk mengubah ini sesuai dengan domain atau path aplikasi Anda
define('BASE_URL', 'http://localhost/jurnal-mengajar/');

// --- Mulai Session ---
// Panggil session_start() di sini agar tersedia di semua halaman
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>