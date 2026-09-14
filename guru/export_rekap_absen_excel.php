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
$tgl_mulai = $_GET['tanggal_mulai'] ?? date('Y-m-01');
$tgl_selesai = $_GET['tanggal_selesai'] ?? date('Y-m-d');

if ($kelas_id <= 0 || $mapel_id <= 0) {
    die("Error: Parameter kelas dan mata pelajaran wajib dipilih.");
}

// Fetch metadata
$q_kelas = mysqli_query($conn, "SELECT nama_kelas FROM kelas WHERE id = $kelas_id");
$nama_kelas = mysqli_fetch_assoc($q_kelas)['nama_kelas'] ?? '-';

$q_mapel = mysqli_query($conn, "SELECT nama_mapel FROM mata_pelajaran WHERE id = $mapel_id");
$nama_mapel = mysqli_fetch_assoc($q_mapel)['nama_mapel'] ?? '-';

$tgl_m_escaped = mysqli_real_escape_string($conn, $tgl_mulai);
$tgl_s_escaped = mysqli_real_escape_string($conn, $tgl_selesai);

$where_guru = ($_SESSION['role'] === 'admin') ? "1=1" : "j.guru_id = $guru_id";

$sql = "SELECT s.nama_siswa, s.nis, s.nisn,
        COUNT(CASE WHEN aj.status = 'H' THEN 1 END) as hadir,
        COUNT(CASE WHEN aj.status = 'S' THEN 1 END) as sakit,
        COUNT(CASE WHEN aj.status = 'I' THEN 1 END) as izin,
        COUNT(CASE WHEN aj.status = 'A' THEN 1 END) as alfa,
        COUNT(aj.id) as total_pertemuan
        FROM siswa s
        JOIN siswa_kelas sk ON s.id = sk.siswa_id
        LEFT JOIN absensi_jurnal aj ON s.id = aj.siswa_id
        LEFT JOIN jurnal j ON aj.jurnal_id = j.id AND j.mapel_id = $mapel_id AND j.kelas_id = $kelas_id AND $where_guru AND j.tanggal BETWEEN '$tgl_m_escaped' AND '$tgl_s_escaped'
        WHERE sk.kelas_id = $kelas_id AND sk.tahun_pelajaran_id = $active_tahun_id
        GROUP BY s.id
        ORDER BY s.nama_siswa ASC";
$result = mysqli_query($conn, $sql);

$data = [];
$data[] = ['<b>REKAP KEHADIRAN SISWA PER MATA PELAJARAN</b>', '', '', '', '', '', '', '', ''];
$data[] = ['Guru Pengajar:', $nama_guru, '', '', '', '', '', '', ''];
$data[] = ['Kelas:', $nama_kelas, '', '', '', '', '', '', ''];
$data[] = ['Mata Pelajaran:', $nama_mapel, '', '', '', '', '', '', ''];
$data[] = ['Periode Tanggal:', date('d-m-Y', strtotime($tgl_mulai)) . ' s/d ' . date('d-m-Y', strtotime($tgl_selesai)), '', '', '', '', '', '', ''];
$data[] = ['', '', '', '', '', '', '', '', '']; // Spacer

$data[] = [
    '<b>No</b>',
    '<b>NIS</b>',
    '<b>NISN</b>',
    '<b>Nama Siswa</b>',
    '<b>Hadir (H)</b>',
    '<b>Sakit (S)</b>',
    '<b>Izin (I)</b>',
    '<b>Alfa (A)</b>',
    '<b>Total Sesi</b>',
    '<b>Persentase Hadir (%)</b>'
];

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $total = (int)$row['total_pertemuan'];
    $hadir = (int)$row['hadir'];
    $pct = $total > 0 ? round(($hadir / $total) * 100) : 0;

    $data[] = [
        $no++,
        $row['nis'],
        $row['nisn'] ?? '-',
        $row['nama_siswa'],
        $row['hadir'],
        $row['sakit'],
        $row['izin'],
        $row['alfa'],
        $total,
        $pct . '%'
    ];
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
$filename = "Rekap_Absen_" . str_replace(' ', '_', $nama_mapel) . "_" . str_replace(' ', '_', $nama_kelas) . "_" . date('Ymd') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
