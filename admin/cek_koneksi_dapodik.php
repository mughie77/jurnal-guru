<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'CSRF Token Invalid']);
    exit;
}

$url = rtrim($_POST['url'], '/');
$token = $_POST['token'];

// To check connection, we try to fetch the "Sekolah" info from Dapodik Web Service
// Endpoints are usually something like /getSekolah or /getGtk
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url . "/getSekolah");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
    if ($data && isset($data['rows'])) {
        $nama_sekolah = $data['rows'][0]['nama'] ?? 'Unknown';
        echo json_encode(['success' => true, 'message' => 'Tersambung ke ' . $nama_sekolah]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Format response Dapodik tidak sesuai atau data kosong.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'HTTP Error ' . $http_code . '. Pastikan URL dan Token benar.']);
}
