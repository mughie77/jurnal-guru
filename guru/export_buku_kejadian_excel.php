<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['guru', 'admin', 'waka']);

$user_id = $_SESSION['user_id'];
$nama_guru = $_SESSION['nama_lengkap'] ?? 'Guru';

$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = (mysqli_num_rows($guru_res) > 0) ? mysqli_fetch_assoc($guru_res)['id'] : 0;

$kelas_id = (int)($_GET['kelas_id'] ?? 0);
$tgl_mulai = $_GET['tanggal_mulai'] ?? '';
$tgl_selesai = $_GET['tanggal_selesai'] ?? '';
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');

$where_clauses = [];
if (!in_array($_SESSION['role'], ['admin', 'waka'])) {
    $where_clauses[] = "bk.guru_id = $guru_id";
}
if ($kelas_id > 0) {
    $where_clauses[] = "bk.kelas_id = $kelas_id";
}
if (!empty($tgl_mulai)) {
    $where_clauses[] = "bk.tanggal >= '" . mysqli_real_escape_string($conn, $tgl_mulai) . "'";
}
if (!empty($tgl_selesai)) {
    $where_clauses[] = "bk.tanggal <= '" . mysqli_real_escape_string($conn, $tgl_selesai) . "'";
}
if (!empty($search)) {
    $where_clauses[] = "(bk.nama_siswa_list LIKE '%$search%' OR bk.uraian_kejadian LIKE '%$search%' OR bk.tindak_lanjut LIKE '%$search%')";
}

$where_sql = !empty($where_clauses) ? " WHERE " . implode(" AND ", $where_clauses) : "";

$query = "SELECT bk.*, k.nama_kelas, mp.nama_mapel, u.nama_lengkap as nama_guru
          FROM buku_kejadian bk
          JOIN kelas k ON bk.kelas_id = k.id
          LEFT JOIN mata_pelajaran mp ON bk.mapel_id = mp.id
          LEFT JOIN guru g ON bk.guru_id = g.id
          LEFT JOIN users u ON g.user_id = u.id
          $where_sql
          ORDER BY bk.tanggal DESC, bk.created_at DESC";
$result = mysqli_query($conn, $query);

$data = [];
$data[] = ['<b>REKAP BUKU KEJADIAN KELAS</b>', '', '', '', '', ''];
$data[] = ['Nama Guru / Pelapor:', $nama_guru, '', '', '', ''];
$data[] = ['Tanggal Ekspor:', date('d-m-Y H:i') . ' WIB', '', '', '', ''];
$data[] = ['', '', '', '', '', '']; // Spacer

$data[] = [
    '<b>No</b>',
    '<b>Hari & Tanggal</b>',
    '<b>Kelas & Mapel</b>',
    '<b>Guru Pelapor</b>',
    '<b>Siswa Terlibat</b>',
    '<b>Uraian Kejadian</b>',
    '<b>Tindak Lanjut / Pembinaan</b>'
];

$no = 1;
while ($r = mysqli_fetch_assoc($result)) {
    $data[] = [
        $no++,
        ($r['hari'] ?? date('l', strtotime($r['tanggal']))) . ', ' . date('d-m-Y', strtotime($r['tanggal'])),
        $r['nama_kelas'] . ($r['nama_mapel'] ? ' (' . $r['nama_mapel'] . ')' : ''),
        $r['nama_guru'] ?? $nama_guru,
        $r['nama_siswa_list'] ?? 'Seluruh Siswa',
        $r['uraian_kejadian'],
        $r['tindak_lanjut'] ?? '-'
    ];
}

foreach ($data as $rowIndex => &$row) {
    if ($rowIndex < 4) continue;
    foreach ($row as $colIndex => &$cell) {
        if ($rowIndex === 4) {
            $cell = '<style bgcolor="#FEF3C7" border="thin">'.$cell.'</style>';
        } else {
            $cell = '<style border="thin">'.$cell.'</style>';
        }
    }
}

$xlsx = SimpleXLSXGen::fromArray($data);
$filename = "Rekap_Buku_Kejadian_" . date('Ymd') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
