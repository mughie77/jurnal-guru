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

        <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
            <?php
            $tables = [
                'siswa' => [
                    'tempat_lahir' => "VARCHAR(100) DEFAULT NULL AFTER foto",
                    'tanggal_lahir' => "DATE DEFAULT NULL AFTER tempat_lahir",
                    'berkas_kk' => "VARCHAR(255) DEFAULT NULL AFTER tanggal_lahir",
                    'berkas_ijazah' => "VARCHAR(255) DEFAULT NULL AFTER berkas_kk"
                ],
                'guru' => [
                    'tempat_lahir' => "VARCHAR(100) DEFAULT NULL AFTER foto",
                    'tanggal_lahir' => "DATE DEFAULT NULL AFTER tempat_lahir"
                ],
                'absensi_harian' => [
                    'file_surat' => "VARCHAR(255) DEFAULT NULL AFTER keterangan",
                    'status_verifikasi' => "ENUM('pending','disetujui','ditolak') DEFAULT 'pending' AFTER file_surat"
                ],
                'jurnal' => [
                    'latitude' => "VARCHAR(50) DEFAULT NULL AFTER keterangan",
                    'longitude' => "VARCHAR(50) DEFAULT NULL AFTER latitude"
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
                'radius_absen' => '30',
                'siswa_gps_absen' => 'nonaktif'
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

            // Create mood_survey table
            $create_mood = "CREATE TABLE IF NOT EXISTS `mood_survey` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) NOT NULL,
                `role` enum('siswa','guru') NOT NULL,
                `mood` varchar(50) NOT NULL,
                `tanggal` date NOT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_user_daily` (`user_id`, `role`, `tanggal`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if (mysqli_query($conn, $create_mood)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>mood_survey</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>mood_survey</b>: " . mysqli_error($conn)];
            }

            // Create panic_button table
            $create_panic = "CREATE TABLE IF NOT EXISTS `panic_button` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `siswa_id` int(11) NOT NULL,
                `nama_siswa` varchar(150) NOT NULL,
                `keterangan` text NOT NULL,
                `latitude` decimal(11,8) NOT NULL,
                `longitude` decimal(11,8) NOT NULL,
                `akurasi` float NOT NULL,
                `tanggal` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                CONSTRAINT `panic_button_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if (mysqli_query($conn, $create_panic)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>panic_button</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>panic_button</b>: " . mysqli_error($conn)];
            }

            // Create kritik_saran table
            $create_kritik = "CREATE TABLE IF NOT EXISTS `kritik_saran` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `user_id` int(11) DEFAULT NULL,
                `nama_pengirim` varchar(150) NOT NULL,
                `role` enum('siswa','guru') NOT NULL,
                `subjek` varchar(255) NOT NULL,
                `isi` text NOT NULL,
                `umpan_balik` text DEFAULT NULL,
                `tanggal` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if (mysqli_query($conn, $create_kritik)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>kritik_saran</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>kritik_saran</b>: " . mysqli_error($conn)];
            }

            foreach ($logs as $log) {
                $color = 'text-blue-600 bg-blue-50';
                if ($log['status'] == 'success') {
                    $color = 'text-green-600 bg-green-50';
                } elseif ($log['status'] == 'error') {
                    $color = 'text-red-600 bg-red-50';
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
