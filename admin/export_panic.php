<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['admin', 'waka']);

$query = "SELECT pb.*, k.nama_kelas
          FROM panic_button pb
          LEFT JOIN siswa_kelas sk ON pb.siswa_id = sk.siswa_id AND sk.tahun_pelajaran_id = ?
          LEFT JOIN kelas k ON sk.kelas_id = k.id
          ORDER BY pb.tanggal DESC";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, "i", $active_tahun_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$data = [];
$data[] = [
    '<b>Waktu Kejadian</b>',
    '<b>Nama Pelapor</b>',
    '<b>Kelas</b>',
    '<b>Kronologi / Keterangan Bullying</b>',
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
$filename = "Rekap_Laporan_Bullying_" . date('Ymd_His') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
