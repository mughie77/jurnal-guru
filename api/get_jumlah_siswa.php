<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Hanya user yang sudah login (guru/admin) yang bisa akses
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Akses ditolak']);
    exit();
}

if (!isset($_GET['kelas_id'])) {
    echo json_encode(['error' => 'ID Kelas tidak disediakan']);
    exit();
}

$kelas_id = (int)$_GET['kelas_id'];

$query = "SELECT jumlah_siswa_L, jumlah_siswa_P FROM kelas WHERE id = ?";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $kelas_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($kelas = mysqli_fetch_assoc($result)) {
    $total_siswa = $kelas['jumlah_siswa_L'] + $kelas['jumlah_siswa_P'];
    echo json_encode(['total_siswa' => $total_siswa]);
} else {
    echo json_encode(['total_siswa' => 0]);
}

mysqli_stmt_close($stmt);
?>