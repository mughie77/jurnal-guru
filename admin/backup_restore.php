<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Backup & Restore Data";
$message = '';
$message_type = '';

// Handle DB Backup Action
if (isset($_GET['action']) && $_GET['action'] === 'backup') {
    // Clean any accidental output/notices
    ob_start();

    try {
        $tables = [];
        $result = mysqli_query($conn, "SHOW TABLES");
        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }

        $sql = "-- CAKRA Central Academic Knowledge & Record Application\n";
        $sql .= "-- Database Backup File\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . " (Asia/Jakarta)\n";
        $sql .= "-- --------------------------------------------------------\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $table) {
            // Write table drop and create schema
            $create_res = mysqli_query($conn, "SHOW CREATE TABLE `$table`");
            if ($create_row = mysqli_fetch_row($create_res)) {
                $sql .= "DROP TABLE IF EXISTS `$table`;\n";
                $sql .= $create_row[1] . ";\n\n";
            }

            // Write insert queries
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

        // Download attachment stream
        ob_clean();
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="cakra_backup_' . date('Ymd_His') . '.sql"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        echo $sql;
        exit();
    } catch (Exception $e) {
        ob_clean();
        $message = "Gagal Backup: " . $e->getMessage();
        $message_type = 'error';
    }
}

// Handle DB Restore Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        die("CSRF Token Invalid");
    }

    if (!empty($_FILES['backup_file']['name'])) {
        $file_ext = strtolower(pathinfo($_FILES["backup_file"]["name"], PATHINFO_EXTENSION));
        if ($file_ext === 'sql') {
            $sql_content = file_get_contents($_FILES["backup_file"]["tmp_name"]);

            mysqli_begin_transaction($conn);
            try {
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=0");

                // Remove multiline comments, single line comments starting with -- or # or /*
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

                // Split queries securely using standard statement delimiters at the end of a line
                $queries = preg_split('/;[ \t\r]*\n/', $sql_executable);

                foreach ($queries as $query) {
                    $query = trim($query);
                    if ($query === '') {
                        continue;
                    }
                    if (!mysqli_query($conn, $query)) {
                        throw new Exception(mysqli_error($conn) . " | Query: " . $query);
                    }
                }

                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                mysqli_commit($conn);

                $message = "Database berhasil dipulihkan dari file backup!";
                $message_type = 'success';
            } catch (Exception $e) {
                mysqli_rollback($conn);
                mysqli_query($conn, "SET FOREIGN_KEY_CHECKS=1");
                $message = "Gagal memulihkan database: " . $e->getMessage();
                $message_type = 'error';
            }
        } else {
            $message = "Hanya mendukung file cadangan berformat .sql";
            $message_type = 'error';
        }
    } else {
        $message = "Silakan pilih file backup .sql terlebih dahulu.";
        $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Backup & Restore Data</h1>
    <p class="text-slate-500">Cadangkan atau pulihkan seluruh database sistem CAKRA Anda.</p>
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
                    <i class="fa fa-cloud-download-alt"></i>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-800 italic">Cadangkan Data (Backup)</h3>
                    <p class="text-xs text-slate-400 mt-1 uppercase font-black tracking-wider">Sistem Ekspor SQL Otomatis</p>
                </div>
            </div>
            <p class="text-sm text-slate-500 leading-relaxed">
                Fitur ini akan mengekspor seluruh struktur tabel (skema) beserta record data ke dalam satu berkas SQL terpadu. Simpan file hasil unduhan di komputer Anda sebagai cadangan aman.
            </p>
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-100 flex items-start gap-3">
                <i class="fa fa-info-circle text-indigo-500 text-sm mt-0.5"></i>
                <p class="text-xs text-slate-400 font-bold leading-relaxed italic">
                    Proses backup tidak mempengaruhi data aktif di sistem Anda.
                </p>
            </div>
        </div>
        <div class="pt-8 border-t border-slate-50 mt-6">
            <a href="?action=backup" class="w-full py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center gap-3 text-base">
                <i class="fa fa-download text-lg"></i> CADANGKAN DATABASE
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
                        <i class="fa fa-cloud-upload-alt"></i>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-slate-800 italic">Pulihkan Data (Restore)</h3>
                        <p class="text-xs text-slate-400 mt-1 uppercase font-black tracking-wider">Sistem Impor SQL Cadangan</p>
                    </div>
                </div>
                <p class="text-sm text-slate-500 leading-relaxed">
                    Fitur ini akan mengunggah berkas cadangan .sql Anda dan menimpa database aktif. Data saat ini akan diganti seluruhnya dengan data yang ada di file cadangan.
                </p>

                <div class="space-y-2">
                    <label class="block text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Berkas SQL Backup</label>
                    <input type="file" name="backup_file" accept=".sql" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-rose-50 bg-white text-sm text-slate-500 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-rose-50 file:text-rose-600 hover:file:bg-rose-100 transition-all shadow-sm">
                </div>
            </div>

            <div class="pt-8 border-t border-slate-50 mt-6">
                <button type="submit" onclick="return confirmRestore(event)" class="w-full py-4 bg-rose-600 hover:bg-rose-700 text-white font-black rounded-2xl shadow-xl shadow-rose-100 transition-all flex items-center justify-center gap-3 text-base">
                    <i class="fa fa-upload text-lg"></i> PULIHKAN DATABASE
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
        text: "Memulihkan database akan menghapus seluruh data saat ini dan menggantinya dengan data cadangan. Tindakan ini tidak dapat dibatalkan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Pulihkan Sekarang!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Sedang Memulihkan Database...',
                text: 'Harap jangan menutup halaman ini.',
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
