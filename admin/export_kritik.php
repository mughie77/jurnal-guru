<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['admin', 'waka']);

$query = "SELECT * FROM kritik_saran ORDER BY tanggal DESC";
$result = mysqli_query($conn, $query);

$data = [];
$data[] = [
    '<b>Waktu Kirim</b>',
    '<b>Nama Pengirim</b>',
    '<b>Peran</b>',
    '<b>Subjek</b>',
    '<b>Kritik / Saran / Isi</b>',
    '<b>Tanggapan (Umpan Balik)</b>'
];

while ($row = mysqli_fetch_assoc($result)) {
    $data[] = [
        date('d-m-Y H:i', strtotime($row['tanggal'])),
        $row['nama_pengirim'],
        strtoupper($row['role']),
        $row['subjek'],
        $row['isi'],
        $row['umpan_balik'] ?? 'Belum ditanggapi'
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
$filename = "Rekap_Kritik_Saran_" . date('Ymd_His') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
