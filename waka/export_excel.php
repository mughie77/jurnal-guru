<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

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
if (!empty($_GET['search'])) {
    $search = mysqli_real_escape_string($conn, $_GET['search']);
    $where_clauses[] = "(jurnal.materi LIKE '%$search%' OR users.nama_lengkap LIKE '%$search%')";
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

$data = [];
// Header with styling
$data[] = [
    '<b>Tanggal</b>',
    '<b>Nama Guru</b>',
    '<b>Mapel</b>',
    '<b>Kelas</b>',
    '<b>Jam Ke</b>',
    '<b>Materi</b>',
    '<b>Hadir</b>',
    '<b>Sakit</b>',
    '<b>Izin</b>',
    '<b>Alfa</b>',
    '<b>Keterangan</b>'
];

while($row = mysqli_fetch_assoc($result)) {
    $data[] = [
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
}

// Add borders to all cells
foreach ($data as $rowIndex => &$row) {
    foreach ($row as $colIndex => &$cell) {
        if ($rowIndex === 0) {
            $cell = '<style bgcolor="#E2E8F0" border="thin">'.$cell.'</style>';
        } else {
            $cell = '<style border="thin">'.$cell.'</style>';
        }
    }
}

$xlsx = SimpleXLSXGen::fromArray($data);
$filename = "Laporan_Jurnal_Mengajar_" . date('Ymd') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
