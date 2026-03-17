<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
authorize_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF Token Invalid']);
    exit;
}

$url = rtrim($_POST['url'] ?? '', '/');
$token = $_POST['token'] ?? '';

if (empty($url) || empty($token)) {
    echo json_encode(['success' => false, 'message' => 'URL dan Token harus diisi.']);
    exit;
}

// To check connection, we try to fetch the "Sekolah" info from Dapodik Web Service
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "/getSekolah");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Often needed for local web services
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Accept: application/json"
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    echo json_encode(['success' => false, 'message' => 'CURL Error: ' . $error]);
} elseif ($http_code === 200) {
    $data = json_decode($response, true);
    if ($data !== null && isset($data['rows'])) {
        if (!empty($data['rows'])) {
            $nama_sekolah = $data['rows'][0]['nama'] ?? 'Instansi Terdaftar';
            echo json_encode(['success' => true, 'message' => 'Tersambung ke ' . $nama_sekolah]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Koneksi berhasil, tapi Dapodik mengembalikan data sekolah kosong.']);
        }
    } else {
        $json_err = json_last_error_msg();
        echo json_encode(['success' => false, 'message' => 'Koneksi berhasil, tapi format data tidak valid: ' . $json_err]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Gagal terhubung (HTTP ' . $http_code . '). Periksa URL, Token, dan pastikan Web Service Dapodik aktif.']);
}
