<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi hanya untuk admin
authorize_role(['admin']);

// Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year ? $active_year['id'] : null;

// Logika Filter dari GET parameter
$filter_start_date = $_GET['start_date'] ?? date('Y-m-d');
$filter_end_date = $_GET['end_date'] ?? date('Y-m-d');
$filter_kelas_id = $_GET['kelas_id'] ?? '';
$filter_search = $_GET['search'] ?? '';

$query = "
    SELECT
        a.tanggal,
        s.nama_siswa,
        s.nis,
        s.nisn,
        k.nama_kelas,
        a.status,
        a.jam_masuk
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = " . ($active_year_id ?? 0) . "
    LEFT JOIN kelas k ON sk.kelas_id = k.id
";

$where_clauses = [];
$where_clauses[] = "a.tanggal BETWEEN '{$filter_start_date}' AND '{$filter_end_date}'";

if (!empty($filter_kelas_id)) {
    $where_clauses[] = "k.id = " . (int)$filter_kelas_id;
}
if (!empty($filter_search)) {
    $sanitized_search = mysqli_real_escape_string($conn, $filter_search);
    $where_clauses[] = "s.nama_siswa LIKE '%{$sanitized_search}%'";
}

if (!empty($where_clauses)) {
    $query .= " WHERE " . implode(' AND ', $where_clauses);
}

$query .= " ORDER BY a.tanggal DESC, k.nama_kelas, s.nama_siswa ASC";
$result = mysqli_query($conn, $query);

// Nama file CSV
$filename = "rekap_absensi_" . date('Y-m-d') . ".csv";

// Set header untuk download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Buka output stream
$output = fopen('php://output', 'w');

// Tulis header CSV
fputcsv($output, ['Tanggal', 'Nama Siswa', 'NIS', 'NISN', 'Kelas', 'Status', 'Jam Masuk']);

// Tulis data ke CSV
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>