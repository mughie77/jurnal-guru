<?php
// Secure External Data Retrieval API
ob_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

// Define a default secure API Key (Admin can change or use this token)
$expected_key = 'CAKRA_SECURE_API_KEY_2026';

$provided_key = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

if (empty($provided_key) || $provided_key !== $expected_key) {
    http_response_code(401);
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Invalid or missing API Key. Use X-API-KEY header or api_key parameter.']);
    exit;
}

$resource = $_GET['resource'] ?? '';
$response = ['success' => true, 'resource' => $resource];

switch ($resource) {
    case 'students':
        $res = mysqli_query($conn, "SELECT id, nis, nisn, nama_siswa, jenis_kelamin, no_telp, tempat_lahir, tanggal_lahir FROM siswa ORDER BY nama_siswa ASC");
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        $response['data'] = $data;
        break;

    case 'teachers':
        $res = mysqli_query($conn, "SELECT g.id, g.nip, u.nama_lengkap, g.no_telp, g.tempat_lahir, g.tanggal_lahir
                                    FROM guru g
                                    JOIN users u ON g.user_id = u.id
                                    ORDER BY u.nama_lengkap ASC");
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        $response['data'] = $data;
        break;

    case 'classes':
        $res = mysqli_query($conn, "SELECT id, nama_kelas, wali_kelas_id FROM kelas ORDER BY nama_kelas ASC");
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        $response['data'] = $data;
        break;

    case 'attendance':
        $date = $_GET['date'] ?? date('Y-m-d');
        // Validate date
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = date('Y-m-d');
        }
        $res = mysqli_query($conn, "SELECT ah.id, s.nis, s.nama_siswa, ah.tanggal, ah.waktu_masuk, ah.status, ah.keterangan
                                    FROM absensi_harian ah
                                    JOIN siswa s ON ah.siswa_id = s.id
                                    WHERE ah.tanggal = '$date'
                                    ORDER BY ah.waktu_masuk ASC");
        $data = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $data[] = $row;
        }
        $response['date'] = $date;
        $response['data'] = $data;
        break;

    default:
        http_response_code(400);
        $response['success'] = false;
        $response['error'] = 'Invalid resource. Available resources: students, teachers, classes, attendance';
        break;
}

ob_clean();
echo json_encode($response);
exit();
