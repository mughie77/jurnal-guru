<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Atur zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Ambil data JSON dari body request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['nisn'])) {
    echo json_encode(['success' => false, 'message' => 'NISN tidak ditemukan.']);
    exit;
}

$nisn = mysqli_real_escape_string($conn, $data['nisn']);
$today = date('Y-m-d');
$now = date('H:i:s');

// 1. Cari siswa berdasarkan NISN
$siswa_query = "SELECT id, nama_siswa FROM siswa WHERE nisn = '$nisn'";
$siswa_result = mysqli_query($conn, $siswa_query);

if (mysqli_num_rows($siswa_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Siswa dengan NISN tersebut tidak ditemukan.']);
    exit;
}

$siswa = mysqli_fetch_assoc($siswa_result);
$siswa_id = $siswa['id'];
$nama_siswa = $siswa['nama_siswa'];

// 2. Cek apakah siswa sudah absen hari ini
$absensi_check_query = "SELECT id, status FROM absensi WHERE siswa_id = $siswa_id AND tanggal = '$today'";
$absensi_check_result = mysqli_query($conn, $absensi_check_query);

if (mysqli_num_rows($absensi_check_result) > 0) {
    $absensi = mysqli_fetch_assoc($absensi_check_result);
    $status = $absensi['status'];
    echo json_encode(['success' => false, 'message' => "Siswa ini sudah diabsen dengan status: $status."]);
    exit;
}

// 3. Jika belum, masukkan data absensi baru
$insert_query = "INSERT INTO absensi (siswa_id, tanggal, status, jam_masuk) VALUES ($siswa_id, '$today', 'Hadir', '$now')";

if (mysqli_query($conn, $insert_query)) {
    echo json_encode(['success' => true, 'message' => 'Absensi berhasil dicatat.', 'nama_siswa' => $nama_siswa]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mencatat absensi: ' . mysqli_error($conn)]);
}

mysqli_close($conn);
?>