<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Backup & Restore Sistem";
$message = '';
$message_type = '';

// Handle Full System Backup (ZIP = Database SQL + Uploads Media folder)
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    ob_start();

    try {
        // 1. Generate Database SQL Dump
        $tables = [];
        $result = mysqli_query($conn, "SHOW TABLES");
        while ($row = mysqli_fetch_row($result)) {
            // Skip kategori_perangkat or others if not existing, but SHOW TABLES lists all
            $tables[] = $row[0];
        }

        $sql = "-- CAKRA Central Academic Knowledge & Record Application\n";
        $sql .= "-- Full System Database Backup File\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . " (Asia/Jakarta)\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            $create_res = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
            if ($create_row = mysqli_fetch_row($create_res)) {
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create_row[1] . ";\n\n";
            }

            $data_res = mysqli_query($conn, "SELECT * FROM `$table`");
            $fields_count = mysqli_num_fields($data_res);

            while ($data_row = mysqli_fetch_row($data_res)) {
                $sql .= "INSERT INTO `$table` VALUES(";
                for ($i = 0; $i < $fields_count; $i++) {
                    if (isset($data_row[$i])) {
                        $escaped = mysqli_real_escape_string($conn, $data_row[$i]);
                        $sql .= "'" . $escaped . "'";
                    } else {
                        $sql .= "NULL";
                    }
                    if ($i < ($fields_count - 1)) {
                        $sql .= ",";
                    }
                }
                $sql .= ");\n";
            }
            $sql .= "\n-- --------------------------------------------------------\n\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        // 2. Instantiate ZIP Archive
        $temp_zip = tempnam(sys_get_temp_dir(), 'cakra_') . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($temp_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            throw new Exception("Gagal menginisialisasi berkas ZIP kompresi.");
        }

        // Add database sql dump inside ZIP
        $zip->addFromString('database_backup.sql', $sql);

        // Recursively add all media/uploaded files
        $uploads_dir = realpath(__DIR__ . '/../uploads/');
        if ($uploads_dir && is_dir($uploads_dir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($uploads_dir),
                RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                // Skip directories (they get added automatically as files are added)
                if (!$file->isDir()) {
                    $file_path = $file->getRealPath();
                    // Obtain the path relative to the app's root dir
                    $relative_path = 'uploads/' . ltrim(substr($file_path, strlen($uploads_dir)), '/\\');
                    $zip->addFile($file_path, $relative_path);
                }
            }
        }

        $zip->close();

        // Stream Full ZIP Download
        ob_clean();
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="cakra_full_backup_' . date('Ymd_His') . '.zip"');
        header('Content-Length: ' . filesize($temp_zip));
        header('Pragma: no-cache');
        header('Expires: 0');
        readfile($temp_zip);
        @unlink($temp_zip);
        exit();
    } catch (Exception $e) {
        ob_clean();
        $message = "Gagal Backup: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle Full System Restore (ZIP = Database SQL + Uploads Media folder)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    if (!empty($_FILES['backup_file']['name'])) {
        $file_ext = strtolower(pathinfo($_FILES["backup_file"]["name"], PATHINFO_EXTENSION));
        if ($file_ext === 'zip') {
            $zip_path = $_FILES["backup_file"]["tmp_name"];
            $zip = new ZipArchive();
            if ($zip->open($zip_path) === TRUE) {

                // 1. Restore Database SQL from ZIP
                $sql_content = $zip->getFromName('database_backup.sql');
                if ($sql_content !== FALSE) {
                    mysqli_begin_transaction($conn);
                    try {
                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

                        // Strip comments
                        $sql_clean = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
                        $lines = explode("\n", $sql_clean);
                        $processed_lines = [];
                        foreach ($lines as $line) {
                            $trimmed = trim($line);
                            if ($trimmed === '' || strpos($trimmed, '--') === 0 || strpos($trimmed, '#') === 0) {
                                continue;
                            }
                            $processed_lines[] = $line;
                        }
                        $sql_executable = implode("\n", $processed_lines);

                        // Split and execute statements
                        $queries = preg_split('/;[ \t\r]*\n/', $sql_executable);
                        foreach ($queries as $query) {
                            $query = trim($query);
                            if ($query === '') continue;
                            if (!mysqli_query($conn, $query)) {
                                throw new Exception(mysqli_error($conn) . " | Query: " . $query);
                            }
                        }

                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                        mysqli_commit($conn);
                    } catch (Exception $e) {
                        mysqli_rollback($conn);
                        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                        $zip->close();
                        $message = "Gagal memulihkan database: " . $e->getMessage();
                        $message_type = 'error';
                    }
                } else {
                    $zip->close();
                    $message = "Gagal: Berkas database_backup.sql tidak ditemukan di dalam arsip ZIP.";
                    $message_type = 'error';
                }

                if ($message_type !== 'error') {
                    try {
                        // 2. Unpack Uploaded Media Files
                        for ($i = 0; $i < $zip->numFiles; $i++) {
                            $entry_name = $zip->getNameIndex($i);
                            // Verify entry belongs to uploads folder and is not a plain directory
                            if (strpos($entry_name, 'uploads/') === 0 && substr($entry_name, -1) !== '/' && substr($entry_name, -1) !== '\\') {
                                // Prevent Zip Slip / Path Traversal (CWE-22)
                                if (strpos($entry_name, '..') !== false || strpos($entry_name, '\\..') !== false || strpos($entry_name, '/..') !== false) {
                                    throw new Exception("Deteksi ancaman keamanan: lintasan direktori tidak sah.");
                                }
                                $target_file = __DIR__ . '/../' . $entry_name;
                                $target_dir = dirname($target_file);
                                if (!is_dir($target_dir)) {
                                    mkdir($target_dir, 0777, true);
                                }
                                copy("zip://".$zip_path."#".$entry_name, $target_file);
                            }
                        }
                        $zip->close();
                        $message = "Database dan seluruh berkas media/uploads berhasil dipulihkan dengan sukses!";
                        $message_type = 'success';
                    } catch (Exception $ex) {
                        $zip->close();
                        $message = "Gagal memulihkan berkas media: " . $ex->getMessage();
                        $message_type = 'error';
                    }
                }
            } else {
                $message = "Gagal membuka berkas ZIP. File mungkin rusak.";
                $message_type = 'error';
            }
        } else {
            $message = "Hanya mendukung file arsip cadangan berformat .zip";
            $message_type = 'error';
        }
    } else {
        $message = "Silakan pilih file backup ZIP terlebih dahulu.";
        $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Backup & Restore Sistem</h1>
    <p class="text-slate-500">Cadangkan atau pulihkan seluruh database beserta berkas media (foto, berkas KK/Ijazah, perangkat guru) sistem CAKRA Anda.</p>
</div>

<?php if ($message): ?>
<script>
    Swal.fire({
        icon: '<?= $message_type ?>',
        title: '<?= $message_type === 'success' ? 'Berhasil!' : 'Gagal' ?>',
        text: '<?= addslashes(htmlspecialchars($message)) ?>',
        confirmButtonColor: '#4F46E5'
    });
</script>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 gap-8">
    <!-- Backup Card -->
    <div class="lux-card p-8 bg-white flex flex-col justify-between relative overflow-hidden">
        <div class="space-y-6">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm">
                    <i class="fa fa-archive"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-800 italic">Cadangkan Sistem (ZIP)</h3>
                    <p class="text-xs text-slate-400 mt-1 uppercase font-black tracking-wider">Ekspor Database & Berkas Media</p>
                </div>
            </div>
            <p class="text-sm text-slate-500 leading-relaxed">
                Fitur ini mengekspor database (struktur & data) serta **seluruh berkas media** (foto guru/siswa, scan berkas, perangkat mengajar, PDF jadwal pelajaran) ke dalam satu arsip ZIP kompresi terpadu.
            </p>
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-3">
                <i class="fa fa-info-circle text-indigo-500 text-sm mt-0.5"></i>
                <p class="text-xs text-slate-400 font-bold leading-relaxed italic">
                    File unduhan berformat ZIP. Simpan berkas ini dengan aman sebagai pemulihan total jika terjadi kegagalan server.
                </p>
            </div>
        </div>
        <div class="pt-8 border-t border-slate-50 mt-6">
            <a href="?action=backup" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-3 text-base">
                <i class="fa fa-download text-lg"></i> CADANGKAN SELURUH SISTEM
            </a>
        </div>
    </div>

    <!-- Restore Card -->
    <div class="lux-card p-8 bg-white flex flex-col justify-between relative overflow-hidden">
        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6 h-full flex flex-col justify-between">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <input type="hidden" name="restore" value="1">

            <div class="space-y-6">
                <div class="flex items-center gap-4">
                    <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm">
                        <i class="fa fa-history"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-800 italic">Pulihkan Sistem (ZIP)</h3>
                        <p class="text-xs text-slate-400 mt-1 uppercase font-black tracking-wider">Pemulihan Total Database & Media</p>
                    </div>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed">
                    Unggah berkas cadangan ZIP yang sebelumnya telah diunduh. Sistem akan memulihkan database secara keseluruhan sekaligus menyinkronkan kembali berkas-berkas media ke direktori server.
                </p>

                <div class="space-y-2">
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Berkas ZIP Backup</label>
                    <input type="file" name="backup_file" accept=".zip" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-rose-50 bg-white text-sm text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100 transition-all shadow-sm">
                </div>
            </div>

            <div class="pt-8 border-t border-slate-50 mt-6">
                <button type="submit" onclick="return confirmRestore(event)" class="w-full py-4 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-2xl shadow-xl shadow-rose-100 transition-all flex items-center justify-center gap-3 text-base">
                    <i class="fa fa-upload text-lg"></i> PULIHKAN SELURUH SISTEM
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function confirmRestore(event) {
    event.preventDefault();
    const form = event.target.form;
    Swal.fire({
        title: 'Apakah Anda Yakin?',
        text: "Memulihkan sistem dari ZIP cadangan akan menimpa seluruh database aktif dan folder berkas media. Tindakan ini tidak dapat dibatalkan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Pulihkan Sekarang!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sedang Memulihkan Sistem...',
                text: 'Harap jangan menutup halaman ini atau mematikan koneksi.',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            form.submit();
        }
    });
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
