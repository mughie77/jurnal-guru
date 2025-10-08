<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Atur zona waktu ke Asia/Jakarta
date_default_timezone_set('Asia/Jakarta');

// Ambil data JSON dari body request
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['nis'])) {
    echo json_encode(['success' => false, 'message' => 'NIS tidak ditemukan.']);
    exit;
}

$nis = mysqli_real_escape_string($conn, $data['nis']);
$today = date('Y-m-d');
$now = date('H:i:s');

// Ambil jam masuk sekolah dari pengaturan
$pengaturan_query = mysqli_query($conn, "SELECT setting_value FROM pengaturan WHERE setting_key = 'jam_masuk_sekolah'");
$jam_masuk_sekolah = '07:00:00'; // Default jika tidak ada di DB
if (mysqli_num_rows($pengaturan_query) > 0) {
    $jam_masuk_sekolah = mysqli_fetch_assoc($pengaturan_query)['setting_value'];
}

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// 1. Cari siswa dan kelasnya di tahun ajaran aktif berdasarkan NIS
$siswa_query = "
    SELECT
        s.id, s.nama_siswa, k.nama_kelas
    FROM siswa s
    LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = " . ($active_year_id ?? 0) . "
    LEFT JOIN kelas k ON sk.kelas_id = k.id
    WHERE s.nis = '$nis'
";
$siswa_result = mysqli_query($conn, $siswa_query);


if (mysqli_num_rows($siswa_result) == 0) {
    echo json_encode(['success' => false, 'message' => 'Siswa dengan NIS tersebut tidak ditemukan.']);
    exit;
}

$siswa = mysqli_fetch_assoc($siswa_result);
$siswa_id = $siswa['id'];
$nama_siswa = $siswa['nama_siswa'];
$nama_kelas = $siswa['nama_kelas'] ?? '<i>Tidak Terdaftar</i>';

// 2. Cek apakah siswa sudah absen hari ini
$absensi_check_query = "SELECT id, status FROM absensi WHERE siswa_id = $siswa_id AND tanggal = '$today'";
$absensi_check_result = mysqli_query($conn, $absensi_check_query);

if (mysqli_num_rows($absensi_check_result) > 0) {
    $absensi = mysqli_fetch_assoc($absensi_check_result);
    $status = $absensi['status'];
    echo json_encode(['success' => false, 'message' => "Siswa ini sudah diabsen dengan status: $status."]);
    exit;
}

// 3. Tentukan status berdasarkan jam masuk
$status_kehadiran = ($now > $jam_masuk_sekolah) ? 'Terlambat' : 'Hadir';

// 4. Jika belum, masukkan data absensi baru dengan status yang sesuai
$insert_query = "INSERT INTO absensi (siswa_id, tanggal, status, jam_masuk) VALUES (?, ?, ?, ?)";
$stmt = mysqli_prepare($conn, $insert_query);
mysqli_stmt_bind_param($stmt, "isss", $siswa_id, $today, $status_kehadiran, $now);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode([
        'success' => true,
        'message' => 'Absensi berhasil dicatat.',
        'nama_siswa' => $nama_siswa,
        'nama_kelas' => $nama_kelas,
        'status' => $status_kehadiran
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal mencatat absensi: ' . mysqli_error($conn)]);
}
mysqli_stmt_close($stmt);

mysqli_close($conn);
?>