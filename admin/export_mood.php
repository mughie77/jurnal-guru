<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';
use Shuchkin\SimpleXLSXGen;

authorize_role(['admin', 'waka']);

// Filter Parameters
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$role_filter = $_GET['role'] ?? '';
$mood_filter = $_GET['mood'] ?? '';
$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');

$where_clauses = [];
if (!empty($start_date)) {
    $where_clauses[] = "ms.tanggal >= '" . mysqli_real_escape_string($conn, $start_date) . "'";
}
if (!empty($end_date)) {
    $where_clauses[] = "ms.tanggal <= '" . mysqli_real_escape_string($conn, $end_date) . "'";
}
if (!empty($role_filter)) {
    $where_clauses[] = "ms.role = '" . mysqli_real_escape_string($conn, $role_filter) . "'";
}
if (!empty($mood_filter)) {
    $where_clauses[] = "ms.mood = '" . mysqli_real_escape_string($conn, $mood_filter) . "'";
}
if (!empty($search)) {
    $where_clauses[] = "(s.nama_siswa LIKE '%$search%' OR u.nama_lengkap LIKE '%$search%')";
}

$where_sql = "";
if (!empty($where_clauses)) {
    $where_sql = " WHERE " . implode(' AND ', $where_clauses);
}

// Active Academic Year
$active_tahun_id = (int)($active_tahun_id ?? 0);

$sql = "SELECT ms.*,
               IF(ms.role = 'siswa', s.nama_siswa, u.nama_lengkap) AS nama_user,
               IF(ms.role = 'siswa', k.nama_kelas, '-') AS nama_kelas
        FROM mood_survey ms
        LEFT JOIN siswa s ON ms.role = 'siswa' AND ms.user_id = s.id
        LEFT JOIN siswa_kelas sk ON ms.role = 'siswa' AND s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
        LEFT JOIN kelas k ON sk.kelas_id = k.id
        LEFT JOIN users u ON ms.role = 'guru' AND ms.user_id = u.id
        $where_sql
        ORDER BY ms.tanggal DESC, ms.created_at DESC";

$result = mysqli_query($conn, $sql);

$mood_labels = [
    'sangat_baik' => '😃 Sangat Baik',
    'bersemangat' => '💪 Bersemangat',
    'biasa_saja' => '😐 Biasa Saja',
    'lelah' => '😴 Lelah',
    'stres' => '😔 Stres',
    'sedih' => '😢 Sedih'
];

$data = [];
$data[] = [
    '<b>Tanggal</b>',
    '<b>Waktu Submit</b>',
    '<b>Nama Pengguna</b>',
    '<b>Peran</b>',
    '<b>Kelas (Khusus Siswa)</b>',
    '<b>Mood Terpilih</b>'
];

while ($row = mysqli_fetch_assoc($result)) {
    $mood_label = $mood_labels[$row['mood']] ?? ucfirst($row['mood']);
    $data[] = [
        date('d-m-Y', strtotime($row['tanggal'])),
        date('H:i:s', strtotime($row['created_at'])) . ' WIB',
        $row['nama_user'] ?? '-',
        strtoupper($row['role']),
        $row['nama_kelas'] ?? '-',
        $mood_label
    ];
}

// Styling cells with borders and backgrounds
foreach ($data as $rowIndex => &$row) {
    foreach ($row as $colIndex => &$cell) {
        if ($rowIndex === 0) {
            $cell = '<style bgcolor="#E2E8F0" border="thin">' . $cell . '</style>';
        } else {
            $cell = '<style border="thin">' . $cell . '</style>';
        }
    }
}

$xlsx = SimpleXLSXGen::fromArray($data);
$filename = "Rekap_Mood_Harian_" . date('Ymd_His') . ".xlsx";
$xlsx->downloadAs($filename);
exit();
?>
