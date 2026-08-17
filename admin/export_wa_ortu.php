<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSXGen.php';

use Shuchkin\SimpleXLSXGen;

authorize_role(['admin', 'waka']);

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$kelas_id = mysqli_real_escape_string($conn, $_GET['kelas_id'] ?? '');

$where = " WHERE tp.status = 'aktif'";
if (!empty($search)) {
    $where .= " AND (s.nama_siswa LIKE '%$search%' OR s.nis LIKE '%$search%' OR s.nisn LIKE '%$search%')";
}
if (!empty($kelas_id)) {
    $where .= " AND sk.kelas_id = '$kelas_id'";
}

$query = "SELECT s.id, s.nis, s.nisn, s.nama_siswa, s.berkas_kk, s.berkas_ijazah, s.no_wa_ortu, k.nama_kelas
          FROM siswa s
          JOIN siswa_kelas sk ON s.id = sk.siswa_id
          JOIN kelas k ON sk.kelas_id = k.id
          JOIN tahun_pelajaran tp ON sk.tahun_pelajaran_id = tp.id
          $where
          ORDER BY k.nama_kelas ASC, s.nama_siswa ASC";
$result = mysqli_query($conn, $query);

$data = [];

// Header rows
$data[] = ['<b>REKAP NO. WA ORANG TUA & STATUS BERKAS SISWA</b>'];
$data[] = ['Tanggal Unduh: ' . date('d/m/Y H:i')];
$data[] = [''];

// Table columns
$data[] = [
    '<b>No</b>',
    '<b>NIS</b>',
    '<b>NISN</b>',
    '<b>Nama Siswa</b>',
    '<b>Kelas</b>',
    '<b>No. WA Orang Tua</b>',
    '<b>Kartu Keluarga (KK)</b>',
    '<b>Ijazah</b>',
    '<b>Status Berkas</b>'
];

$no = 1;
while ($row = mysqli_fetch_assoc($result)) {
    $has_kk = !empty($row['berkas_kk']);
    $has_ijazah = !empty($row['berkas_ijazah']);
    $is_lengkap = ($has_kk && $has_ijazah);

    $data[] = [
        $no++,
        $row['nis'],
        $row['nisn'] ?: '-',
        $row['nama_siswa'],
        $row['nama_kelas'],
        $row['no_wa_ortu'] ?: 'Belum Diisi',
        $has_kk ? 'Sudah Upload' : 'Belum Upload',
        $has_ijazah ? 'Sudah Upload' : 'Belum Upload',
        $is_lengkap ? 'Lengkap' : 'Belum Lengkap'
    ];
}

$filename = 'rekap_wa_ortu_berkas_' . date('Ymd_His') . '.xlsx';

$xlsx = SimpleXLSXGen::fromArray($data);
$xlsx->downloadAs($filename);
exit;
