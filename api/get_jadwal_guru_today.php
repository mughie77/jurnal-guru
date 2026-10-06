<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'guru') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = (int)$_SESSION['user_id'];
$guru_id = 0;
$res_g = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id LIMIT 1");
if ($res_g && $row_g = mysqli_fetch_assoc($res_g)) {
    $guru_id = (int)$row_g['id'];
}

if ($guru_id <= 0) {
    echo json_encode(['success' => false, 'schedules' => []]);
    exit();
}

// Get today's day name in Indonesian
$days_map = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$hari_ini = $days_map[date('l')];
$today_date = date('Y-m-d');

$query = "SELECT jp.id, jp.jam_ke, jp.jam_mulai, k.nama_kelas, m.nama_mapel,
            (SELECT COUNT(*) FROM jurnal j WHERE j.guru_id = jp.guru_id AND j.kelas_id = jp.kelas_id AND j.tanggal = '$today_date' AND (j.jadwal_id = jp.id OR (j.jadwal_id IS NULL AND j.mapel_id = jp.mapel_id))) as total_jurnal
          FROM jadwal_pelajaran jp
          JOIN kelas k ON jp.kelas_id = k.id
          JOIN mata_pelajaran m ON jp.mapel_id = m.id
          WHERE jp.guru_id = $guru_id AND jp.hari = '$hari_ini' AND jp.jam_mulai IS NOT NULL
          ORDER BY jp.jam_mulai ASC";

$result = mysqli_query($conn, $query);
$schedules = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $row['is_filled'] = ((int)$row['total_jurnal']) > 0;
        $schedules[] = $row;
    }
}

echo json_encode(['success' => true, 'today_date' => $today_date, 'schedules' => $schedules]);
exit();
