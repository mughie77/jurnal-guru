<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$query = "SELECT nama_siswa, face_image, face_descriptor FROM siswa WHERE face_image IS NOT NULL";
$result = mysqli_query($conn, $query);

$students = [];
while ($row = mysqli_fetch_assoc($result)) {
    $students[] = [
        'name' => $row['nama_siswa'],
        'image' => BASE_URL . 'uploads/siswa/face/' . $row['face_image'],
        'descriptor' => !empty($row['face_descriptor']) ? json_decode($row['face_descriptor']) : null
    ];
}

echo json_encode($students);
?>
