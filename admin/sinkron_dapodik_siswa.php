<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/dapodik_helper.php';

header('Content-Type: application/json');
authorize_role(['admin']);

if (!verify_csrf_token($_GET['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF Token Invalid']);
    exit;
}

try {
    $dapodik = new DapodikHelper($conn);
    $rows = $dapodik->getSiswa();

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada data siswa yang ditemukan di Dapodik.']);
        exit;
    }

    mysqli_begin_transaction($conn);

    $count = 0;
    $stmt = mysqli_prepare($conn, "INSERT INTO siswa (nis, nisn, nama_siswa, jenis_kelamin, alamat, no_telp, tempat_lahir, tanggal_lahir)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE
                                   nisn = VALUES(nisn),
                                   nama_siswa = VALUES(nama_siswa),
                                   jenis_kelamin = VALUES(jenis_kelamin),
                                   alamat = VALUES(alamat),
                                   no_telp = VALUES(no_telp),
                                   tempat_lahir = VALUES(tempat_lahir),
                                   tanggal_lahir = VALUES(tanggal_lahir)");

    foreach ($rows as $row) {
        $nis = $row['nipd'] ?? $row['nis'] ?? '';
        if (empty($nis)) continue; // skip if no NIS

        $nisn = $row['nisn'] ?? null;
        $nama = $row['nama'] ?? '';
        $jk = ($row['jenis_kelamin'] == 'P') ? 'P' : 'L';
        $alamat = $row['alamat_jalan'] ?? null;
        $telp = $row['nomor_telepon_seluler'] ?? $row['nomor_telepon_rumah'] ?? null;
        $tempat_lahir = $row['tempat_lahir'] ?? null;
        $tanggal_lahir = $row['tanggal_lahir'] ?? null;

        mysqli_stmt_bind_param($stmt, "ssssssss", $nis, $nisn, $nama, $jk, $alamat, $telp, $tempat_lahir, $tanggal_lahir);
        mysqli_stmt_execute($stmt);
        $count++;
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => "Berhasil menyinkronkan $count data siswa dari Dapodik."]);

} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
