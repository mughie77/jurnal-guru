<?php
require_once __DIR__ . '/../config/database.php';

// Disable any potential warnings/notices outputting to the buffer
error_reporting(0);
ini_set('display_errors', 0);

// Ensure no whitespace before this point
ob_start();

// Cek apakah user sudah login. Jika tidak, kembalikan JSON error.
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['error' => 'Sesi berakhir, silakan login kembali.']);
    exit();
}

// Otorisasi role apa saja yang butuh API ini (admin, guru)
if (!in_array($_SESSION['role'], ['admin', 'guru'])) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak.']);
    exit();
}

header('Content-Type: application/json');

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$tahun_id = (int)($_GET['tahun_id'] ?? $active_tahun_id);

if ($kelas_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'ID Kelas tidak valid']);
    exit;
}

if (!$tahun_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Tahun pelajaran aktif belum diatur.']);
    exit;
}

$query = "SELECT s.id, s.nis, s.nama_siswa, s.jenis_kelamin
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $tahun_id
          ORDER BY s.nama_siswa ASC";

$result = mysqli_query($conn, $query);
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . mysqli_error($conn)]);
    exit;
}
$siswa = [];

while ($row = mysqli_fetch_assoc($result)) {
    $siswa[] = $row;
}

// Clear any accidental output (whitespace, notices) before sending JSON
ob_clean();
echo json_encode($siswa);
exit();
?>
