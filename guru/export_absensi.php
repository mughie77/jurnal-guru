<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk guru
authorize_role(['guru']);

// Dapatkan guru_id dari user_id session
$user_id = $_SESSION['user_id'];
$guru_info_query = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_info = mysqli_fetch_assoc($guru_info_query);
$guru_id = $guru_info ? $guru_info['id'] : null;

// Dapatkan kelas yang diampu oleh guru sebagai wali kelas
$kelas_ids = [];
if ($guru_id) {
    $kelas_query = mysqli_query($conn, "SELECT id FROM kelas WHERE wali_kelas_id = $guru_id");
    while ($kelas = mysqli_fetch_assoc($kelas_query)) {
        $kelas_ids[] = $kelas['id'];
    }
}

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// Logika Filter
$filter_start_date = $_GET['start_date'] ?? date('Y-m-d');
$filter_end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_search = $_GET['search'] ?? '';

$query = "
    SELECT
        a.tanggal,
        s.nama_siswa,
        s.nis,
        a.status,
        a.jam_masuk
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    JOIN siswa_kelas sk ON s.id = sk.siswa_id
";

$where_clauses = [
    "a.tanggal BETWEEN '{$filter_start_date}' AND '{$filter_end_date}'",
    "sk.tahun_pelajaran_id = " . ($active_year_id ?? 0)
];

if (!empty($kelas_ids)) {
    $where_clauses[] = "sk.kelas_id IN (" . implode(',', $kelas_ids) . ")";
} else {
    $where_clauses[] = "1=0";
}

if (!empty($filter_search)) {
    $sanitized_search = mysqli_real_escape_string($conn, $filter_search);
    $where_clauses[] = "s.nama_siswa LIKE '%{$sanitized_search}%'";
}

$query .= " WHERE " . implode(' AND ', $where_clauses);
$query .= " ORDER BY a.tanggal DESC, s.nama_siswa ASC";
$result = mysqli_query($conn, $query);

// Nama file CSV
$filename = "rekap_absensi_kelas_" . date('Y-m-d') . ".csv";

// Set header untuk download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Buka output stream
$output = fopen('php://output', 'w');

// Tulis header CSV
fputcsv($output, ['Tanggal', 'Nama Siswa', 'NIS', 'Status', 'Jam Masuk']);

// Tulis data ke CSV
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>