<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

// Helper check for Guru BK or Wali Kelas or Admin/Waka
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

$is_allowed = false;
if (in_array($user_role, ['admin', 'waka'])) {
    $is_allowed = true;
} elseif ($user_role === 'guru') {
    // Check if Wali Kelas
    $wali_info = get_wali_kelas_info();
    if ($wali_info !== null) {
        $is_allowed = true;
    } else {
        // Check if Guru BK
        $q_guru = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = " . (int)$user_id);
        if ($g_data = mysqli_fetch_assoc($q_guru)) {
            $guru_id = (int)$g_data['id'];
            $q_bk = mysqli_query($conn, "SELECT COUNT(*) as count FROM guru_mapel gm
                                         JOIN mata_pelajaran mp ON gm.mapel_id = mp.id
                                         WHERE gm.guru_id = $guru_id AND (mp.nama_mapel LIKE '%Bimbingan Konseling%' OR mp.nama_mapel LIKE '%BK%')");
            $bk_count = mysqli_fetch_assoc($q_bk)['count'] ?? 0;
            if ($bk_count > 0) {
                $is_allowed = true;
            }
        }
    }
}

if (!$is_allowed) {
    header('Location: ' . BASE_URL . 'logout.php');
    exit();
}

$query = "SELECT p.*, k.nama_kelas
          FROM pengaduan p
          LEFT JOIN siswa_kelas sk ON p.siswa_id = sk.siswa_id AND sk.tahun_pelajaran_id = ?
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          ORDER BY p.tanggal DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $active_tahun_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
$data[] = [
    '<b>Waktu Kejadian</b>',
    '<b>Nama Pelapor</b>',
    '<b>Kelas</b>',
    '<b>Kronologi / Keterangan Laporan Pengaduan</b>',
    '<b>Latitude</b>',
    '<b>Longitude</b>',
    '<b>Akurasi GPS (meter)</b>'
];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        date('d-m-Y H:i', strtotime($row['tanggal'])),
        $row['nama_siswa'],
        $row['nama_kelas'] ?? 'Tanpa Kelas',
        $row['keterangan'],
        $row['latitude'],
        $row['longitude'],
        round($row['akurasi'])
    ];
}
mysqli_stmt_close($stmt);

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
$filename = "Rekap_Laporan_Pengaduan_Siswa_" . date('Ymd_His') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
