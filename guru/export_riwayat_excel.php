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

$tgl_mulai = $_GET['tanggal_mulai'] ?? '';
$tgl_selesai = $_GET['tanggal_selesai'] ?? '';

$where_clause = "jurnal.guru_id = $guru_id";
if ($active_tahun_id) {
    $where_clause .= " AND jurnal.tahun_pelajaran_id = $active_tahun_id";
}

if (!empty($tgl_mulai)) {
    $tgl_mulai_escaped = mysqli_real_escape_string($conn, $tgl_mulai);
    $where_clause .= " AND jurnal.tanggal >= '$tgl_mulai_escaped'";
}
if (!empty($tgl_selesai)) {
    $tgl_selesai_escaped = mysqli_real_escape_string($conn, $tgl_selesai);
    $where_clause .= " AND jurnal.tanggal <= '$tgl_selesai_escaped'";
}

$sql = "SELECT jurnal.*, mp.nama_mapel, k.nama_kelas
        FROM jurnal
        JOIN mata_pelajaran mp ON jurnal.mapel_id = mp.id
        JOIN kelas k ON jurnal.kelas_id = k.id
        WHERE $where_clause
        ORDER BY jurnal.tanggal DESC, jurnal.created_at DESC";
$result = mysqli_query($conn, $sql);

$rekap_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rekap_data[] = [
        'tanggal' => date('d-m-Y', strtotime($row['tanggal'])),
        'mapel' => $row['nama_mapel'],
        'kelas' => $row['nama_kelas'],
        'jam' => $row['jam_ke'],
        'materi' => $row['materi'],
        'keterangan' => $row['keterangan'],
        'hadir' => $row['jml_hadir'],
        'sakit' => $row['jml_sakit'],
        'izin' => $row['jml_izin'],
        'alfa' => $row['jml_alfa']
    ];
}

$data = [];
// Title and Metadata
$data[] = ['<b>RIWAYAT JURNAL MENGAJAR GURU</b>', '', '', '', '', '', '', '', '', ''];
$data[] = ['Nama Guru:', $nama_guru, '', '', '', '', '', '', '', ''];
if (!empty($tgl_mulai) || !empty($tgl_selesai)) {
    $range_str = (!empty($tgl_mulai) ? date('d-m-Y', strtotime($tgl_mulai)) : 'Awal') . ' s/d ' . (!empty($tgl_selesai) ? date('d-m-Y', strtotime($tgl_selesai)) : 'Akhir');
    $data[] = ['Rentang Tanggal:', $range_str, '', '', '', '', '', '', '', ''];
} else {
    $data[] = ['Rentang Tanggal:', 'Semua Riwayat', '', '', '', '', '', '', '', ''];
}
$data[] = ['', '', '', '', '', '', '', '', '', '']; // Spacer

// Header
$data[] = [
    '<b>Tanggal</b>',
    '<b>Mata Pelajaran</b>',
    '<b>Kelas</b>',
    '<b>Jam Ke</b>',
    '<b>Materi Pembahasan</b>',
    '<b>Catatan Tambahan</b>',
    '<b>Hadir (H)</b>',
    '<b>Sakit (S)</b>',
    '<b>Izin (I)</b>',
    '<b>Alfa (A)</b>'
];

foreach ($rekap_data as $row) {
    $data[] = [
        $row['tanggal'],
        $row['mapel'],
        $row['kelas'],
        $row['jam'],
        $row['materi'],
        $row['keterangan'],
        $row['hadir'],
        $row['sakit'],
        $row['izin'],
        $row['alfa']
    ];
}

// Styling & borders starting from index 4 (header table)
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
$filename = "Riwayat_Jurnal_" . str_replace(' ', '_', $nama_guru) . "_" . date('Ymd') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
