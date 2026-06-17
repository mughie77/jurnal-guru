<?php
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Secure endpoint for siswa only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'siswa') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
    exit;
}

$siswa_id = $_SESSION['user_id'];
$status = $_POST['status'] ?? 'Izin';
$keterangan = $_POST['keterangan'] ?? '';
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;

// Validate status
if (!in_array($status, ['Sakit', 'Izin'])) {
    echo json_encode(['success' => false, 'message' => 'Jenis izin tidak valid.']);
    exit;
}

// Validate coordinates
if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Koordinat GPS diperlukan. Harap aktifkan lokasi.']);
    exit;
}

// Handle File Upload
$filename = null;
if (!empty($_FILES['file_surat']['name'])) {
    $target_dir = __DIR__ . "/../uploads/surat/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file_ext = strtolower(pathinfo($_FILES["file_surat"]["name"], PATHINFO_EXTENSION));
    $allowed_ext = ['png', 'jpg', 'jpeg', 'pdf'];

    if (in_array($file_ext, $allowed_ext)) {
        if ($_FILES["file_surat"]["size"] > 2 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file maksimal 2MB.']);
            exit;
        }

        $filename = "surat_" . time() . "_" . $siswa_id . "." . $file_ext;
        $target_file = $target_dir . $filename;

        if (!move_uploaded_file($_FILES["file_surat"]["tmp_name"], $target_file)) {
            echo json_encode(['success' => false, 'message' => 'Gagal mengupload file.']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Format file tidak didukung. Gunakan PNG, JPG, atau PDF.']);
        exit;
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Harap lampirkan surat keterangan/bukti.']);
    exit;
}

// Check if already checked in today
$today = date('Y-m-d');
$check = mysqli_query($conn, "SELECT id FROM absensi_harian WHERE siswa_id = $siswa_id AND tanggal = '$today'");
if (mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'message' => 'Data absensi hari ini sudah ada.']);
    exit;
}

// Store in absensi_harian
$now_time = date('H:i:s');
$keterangan_full = $keterangan . " (GPS: $lat, $lng)";

$stmt = mysqli_prepare($conn, "INSERT INTO absensi_harian (siswa_id, tanggal, waktu_masuk, status, keterangan, file_surat) VALUES (?, ?, ?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, "isssss", $siswa_id, $today, $now_time, $status, $keterangan_full, $filename);

if (mysqli_stmt_execute($stmt)) {
    ob_clean();
    echo json_encode(['success' => true, 'message' => "Pengajuan $status berhasil dikirim."]);
} else {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data pengajuan.']);
}
