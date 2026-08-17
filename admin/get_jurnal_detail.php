<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin', 'waka', 'guru']);

header('Content-Type: application/json');

$jurnal_id = (int)($_GET['id'] ?? 0);

if ($jurnal_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID Jurnal tidak valid.']);
    exit;
}

// Fetch Jurnal Meta Details
$query = "SELECT j.*, u.nama_lengkap, mp.nama_mapel, k.nama_kelas
          FROM jurnal j
          JOIN guru g ON j.guru_id = g.id
          JOIN users u ON g.user_id = u.id
          JOIN mata_pelajaran mp ON j.mapel_id = mp.id
          JOIN kelas k ON j.kelas_id = k.id
          WHERE j.id = $jurnal_id";
$res = mysqli_query($conn, $query);
$jurnal = mysqli_fetch_assoc($res);

if (!$jurnal) {
    echo json_encode(['success' => false, 'message' => 'Data jurnal tidak ditemukan.']);
    exit;
}

// Fetch Students Attendance
$students = [];
$q_stud = "SELECT aj.status, s.nama_siswa, s.nis
           FROM absensi_jurnal aj
           JOIN siswa s ON aj.siswa_id = s.id
           WHERE aj.jurnal_id = $jurnal_id
           ORDER BY s.nama_siswa ASC";
$res_stud = mysqli_query($conn, $q_stud);
while ($row = mysqli_fetch_assoc($res_stud)) {
    $students[] = [
        'nis' => $row['nis'],
        'nama_siswa' => $row['nama_siswa'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'jurnal' => [
        'tanggal' => date('d M Y', strtotime($jurnal['tanggal'])),
        'created_at' => date('d/m/Y H:i', strtotime($jurnal['created_at'])),
        'nama_lengkap' => $jurnal['nama_lengkap'],
        'nama_mapel' => $jurnal['nama_mapel'],
        'nama_kelas' => $jurnal['nama_kelas'],
        'jam_ke' => $jurnal['jam_ke'],
        'materi' => $jurnal['materi'],
        'keterangan' => $jurnal['keterangan'] ?: '-',
        'latitude' => $jurnal['latitude'],
        'longitude' => $jurnal['longitude'],
        'jml_hadir' => $jurnal['jml_hadir'],
        'jml_sakit' => $jurnal['jml_sakit'],
        'jml_izin' => $jurnal['jml_izin'],
        'jml_alfa' => $jurnal['jml_alfa']
    ],
    'students' => $students
]);
