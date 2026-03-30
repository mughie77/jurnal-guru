<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);

$page_title = "Import Foto Massal (.zip)";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['zip_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $type = $_POST['type'] ?? 'siswa'; // 'siswa' or 'guru'
    $file = $_FILES['zip_file']['tmp_name'];

    $zip = new ZipArchive;
    if ($zip->open($file) === TRUE) {
        $target_dir = __DIR__ . "/../uploads/$type/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

        $success_count = 0;
        $error_count = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $fileinfo = pathinfo($filename);

            // Only process jpeg/jpg files
            if (isset($fileinfo['extension']) && in_array(strtolower($fileinfo['extension']), ['jpg', 'jpeg'])) {
                $nis_nip = $fileinfo['filename']; // Filename without extension is the NIS or NIP
                $new_filename = $nis_nip . "_" . time() . ".jpg";

                // Extract file content
                $content = $zip->getFromIndex($i);
                file_put_contents($target_dir . $new_filename, $content);

                // Update database
                if ($type == 'siswa') {
                    $stmt = mysqli_prepare($conn, "UPDATE siswa SET foto = ? WHERE nis = ?");
                } else {
                    $stmt = mysqli_prepare($conn, "UPDATE guru SET foto = ? WHERE nip = ?");
                }

                mysqli_stmt_bind_param($stmt, "ss", $new_filename, $nis_nip);
                if (mysqli_stmt_execute($stmt) && mysqli_stmt_affected_rows($stmt) > 0) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        $zip->close();

        $message = "Berhasil memproses foto. Sukses: $success_count, Gagal (NIS/NIP tidak cocok): $error_count.";
        $message_type = $success_count > 0 ? 'success' : 'error';
    } else {
        $message = "Gagal membuka file ZIP.";
        $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Import Foto Massal</h1>
    <p class="text-slate-500">Unggah file ZIP berisi foto siswa atau guru (format .jpg, nama file = NIS/NIP).</p>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
<?php endif; ?>

<div class="lux-card p-10 max-w-2xl">
    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div class="space-y-4">
            <label class="block text-xs font-black text-slate-400 uppercase tracking-widest ml-1">Jenis Data</label>
            <div class="grid grid-cols-2 gap-4">
                <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 bg-slate-50 cursor-pointer hover:border-indigo-200 transition-all group">
                    <input type="radio" name="type" value="siswa" checked class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                    <span class="ml-3 font-bold text-slate-700">Foto Siswa</span>
                </label>
                <label class="relative flex items-center p-4 rounded-2xl border-2 border-slate-100 bg-slate-50 cursor-pointer hover:border-indigo-200 transition-all group">
                    <input type="radio" name="type" value="guru" class="w-4 h-4 text-indigo-600 border-slate-300 focus:ring-indigo-500">
                    <span class="ml-3 font-bold text-slate-700">Foto Guru</span>
                </label>
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-indigo-50 border border-indigo-100">
            <h3 class="text-indigo-800 font-bold mb-2 flex items-center text-sm">
                <i class="fa fa-info-circle mr-2"></i> Ketentuan Format
            </h3>
            <ul class="text-xs text-indigo-700/80 space-y-1 leading-relaxed">
                <li>• File harus dalam format <strong>.zip</strong></li>
                <li>• Isi ZIP adalah file gambar <strong>.jpg</strong> atau <strong>.jpeg</strong></li>
                <li>• Nama file harus <strong>NIS</strong> (untuk siswa) atau <strong>NIP</strong> (untuk guru)</li>
                <li>• Contoh: <code>12345.jpg</code> atau <code>19800101...jpg</code></li>
            </ul>
        </div>

        <div class="relative group">
            <input type="file" name="zip_file" id="zip_file" class="hidden" accept=".zip" required onchange="document.getElementById('fileName').textContent = this.files[0].name; document.getElementById('fileInfo').classList.remove('hidden')">
            <label for="zip_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50 group-hover:border-indigo-300 transition-all cursor-pointer">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                    <i class="fa fa-file-archive text-4xl text-indigo-400 mb-4"></i>
                    <p class="text-sm text-slate-600 font-medium italic">Klik untuk pilih file ZIP</p>
                    <div id="fileInfo" class="mt-3 hidden">
                        <span id="fileName" class="px-3 py-1 bg-indigo-600 text-white rounded-full text-[10px] font-black uppercase"></span>
                    </div>
                </div>
            </label>
        </div>

        <button type="submit" class="w-full bg-slate-900 hover:bg-indigo-600 text-white font-black py-4 rounded-2xl shadow-xl transition-all flex items-center justify-center gap-3">
            <i class="fa fa-upload"></i> Mulai Extract & Import
        </button>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
