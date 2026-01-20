<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Validasi input
if (!isset($_GET['kelas_id']) || !isset($_GET['tanggal'])) {
    echo json_encode(['success' => false, 'message' => 'Parameter tidak lengkap.']);
    exit;
}

$kelas_id = (int)$_GET['kelas_id'];
$tanggal = $_GET['tanggal'];

// 1. Ambil tahun pelajaran aktif
$active_year_query = mysqli_query($conn, "SELECT id FROM tahun_pelajaran WHERE status = 'aktif'");
if (mysqli_num_rows($active_year_query) == 0) {
    echo json_encode(['success' => false, 'message' => 'Tidak ada tahun pelajaran aktif.']);
    exit;
}
$active_year = mysqli_fetch_assoc($active_year_query);
$active_year_id = $active_year['id'];

// 2. Hitung total siswa di kelas pada tahun ajaran aktif
$total_siswa_query = "SELECT COUNT(id) as total FROM siswa_kelas WHERE kelas_id = $kelas_id AND tahun_pelajaran_id = $active_year_id";
$total_siswa_result = mysqli_query($conn, $total_siswa_query);
$total_siswa = mysqli_fetch_assoc($total_siswa_result)['total'];

// 3. Hitung rekap absensi untuk tanggal dan kelas yang dipilih
$rekap_query = "
    SELECT
        a.status,
        COUNT(a.id) as jumlah
    FROM absensi a
    JOIN siswa_kelas sk ON a.siswa_id = sk.siswa_id
    WHERE
        sk.kelas_id = $kelas_id
        AND a.tanggal = '$tanggal'
        AND sk.tahun_pelajaran_id = $active_year_id
    GROUP BY a.status
";
$rekap_result = mysqli_query($conn, $rekap_query);

$rekap_data = [
    'Hadir' => 0,
    'Sakit' => 0,
    'Izin' => 0,
    'Tanpa Keterangan' => 0 // Ini juga dihitung jika ada yang diinput manual
];

$total_tercatat = 0;
while ($row = mysqli_fetch_assoc($rekap_result)) {
    if (isset($rekap_data[$row['status']])) {
        $rekap_data[$row['status']] = (int)$row['jumlah'];
        $total_tercatat += (int)$row['jumlah'];
    }
}

// 4. Hitung jumlah Alfa (siswa yang tidak memiliki catatan absensi)
// Alfa = Total Siswa - (Hadir + Sakit + Izin + Tanpa Keterangan)
$jumlah_alfa = $total_siswa - $total_tercatat;
// Jika ada yang diinput 'Tanpa Keterangan', tambahkan ke total alfa
$jumlah_alfa += $rekap_data['Tanpa Keterangan'];


// Siapkan data untuk dikirim
$summary = [
    'success' => true,
    'total_siswa' => (int)$total_siswa,
    'jml_hadir' => $rekap_data['Hadir'],
    'jml_sakit' => $rekap_data['Sakit'],
    'jml_izin' => $rekap_data['Izin'],
    'jml_alfa' => $jumlah_alfa < 0 ? 0 : $jumlah_alfa // Pastikan tidak negatif
];

echo json_encode($summary);

mysqli_close($conn);
?>