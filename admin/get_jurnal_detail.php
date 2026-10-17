<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

authorize_role(['admin', 'waka', 'guru']);

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Jurnal tidak valid']);
    exit();
}

// Fetch Jurnal Detail
$query_jurnal = "SELECT j.*, u.nama_lengkap, k.nama_kelas, mp.nama_mapel, mp.kode_mapel
                 FROM jurnal j
                 JOIN guru g ON j.guru_id = g.id
                 JOIN users u ON g.user_id = u.id
                 JOIN kelas k ON j.kelas_id = k.id
                 JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                 WHERE j.id = ?
                 LIMIT 1";

$stmt = mysqli_prepare($conn, $query_jurnal);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res_jurnal = mysqli_stmt_get_result($stmt);

if (!$jurnal = mysqli_fetch_assoc($res_jurnal)) {
    echo json_encode(['success' => false, 'message' => 'Data jurnal tidak ditemukan']);
    exit();
}

// Format date time
$jurnal['created_at'] = !empty($jurnal['created_at']) ? date('d M Y H:i', strtotime($jurnal['created_at'])) : date('d M Y', strtotime($jurnal['tanggal']));
$jurnal['keterangan'] = !empty($jurnal['keterangan']) ? htmlspecialchars($jurnal['keterangan']) : '-';
$jurnal['materi'] = htmlspecialchars($jurnal['materi'] ?? '-');

// Fetch Students Attendance for this Jurnal
$query_siswa = "SELECT s.nis, s.nama_siswa, aj.status
                FROM absensi_jurnal aj
                JOIN siswa s ON aj.siswa_id = s.id
                WHERE aj.jurnal_id = ?
                ORDER BY s.nama_siswa ASC";

$stmt_s = mysqli_prepare($conn, $query_siswa);
mysqli_stmt_bind_param($stmt_s, "i", $id);
mysqli_stmt_execute($stmt_s);
$res_siswa = mysqli_stmt_get_result($stmt_s);

$students = [];
while ($row_s = mysqli_fetch_assoc($res_siswa)) {
    $students[] = [
        'nis' => htmlspecialchars($row_s['nis'] ?? '-'),
        'nama_siswa' => htmlspecialchars($row_s['nama_siswa']),
        'status' => $row_s['status']
    ];
}

echo json_encode([
    'success' => true,
    'jurnal' => $jurnal,
    'students' => $students
]);
exit();
