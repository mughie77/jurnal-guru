<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka']); // Waka juga diberi akses ke ekspor

// Logika Filter (sama persis dengan di admin/jurnal.php)
$where_clauses = [];

if (!empty($_GET['start_date'])) {
    $where_clauses[] = "jurnal.tanggal >= '" . mysqli_real_escape_string($conn, $_GET['start_date']) . "'";
}
if (!empty($_GET['end_date'])) {
    $where_clauses[] = "jurnal.tanggal <= '" . mysqli_real_escape_string($conn, $_GET['end_date']) . "'";
}
if (!empty($_GET['guru_id'])) {
    $where_clauses[] = "jurnal.guru_id = " . (int)$_GET['guru_id'];
}
if (!empty($_GET['mapel_id'])) {
    $where_clauses[] = "jurnal.mapel_id = " . (int)$_GET['mapel_id'];
}
if (!empty($_GET['kelas_id'])) {
    $where_clauses[] = "jurnal.kelas_id = " . (int)$_GET['kelas_id'];
}
if (!empty($_GET['tahun_id'])) {
    $where_clauses[] = "jurnal.tahun_pelajaran_id = " . (int)$_GET['tahun_id'];
}

$sql = "SELECT
            jurnal.tanggal,
            users.nama_lengkap as nama_guru,
            mata_pelajaran.nama_mapel,
            kelas.nama_kelas,
            tahun_pelajaran.tahun,
            jurnal.jam_ke,
            jurnal.materi,
            jurnal.jml_hadir,
            jurnal.jml_sakit,
            jurnal.jml_izin,
            jurnal.jml_alfa,
            jurnal.keterangan
        FROM jurnal
        JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id
        JOIN tahun_pelajaran ON jurnal.tahun_pelajaran_id = tahun_pelajaran.id";

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";

$result = mysqli_query($conn, $sql);

// Nama file CSV
$filename = "export_jurnal_" . date('Y-m-d') . ".csv";

// Set header untuk download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Buka output stream
$output = fopen('php://output', 'w');

// Tulis header CSV
fputcsv($output, [
    'Tanggal', 'Nama Guru', 'Mata Pelajaran', 'Kelas', 'Tahun Pelajaran', 'Jam Ke-', 'Materi Pembahasan',
    'Jumlah Hadir', 'Jumlah Sakit', 'Jumlah Izin', 'Jumlah Alfa', 'Keterangan'
]);

// Tulis data ke CSV
if (mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row);
    }
}

fclose($output);
exit();
?>