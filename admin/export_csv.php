<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

// Otorisasi untuk admin dan waka
authorize_role(['admin', 'waka']);

// Logika Filter (sama persis seperti di jurnal.php)
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

$sql = "SELECT jurnal.tanggal, users.nama_lengkap, mata_pelajaran.nama_mapel, kelas.nama_kelas, jurnal.jam_ke,
               jurnal.materi, jurnal.jml_hadir, jurnal.jml_sakit, jurnal.jml_izin, jurnal.jml_alfa, jurnal.keterangan
        FROM jurnal
        JOIN guru ON jurnal.guru_id = guru.id
        JOIN users ON guru.user_id = users.id
        JOIN mata_pelajaran ON jurnal.mapel_id = mata_pelajaran.id
        JOIN kelas ON jurnal.kelas_id = kelas.id";
if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY jurnal.tanggal ASC, jurnal.created_at ASC";

$result = mysqli_query($conn, $sql);

// Set header untuk download file CSV
$filename = "Laporan_Jurnal_Mengajar_" . date('Ymd') . ".csv";
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Buka output stream
$output = fopen('php://output', 'w');

// Header Tabel
$header = array('Tanggal', 'Nama Guru', 'Mapel', 'Kelas', 'Jam Ke', 'Materi', 'Hadir', 'Sakit', 'Izin', 'Alfa', 'Keterangan');
fputcsv($output, $header);

// Tulis data ke CSV
while($row = mysqli_fetch_assoc($result)) {
    $csv_row = [
        date('d-m-Y', strtotime($row['tanggal'])),
        $row['nama_lengkap'],
        $row['nama_mapel'],
        $row['nama_kelas'],
        $row['jam_ke'],
        $row['materi'],
        $row['jml_hadir'],
        $row['jml_sakit'],
        $row['jml_izin'],
        $row['jml_alfa'],
        $row['keterangan']
    ];
    fputcsv($output, $csv_row);
}

fclose($output);
exit();
?>