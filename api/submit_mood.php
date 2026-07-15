<?php
require_once __DIR__ . '/../config/database.php';

// Disable any potential warnings/notices outputting to the buffer
error_reporting(0);
ini_set('display_errors', 0);

// Ensure no whitespace before this point
ob_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Sesi berakhir, silakan login kembali.']);
    exit();
}

$uid = (int)$_SESSION['user_id'];
$role = $_SESSION['role'];

// Only allow siswa or guru roles
if (!in_array($role, ['siswa', 'guru'])) {
    http_response_code(403);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Hanya siswa atau guru yang dapat mengisi mood survey.']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Metode request tidak didukung.']);
    exit();
}

$mood = trim($_POST['mood'] ?? '');
$valid_moods = ['sangat_baik', 'bersemangat', 'biasa_saja', 'lelah', 'stres', 'sedih'];

if (empty($mood) || !in_array($mood, $valid_moods)) {
    http_response_code(400);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Pilihan mood tidak valid.']);
    exit();
}

$tanggal = date('Y-m-d');

// Check if already submitted today to prevent duplicate key error
$check_stmt = mysqli_prepare($conn, "SELECT id FROM mood_survey WHERE user_id = ? AND role = ? AND tanggal = ?");
mysqli_stmt_bind_param($check_stmt, "iss", $uid, $role, $tanggal);
mysqli_stmt_execute($check_stmt);
mysqli_stmt_store_result($check_stmt);

if (mysqli_stmt_num_rows($check_stmt) > 0) {
    http_response_code(409);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Anda sudah mengisi mood harian Anda hari ini.']);
    mysqli_stmt_close($check_stmt);
    exit();
}
mysqli_stmt_close($check_stmt);

// Insert mood record
$ins_stmt = mysqli_prepare($conn, "INSERT INTO mood_survey (user_id, role, mood, tanggal) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($ins_stmt, "isss", $uid, $role, $mood, $tanggal);

if (mysqli_stmt_execute($ins_stmt)) {
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Mood Anda hari ini berhasil disimpan! Semoga aktivitas Anda menyenangkan.'
    ]);
} else {
    http_response_code(500);
    ob_clean();
    echo json_encode([
        'success' => false,
        'error' => 'Gagal menyimpan mood ke database: ' . mysqli_error($conn)
    ]);
}
mysqli_stmt_close($ins_stmt);
exit();
?>
