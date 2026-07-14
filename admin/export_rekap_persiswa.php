<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

// Otorisasi untuk admin, waka, dan wali kelas
authorize_role(['admin', 'waka', 'wali_kelas']);

$is_wali = ($_SESSION['role'] === 'guru');
$wali_info = get_wali_kelas_info();

if ($is_wali && $wali_info) {
    $kelas_id = $wali_info['kelas_id'];
} else {
    $kelas_id = (int)($_GET['kelas_id'] ?? 0);
}

if ($kelas_id <= 0) {
    die("Kelas tidak valid.");
}

$filter_tipe = $_GET['filter_tipe'] ?? 'hari';
if ($filter_tipe !== 'hari' && $filter_tipe !== 'bulan' && $filter_tipe !== 'semester') {
    $filter_tipe = 'hari';
}
$filter_tipe = mysqli_real_escape_string($conn, $filter_tipe);

$start_date = '';
$end_date = '';

// Daily Range
$hari_mulai = $_GET['hari_mulai'] ?? date('Y-m-d');
$hari_selesai = $_GET['hari_selesai'] ?? date('Y-m-d');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hari_mulai)) {
    $hari_mulai = date('Y-m-d');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hari_selesai)) {
    $hari_selesai = date('Y-m-d');
}

$hari_mulai = mysqli_real_escape_string($conn, $hari_mulai);
$hari_selesai = mysqli_real_escape_string($conn, $hari_selesai);

// Monthly
$bulan = (int)($_GET['bulan'] ?? date('m'));
$tahun_select = (int)($_GET['tahun_select'] ?? date('Y'));

// Semester
$semester = $_GET['semester'] ?? 'ganjil';
if ($semester !== 'ganjil' && $semester !== 'genap') {
    $semester = 'ganjil';
}
$semester = mysqli_real_escape_string($conn, $semester);

$tahun_ajaran_id = (int)($_GET['tahun_ajaran_id'] ?? $active_tahun_id);

if ($filter_tipe === 'hari') {
    $start_date = $hari_mulai;
    $end_date = $hari_selesai;
} elseif ($filter_tipe === 'bulan') {
    $start_date = "$tahun_select-" . str_pad($bulan, 2, '0', STR_PAD_LEFT) . "-01";
    $end_date = date("Y-m-t", strtotime($start_date));
} elseif ($filter_tipe === 'semester') {
    // Fetch selected school year string
    $q_th = mysqli_query($conn, "SELECT tahun FROM tahun_pelajaran WHERE id = $tahun_ajaran_id");
    $th_data = mysqli_fetch_assoc($q_th);
    $th_str = $th_data['tahun'] ?? ''; // e.g. "2025/2026"

    if (preg_match('/^(\d{4})\/(\d{4})$/', $th_str, $matches)) {
        $year_start = $matches[1];
        $year_end = $matches[2];

        if ($semester === 'ganjil') {
            $start_date = "$year_start-07-01";
            $end_date = "$year_start-12-31";
        } else {
            $start_date = "$year_end-01-01";
            $end_date = "$year_end-06-30";
        }
    } else {
        // Fallback to active year or current year
        $start_date = date('Y') . "-01-01";
        $end_date = date('Y') . "-12-31";
    }
}

if (!$start_date || !$end_date) {
    die("Rentang waktu tidak valid.");
}

// Fetch class name
$q_kls = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id = $kelas_id");
$kls_data = mysqli_fetch_assoc($q_kls);
$nama_kelas = $kls_data['nama_kelas'] ?? 'Semua';

$active_tahun_id_clean = (int)($active_tahun_id ?? 0);
$where_siswa = " WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id_clean";

$query = "SELECT s.id, s.nis, s.nama_siswa, s.jenis_kelamin,
                 COUNT(CASE WHEN aj.status = 'H' THEN 1 END) as count_h,
                 COUNT(CASE WHEN aj.status = 'S' THEN 1 END) as count_s,
                 COUNT(CASE WHEN aj.status = 'I' THEN 1 END) as count_i,
                 COUNT(CASE WHEN aj.status = 'A' THEN 1 END) as count_a
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          LEFT JOIN (
              SELECT aj.siswa_id, aj.status
              FROM absensi_jurnal aj
              JOIN jurnal j ON aj.jurnal_id = j.id
              WHERE j.kelas_id = $kelas_id AND j.tanggal BETWEEN '$start_date' AND '$end_date'
          ) aj ON s.id = aj.siswa_id
          $where_siswa
          GROUP BY s.id, s.nis, s.nama_siswa, s.jenis_kelamin
          ORDER BY s.nama_siswa ASC";

$res = mysqli_query($conn, $query);

$data = [];
// Title and Metadata
$data[] = ['<b>REKAP KEHADIRAN SISWA</b>', '', '', '', '', '', '', ''];
$data[] = ['Kelas:', $nama_kelas, '', '', '', '', '', ''];
$data[] = ['Rentang Tanggal:', date('d-m-Y', strtotime($start_date)) . ' s/d ' . date('d-m-Y', strtotime($end_date)), '', '', '', '', '', ''];
$data[] = ['', '', '', '', '', '', '', '']; // Empty spacer

// Header with styling
$data[] = [
    '<b>Nama Siswa</b>',
    '<b>NIS</b>',
    '<b>L/P</b>',
    '<b>Hadir (H)</b>',
    '<b>Sakit (S)</b>',
    '<b>Izin (I)</b>',
    '<b>Alfa (A)</b>',
    '<b>Total Jam</b>'
];

while($row = mysqli_fetch_assoc($res)) {
    $total_jam = $row['count_h'] + $row['count_s'] + $row['count_i'] + $row['count_a'];
    $data[] = [
        $row['nama_siswa'],
        $row['nis'],
        $row['jenis_kelamin'],
        $row['count_h'],
        $row['count_s'],
        $row['count_i'],
        $row['count_a'],
        $total_jam
    ];
}

// Add borders to all cells from index 4 onwards (the table data)
foreach ($data as $rowIndex => &$row) {
    if ($rowIndex < 4) {
        continue;
    }
    foreach ($row as $colIndex => &$cell) {
        if ($rowIndex === 4) {
            $cell = '<style bgcolor="#E2E8F0" border="thin">'.$cell.'</style>';
        } else {
            $cell = '<style border="thin">'.$cell.'</style>';
        }
    }
}

$xlsx = SimpleXLSXGen::fromArray($data);
$filename = "Rekap_Kehadiran_Siswa_" . str_replace(' ', '_', $nama_kelas) . "_" . date('Ymd') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
