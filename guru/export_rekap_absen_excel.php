<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$nama_guru = $_SESSION['nama_lengkap'] ?? 'Guru';

$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if (mysqli_num_rows($guru_res) == 0) {
    die("Error: Data guru tidak ditemukan.");
}
$guru_row = mysqli_fetch_assoc($guru_res);
$guru_id = $guru_row['id'];

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$mapel_id = (int)($_GET['mapel_id'] ?? 0);
$tgl_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-d');
$tgl_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

if ($kelas_id <= 0) {
    die("Error: Parameter kelas wajib dipilih.");
}

// Fetch metadata
$q_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id = $kelas_id");
$nama_kelas = mysqli_fetch_assoc($q_kelas)['nama_kelas'] ?? '-';

$nama_mapel = 'Semua Mapel Saya';
if ($mapel_id > 0) {
    $q_mapel = mysqli_query($conn, "SELECT nama_mapel FROM mata_pelajaran WHERE id = $mapel_id");
    $nama_mapel = mysqli_fetch_assoc($q_mapel)['nama_mapel'] ?? 'Semua Mapel';
}

$tgl_m_escaped = mysqli_real_escape_string($conn, $tgl_mulai);
$tgl_s_escaped = mysqli_real_escape_string($conn, $tgl_selesai);

$where_guru = ($_SESSION['role'] === 'admin') ? "1=1" : "j.guru_id = $guru_id";
$where_mapel = ($mapel_id > 0) ? " AND j.mapel_id = $mapel_id" : "";

// 1. Fetch Jurnals
$query_jurnal = "SELECT j.id, j.tanggal, j.jam_ke, mp.nama_mapel
                 FROM jurnal j
                 JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                 WHERE j.kelas_id = $kelas_id AND $where_guru $where_mapel AND j.tanggal BETWEEN '$tgl_m_escaped' AND '$tgl_s_escaped'
                 ORDER BY j.tanggal ASC, j.jam_ke ASC";
$res_jurnal = mysqli_query($conn, $query_jurnal);

$jurnals = [];
while ($j = mysqli_fetch_assoc($res_jurnal)) {
    $j['absensi'] = [];
    $res_abs = mysqli_query($conn, "SELECT siswa_id, status FROM absensi_jurnal WHERE jurnal_id = " . $j['id']);
    while ($a = mysqli_fetch_assoc($res_abs)) {
        $j['absensi'][$a['siswa_id']] = $a['status'];
    }
    $jurnals[] = $j;
}

// 2. Fetch Siswas
$where_siswa = " WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id";
$query_siswa = "SELECT s.id, s.nama_siswa, s.nis
                FROM siswa s
                JOIN siswa_kelas sk ON s.id = sk.siswa_id
                $where_siswa
                ORDER BY s.nama_siswa ASC";
$res_siswa = mysqli_query($conn, $query_siswa);

$data = [];
$data[] = ['<b>REKAP ABSENSI JURNAL SISWA PER JAM MATA PELAJARAN</b>', '', '', ''];
$data[] = ['Guru Pengajar:', $nama_guru, '', ''];
$data[] = ['Kelas:', $nama_kelas, '', ''];
$data[] = ['Mata Pelajaran:', $nama_mapel, '', ''];
$data[] = ['Periode Tanggal:', date('d-m-Y', strtotime($tgl_mulai)) . ' s/d ' . date('d-m-Y', strtotime($tgl_selesai)), '', ''];
$data[] = ['', '', '', '']; // Spacer

// Build Table Header
$header_row = [
    '<b>No</b>',
    '<b>NIS</b>',
    '<b>Nama Siswa</b>'
];

foreach ($jurnals as $j) {
    $header_row[] = '<b>' . date('d/m', strtotime($j['tanggal'])) . ' (Jam ' . $j['jam_ke'] . ' - ' . $j['nama_mapel'] . ')</b>';
}

$header_row[] = '<b>Hadir (H)</b>';
$header_row[] = '<b>Sakit (S)</b>';
$header_row[] = '<b>Izin (I)</b>';
$header_row[] = '<b>Alfa (A)</b>';

$data[] = $header_row;

$no = 1;
while ($s = mysqli_fetch_assoc($res_siswa)) {
    $row_data = [
        $no++,
        $s['nis'],
        $s['nama_siswa']
    ];

    $cnt_h = 0; $cnt_s = 0; $cnt_i = 0; $cnt_a = 0;

    foreach ($jurnals as $j) {
        $st = $j['absensi'][$s['id']] ?? '-';
        if ($st == 'H') $cnt_h++;
        elseif ($st == 'S') $cnt_s++;
        elseif ($st == 'I') $cnt_i++;
        elseif ($st == 'A') $cnt_a++;

        $row_data[] = $st;
    }

    $row_data[] = $cnt_h;
    $row_data[] = $cnt_s;
    $row_data[] = $cnt_i;
    $row_data[] = $cnt_a;

    $data[] = $row_data;
}

foreach ($data as $rowIndex => &$row) {
    if ($rowIndex < 6) continue;
    foreach ($row as $colIndex => &$cell) {
        if ($rowIndex === 6) {
            $cell = '<style bgcolor="#E2E8F0" border="thin">'.$cell.'</style>';
        } else {
            $cell = '<style border="thin">'.$cell.'</style>';
        }
    }
}

$xlsx = SimpleXLSXGen::fromArray($data);
$filename = "Rekap_Absensi_Matrix_" . str_replace(' ', '_', $nama_kelas) . "_" . date('Ymd') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
