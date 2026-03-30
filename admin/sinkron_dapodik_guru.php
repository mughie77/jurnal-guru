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
    $rows = $dapodik->getGuru();

    if (empty($rows)) {
        echo json_encode(['success' => false, 'message' => 'Tidak ada data GTK yang ditemukan di Dapodik.']);
        exit;
    }

    mysqli_begin_transaction($conn);

    $count = 0;

    // Process Guru
    foreach ($rows as $row) {
        $nip = $row['nip'] ?? $row['nik'] ?? ''; // fallback to NIK if NIP is empty
        if (empty($nip)) continue;

        $nama = $row['nama'] ?? '';
        $alamat = $row['alamat_jalan'] ?? null;
        $telp = $row['nomor_telepon_seluler'] ?? $row['nomor_telepon_rumah'] ?? null;
        $pass = password_hash($nip, PASSWORD_DEFAULT);

        // 1. Ensure user exists
        $stmt_user = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role)
                                           VALUES (?, ?, ?, 'guru')
                                           ON DUPLICATE KEY UPDATE
                                           nama_lengkap = VALUES(nama_lengkap)");
        mysqli_stmt_bind_param($stmt_user, "sss", $nama, $nip, $pass);
        mysqli_stmt_execute($stmt_user);

        // Get user_id (if just inserted or existing)
        $uid_res = mysqli_query($conn, "SELECT id FROM users WHERE username = '$nip'");
        $uid = mysqli_fetch_assoc($uid_res)['id'];

        // 2. Ensure guru exists
        $stmt_guru = mysqli_prepare($conn, "INSERT INTO guru (user_id, nip, alamat, no_telp)
                                           VALUES (?, ?, ?, ?)
                                           ON DUPLICATE KEY UPDATE
                                           alamat = VALUES(alamat),
                                           no_telp = VALUES(no_telp)");
        mysqli_stmt_bind_param($stmt_guru, "isss", $uid, $nip, $alamat, $telp);
        mysqli_stmt_execute($stmt_guru);

        $count++;
    }

    mysqli_commit($conn);
    echo json_encode(['success' => true, 'message' => "Berhasil menyinkronkan $count data Guru/Tendik dari Dapodik."]);

} catch (Exception $e) {
    if (isset($conn)) mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
