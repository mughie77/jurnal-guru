<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Only admin can run migration
authorize_role(['admin']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration - CAKRA</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
    </style>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center p-6">
    <div class="max-w-md w-full bg-white rounded-3xl shadow-xl p-8">
        <div class="text-center mb-8">
            <h1 class="text-2xl font-black text-slate-800 uppercase tracking-tight">Database Migration</h1>
            <p class="text-slate-500 text-sm">System Update & Schema Synchronization</p>
        </div>

        <div class="space-y-4">
            <?php
            $tables = [
                'siswa' => [
                    'tempat_lahir' => "VARCHAR(100) DEFAULT NULL AFTER foto",
                    'tanggal_lahir' => "DATE DEFAULT NULL AFTER tempat_lahir",
                    'face_image' => "VARCHAR(255) DEFAULT NULL AFTER tanggal_lahir"
                ],
                'guru' => [
                    'tempat_lahir' => "VARCHAR(100) DEFAULT NULL AFTER foto",
                    'tanggal_lahir' => "DATE DEFAULT NULL AFTER tempat_lahir"
                ],
                'absensi_harian' => [
                    'file_surat' => "VARCHAR(255) DEFAULT NULL AFTER keterangan"
                ]
            ];

            $logs = [];

            foreach ($tables as $table => $columns) {
                foreach ($columns as $column => $definition) {
                    $check = mysqli_query($conn, "SHOW COLUMNS FROM `$table` LIKE '$column'");
                    if (mysqli_num_rows($check) == 0) {
                        $alter = mysqli_query($conn, "ALTER TABLE `$table` ADD `$column` $definition");
                        if ($alter) {
                            $logs[] = ['status' => 'success', 'msg' => "Added <b>$column</b> to <b>$table</b>"];
                        } else {
                            $logs[] = ['status' => 'error', 'msg' => "Failed adding <b>$column</b>: " . mysqli_error($conn)];
                        }
                    } else {
                        $logs[] = ['status' => 'info', 'msg' => "Column <b>$column</b> already exists in <b>$table</b>"];
                    }
                }
            }

            // New settings migration
            $new_settings = [
                'school_lat' => '-7.9135',
                'school_lng' => '113.8217',
                'radius_absen' => '30'
            ];

            foreach ($new_settings as $key => $val) {
                $check = mysqli_query($conn, "SELECT 1 FROM pengaturan WHERE nama_setting = '$key'");
                if (mysqli_num_rows($check) == 0) {
                    $ins = mysqli_query($conn, "INSERT INTO pengaturan (nama_setting, nilai_setting) VALUES ('$key', '$val')");
                    if ($ins) {
                        $logs[] = ['status' => 'success', 'msg' => "Added setting <b>$key</b>"];
                    } else {
                        $logs[] = ['status' => 'error', 'msg' => "Failed adding setting <b>$key</b>"];
                    }
                } else {
                    $logs[] = ['status' => 'info', 'msg' => "Setting <b>$key</b> already exists"];
                }
            }

            // Create perangkat_kelas table for many-to-many relationship
            $create_pk = "CREATE TABLE IF NOT EXISTS `perangkat_kelas` (
                `perangkat_id` int(11) NOT NULL,
                `kelas_id` int(11) NOT NULL,
                PRIMARY KEY (`perangkat_id`,`kelas_id`),
                CONSTRAINT `perangkat_kelas_ibfk_1` FOREIGN KEY (`perangkat_id`) REFERENCES `perangkat` (`id`) ON DELETE CASCADE,
                CONSTRAINT `perangkat_kelas_ibfk_2` FOREIGN KEY (`kelas_id`) REFERENCES `kelas` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_pk)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>perangkat_kelas</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>perangkat_kelas</b>: " . mysqli_error($conn)];
            }

            foreach ($logs as $log) {
                $color = 'text-blue-600 bg-blue-50';
                $icon = 'info-circle';
                if ($log['status'] == 'success') {
                    $color = 'text-green-600 bg-green-50';
                    $icon = 'check-circle';
                } elseif ($log['status'] == 'error') {
                    $color = 'text-red-600 bg-red-50';
                    $icon = 'exclamation-circle';
                }

                echo "<div class='p-4 rounded-2xl text-sm $color flex items-start gap-3'>";
                echo "<div>{$log['msg']}</div>";
                echo "</div>";
            }
            ?>
        </div>

        <div class="mt-8">
            <a href="index.php" class="block w-full py-4 bg-slate-800 text-white text-center font-bold rounded-2xl hover:bg-slate-900 transition-all">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
