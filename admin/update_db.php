<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Only admin can run migration
authorize_role(['admin']);

$is_ajax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($is_ajax) {
    ob_start();
}
?>
<?php if (!$is_ajax): ?>
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
<?php endif; ?>

        <div class="space-y-4 max-h-[400px] overflow-y-auto pr-2">
            <?php
            $tables = [
                'siswa' => [
                    'tempat_lahir' => "VARCHAR(100) DEFAULT NULL AFTER foto",
                    'tanggal_lahir' => "DATE DEFAULT NULL AFTER tempat_lahir",
                    'berkas_kk' => "VARCHAR(255) DEFAULT NULL AFTER tanggal_lahir",
                    'berkas_ijazah' => "VARCHAR(255) DEFAULT NULL AFTER berkas_kk",
                    'no_wa_ortu' => "VARCHAR(20) DEFAULT NULL AFTER berkas_ijazah"
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
                ],
                'kelas' => [
                    'jadwal_pdf' => "VARCHAR(255) DEFAULT NULL AFTER wali_kelas_id"
                ]
            ];

            $logs = [];

            // Ensure uploads/konsultasi directory exists
            $upload_dir = __DIR__ . '/../uploads/konsultasi';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0775, true);
                // Create secure .htaccess
                $htaccess_content = "# Prevent PHP execution in this directory\n<Files \"*.php\">\n    Order Deny,Allow\n    Deny from all\n</Files>\n\n# Ensure images are served correctly\nAddType image/jpeg .jpg .jpeg\n";
                file_put_contents($upload_dir . '/.htaccess', $htaccess_content);
                $logs[] = ['status' => 'success', 'msg' => "Directory <b>uploads/konsultasi/</b> created with secure .htaccess"];
            }

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
                'siswa_gps_absen' => 'nonaktif',
                'pilih_tanggal_jurnal' => 'nonaktif'
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

            // Rename panic_button to pengaduan if panic_button exists
            $check_panic_table = mysqli_query($conn, "SHOW TABLES LIKE 'panic_button'");
            if (mysqli_num_rows($check_panic_table) > 0) {
                // Check if pengaduan table already exists
                $check_pengaduan_table = mysqli_query($conn, "SHOW TABLES LIKE 'pengaduan'");
                if (mysqli_num_rows($check_pengaduan_table) == 0) {
                    $rename_query = "RENAME TABLE `panic_button` TO `pengaduan`";
                    if (mysqli_query($conn, $rename_query)) {
                        $logs[] = ['status' => 'success', 'msg' => "Table <b>panic_button</b> successfully renamed to <b>pengaduan</b>"];
                    } else {
                        $logs[] = ['status' => 'error', 'msg' => "Failed to rename <b>panic_button</b> to <b>pengaduan</b>: " . mysqli_error($conn)];
                    }
                } else {
                    $logs[] = ['status' => 'info', 'msg' => "Table <b>pengaduan</b> already exists; skipping rename of <b>panic_button</b>"];
                }
            } else {
                // If panic_button table does not exist, create pengaduan directly if it does not exist
                $create_pengaduan = "CREATE TABLE IF NOT EXISTS `pengaduan` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `siswa_id` int(11) NOT NULL,
                    `nama_siswa` varchar(150) NOT NULL,
                    `keterangan` text NOT NULL,
                    `latitude` decimal(11,8) NOT NULL,
                    `longitude` decimal(11,8) NOT NULL,
                    `akurasi` float NOT NULL,
                    `tanggal` timestamp NULL DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`),
                    CONSTRAINT `pengaduan_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

                if (mysqli_query($conn, $create_pengaduan)) {
                    $logs[] = ['status' => 'success', 'msg' => "Table <b>pengaduan</b> created successfully"];
                } else {
                    $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>pengaduan</b>: " . mysqli_error($conn)];
                }
            }

            // Drop old tables first to overwrite any restricted foreign key structures cleanly
            mysqli_query($conn, "DROP TABLE IF EXISTS `konsultasi_pesan`");
            mysqli_query($conn, "DROP TABLE IF EXISTS `konsultasi`");

            // Create the new robust, highly compatible 'konsultasi' table (No foreign key constraints to prevent driver clashes)
            $create_konsultasi = "CREATE TABLE IF NOT EXISTS `konsultasi` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `siswa_id` int(11) NOT NULL,
                `guru_id` int(11) NOT NULL,
                `subjek` varchar(255) NOT NULL,
                `status` varchar(50) NOT NULL DEFAULT 'open',
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `siswa_id` (`siswa_id`),
                KEY `guru_id` (`guru_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if (mysqli_query($conn, $create_konsultasi)) {
                $logs[] = ['status' => 'success', 'msg' => "Robust Table <b>konsultasi</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>konsultasi</b>: " . mysqli_error($conn)];
            }

            // Create the new robust, highly compatible 'konsultasi_pesan' table
            $create_konsultasi_pesan = "CREATE TABLE IF NOT EXISTS `konsultasi_pesan` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `konsultasi_id` int(11) NOT NULL,
                `pengirim_role` varchar(50) NOT NULL,
                `pesan` text NOT NULL,
                `lampiran_foto` varchar(255) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `konsultasi_id` (`konsultasi_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

            if (mysqli_query($conn, $create_konsultasi_pesan)) {
                $logs[] = ['status' => 'success', 'msg' => "Robust Table <b>konsultasi_pesan</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>konsultasi_pesan</b>: " . mysqli_error($conn)];
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

            // Create kategori_perangkat table
            $create_kat = "CREATE TABLE IF NOT EXISTS `kategori_perangkat` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nama_kategori` varchar(100) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `nama_kategori_unique` (`nama_kategori`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_kat)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>kategori_perangkat</b> created successfully"];
                // Seed default categories
                $defaults = ['RPP', 'Silabus', 'Modul Ajar', 'Buku Digital', 'Video Pembelajaran', 'Lainnya'];
                foreach ($defaults as $d) {
                    $d_esc = mysqli_real_escape_string($conn, $d);
                    mysqli_query($conn, "INSERT IGNORE INTO kategori_perangkat (nama_kategori) VALUES ('$d_esc')");
                }
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>kategori_perangkat</b>: " . mysqli_error($conn)];
            }

            // Create piket_guru table
            $create_pg = "CREATE TABLE IF NOT EXISTS `piket_guru` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat') NOT NULL,
                `guru_id` INT NOT NULL,
                UNIQUE KEY `unique_hari_guru` (`hari`, `guru_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_pg)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>piket_guru</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>piket_guru</b>: " . mysqli_error($conn)];
            }

            // Create tugas_kelas table
            $create_tk = "CREATE TABLE IF NOT EXISTS `tugas_kelas` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `guru_id` INT NOT NULL,
                `kelas_id` INT NOT NULL,
                `tanggal` DATE NOT NULL,
                `keterangan_tugas` TEXT NOT NULL,
                `file_lampiran` VARCHAR(255) DEFAULT NULL,
                `latitude` VARCHAR(50) DEFAULT NULL,
                `longitude` VARCHAR(50) DEFAULT NULL,
                `status_selesai` TINYINT(1) DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_tk)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>tugas_kelas</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>tugas_kelas</b>: " . mysqli_error($conn)];
            }

            // Create pengumuman table
            $create_pengumuman = "CREATE TABLE IF NOT EXISTS `pengumuman` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `judul` varchar(255) NOT NULL,
                `isi` text NOT NULL,
                `target` enum('semua','guru','siswa') NOT NULL DEFAULT 'semua',
                `file_lampiran` varchar(255) DEFAULT NULL,
                `created_by` varchar(100) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_pengumuman)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>pengumuman</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>pengumuman</b>: " . mysqli_error($conn)];
            }

            // Create tempat_pkl table
            $create_tpkl = "CREATE TABLE IF NOT EXISTS `tempat_pkl` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `nama_tempat` varchar(255) NOT NULL,
                `alamat` text DEFAULT NULL,
                `latitude` varchar(50) DEFAULT NULL,
                `longitude` varchar(50) DEFAULT NULL,
                `radius_absen` int(11) NOT NULL DEFAULT 50,
                `guru_pembimbing_id` int(11) DEFAULT NULL,
                `pembimbing_dudi` varchar(255) DEFAULT NULL,
                `no_telp_dudi` varchar(50) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                KEY `guru_pembimbing_id` (`guru_pembimbing_id`),
                CONSTRAINT `tempat_pkl_ibfk_1` FOREIGN KEY (`guru_pembimbing_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_tpkl)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>tempat_pkl</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>tempat_pkl</b>: " . mysqli_error($conn)];
            }

            // Create siswa_pkl table
            $create_spkl = "CREATE TABLE IF NOT EXISTS `siswa_pkl` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `siswa_id` int(11) NOT NULL,
                `tempat_pkl_id` int(11) NOT NULL,
                `tahun_pelajaran_id` int(11) NOT NULL,
                `status` enum('aktif','selesai') NOT NULL DEFAULT 'aktif',
                `created_at` timestamp NULL DEFAULT current_timestamp(),
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_siswa_tahun_pkl` (`siswa_id`, `tahun_pelajaran_id`),
                KEY `tempat_pkl_id` (`tempat_pkl_id`),
                CONSTRAINT `siswa_pkl_ibfk_1` FOREIGN KEY (`siswa_id`) REFERENCES `siswa` (`id`) ON DELETE CASCADE,
                CONSTRAINT `siswa_pkl_ibfk_2` FOREIGN KEY (`tempat_pkl_id`) REFERENCES `tempat_pkl` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

            if (mysqli_query($conn, $create_spkl)) {
                $logs[] = ['status' => 'success', 'msg' => "Table <b>siswa_pkl</b> created successfully"];
            } else {
                $logs[] = ['status' => 'error', 'msg' => "Failed creating <b>siswa_pkl</b>: " . mysqli_error($conn)];
            }

            foreach ($logs as $log) {
                $color = 'text-blue-600 bg-blue-50';
                if ($log['status'] == 'success') {
                    $color = 'text-green-600 bg-green-50';
                } elseif ($log['status'] == 'error') {
                    $color = 'text-red-600 bg-red-50';
                }

                echo "<div class='p-3 rounded-xl text-xs $color flex items-start gap-2'>";
                echo "<div>{$log['msg']}</div>";
                echo "</div>";
            }
            ?>
        </div>

<?php if (!$is_ajax): ?>
        <div class="mt-8">
            <a href="index.php" class="block w-full py-4 bg-slate-800 text-white text-center font-bold rounded-2xl hover:bg-slate-900 transition-all">
                Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>
<?php endif; ?>
