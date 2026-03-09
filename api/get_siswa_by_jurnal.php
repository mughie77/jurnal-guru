<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

authorize_role(['admin', 'guru']);
header('Content-Type: application/json');

$jurnal_id = (int)($_GET['jurnal_id'] ?? 0);
if ($jurnal_id <= 0) { echo json_encode([]); exit; }

// Get class_id from journal
$res_j = mysqli_query($conn, "SELECT kelas_id, tahun_pelajaran_id FROM jurnal WHERE id = $jurnal_id");
$j = mysqli_fetch_assoc($res_j);
$kid = $j['kelas_id'];
$tid = $j['tahun_pelajaran_id'];

$query = "SELECT s.id, s.nis, s.nama_siswa, s.jenis_kelamin
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $tid
          ORDER BY s.nama_siswa ASC";

$result = mysqli_query($conn, $query);
$siswa = [];
while ($row = mysqli_fetch_assoc($result)) { $siswa[] = $row; }
echo json_encode($siswa);
?>
