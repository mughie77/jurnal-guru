<?php
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
$lat = $_POST['lat'] ?? null;
$lng = $_POST['lng'] ?? null;

if (!$lat || !$lng) {
    echo json_encode(['success' => false, 'message' => 'Koordinat tidak valid.']);
    exit;
}

// Get school settings
$res_set = mysqli_query($conn, "SELECT * FROM pengaturan");
$sets = [];
while ($r = mysqli_fetch_assoc($res_set)) $sets[$r['nama_setting']] = $r['nilai_setting'];

$school_lat = $sets['school_lat'] ?? '-7.9135';
$school_lng = $sets['school_lng'] ?? '113.8217';
$radius_absen = (int)($sets['radius_absen'] ?? 30);
$jam_masuk = $sets['jam_masuk_sekolah'] ?? '07:00:00';

// Calculate Distance (Server-side validation)
function vincentyGreatCircleDistance($lat1, $lon1, $lat2, $lon2, $earthRadius = 6371000) {
    $latFrom = deg2rad($lat1);
    $lonFrom = deg2rad($lon1);
    $latTo = deg2rad($lat2);
    $lonTo = deg2rad($lon2);

    $lonDelta = $lonTo - $lonFrom;
    $a = pow(cos($latTo) * sin($lonDelta), 2) + pow(cos($latFrom) * sin($latTo) - sin($latFrom) * cos($latTo) * cos($lonDelta), 2);
    $b = sin($latFrom) * sin($latTo) + cos($latFrom) * cos($latTo) * cos($lonDelta);

    $angle = atan2(sqrt($a), $b);
    return $angle * $earthRadius;
}

$distance = vincentyGreatCircleDistance($lat, $lng, $school_lat, $school_lng);

if ($distance > ($radius_absen + 5)) { // 5m buffer for GPS jitter
    echo json_encode(['success' => false, 'message' => 'Anda berada di luar radius sekolah (' . round($distance) . 'm).']);
    exit;
}

// Check if already checked in today
$today = date('Y-m-d');
$check = mysqli_query($conn, "SELECT id FROM absensi_harian WHERE siswa_id = $siswa_id AND tanggal = '$today'");
if (mysqli_num_rows($check) > 0) {
    echo json_encode(['success' => false, 'message' => 'Anda sudah melakukan absensi hari ini.']);
    exit;
}

// Determine status based on time
$now_time = date('H:i:s');
$status = (strtotime($now_time) > strtotime($jam_masuk)) ? 'Terlambat' : 'Hadir';

$stmt = mysqli_prepare($conn, "INSERT INTO absensi_harian (siswa_id, tanggal, waktu_masuk, status, keterangan) VALUES (?, ?, ?, ?, ?)");
$keterangan = "Absensi GPS (Lat: $lat, Lng: $lng, Dist: " . round($distance, 2) . "m)";
mysqli_stmt_bind_param($stmt, "issss", $siswa_id, $today, $now_time, $status, $keterangan);

if (mysqli_stmt_execute($stmt)) {
    echo json_encode(['success' => true, 'message' => "Berhasil absen. Status: $status pada $now_time"]);
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal menyimpan data absensi.']);
}
