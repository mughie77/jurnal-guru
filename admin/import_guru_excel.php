<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);

$page_title = "Import Guru dari Excel";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $file = $_FILES['excel_file']['tmp_name'];
    if ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows();
        array_shift($rows); // Skip header

        $success_count = 0;
        $skip_count = 0;
        $error_rows = [];

        mysqli_begin_transaction($conn);
        try {
            $stmt_user = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role)
                                              VALUES (?, ?, ?, 'guru')
                                              ON DUPLICATE KEY UPDATE nama_lengkap = VALUES(nama_lengkap), id=LAST_INSERT_ID(id)");

            $stmt_guru = mysqli_prepare($conn, "INSERT INTO guru (user_id, nip, alamat, no_telp)
                                              VALUES (?, ?, ?, ?)
                                              ON DUPLICATE KEY UPDATE alamat = VALUES(alamat), no_telp = VALUES(no_telp)");

            foreach ($rows as $index => $row) {
                if (empty($row[0]) || empty($row[1])) {
                    $skip_count++;
                    continue;
                }

                $nama_lengkap = $row[0];
                $nip = (string)$row[1];
                $pass = password_hash($nip, PASSWORD_DEFAULT);
                $alamat = $row[2] ?? null;
                $no_telp = $row[3] ?? null;

                // Insert/Update User
                mysqli_stmt_bind_param($stmt_user, "sss", $nama_lengkap, $nip, $pass);
                if (!mysqli_stmt_execute($stmt_user)) {
                    throw new Exception("Gagal memproses user pada baris " . ($index + 2));
                }
                $uid = mysqli_insert_id($conn);

                // Insert/Update Guru
                mysqli_stmt_bind_param($stmt_guru, "isss", $uid, $nip, $alamat, $no_telp);
                if (!mysqli_stmt_execute($stmt_guru)) {
                    throw new Exception("Gagal memproses guru pada baris " . ($index + 2));
                }

                $success_count++;
            }
            mysqli_commit($conn);
            $message = "Berhasil memproses $success_count data guru." . ($skip_count > 0 ? " ($skip_count baris kosong dilewati)" : "");
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Gagal membaca file Excel: " . SimpleXLSX::parseError();
        $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Import Guru</h1>
    <p class="text-slate-500">Unggah file Excel untuk menambah atau memperbarui data guru secara massal.</p>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-xl <?= $message_type == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' ?> border flex items-center animate-in fade-in slide-in-from-top-4 duration-500">
    <i class="fa <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-xl"></i>
    <span class="font-bold text-sm"><?= $message ?></span>
</div>
<?php endif; ?>

<div class="lux-card p-8">
    <div class="mb-8 p-6 rounded-2xl bg-indigo-50 border border-indigo-100">
        <h3 class="text-indigo-800 font-bold mb-2 flex items-center">
            <i class="fa fa-info-circle mr-2"></i> Petunjuk Format Excel
        </h3>
        <p class="text-indigo-700/80 text-sm leading-relaxed mb-4">Pastikan file Excel Anda memiliki kolom dengan urutan sebagai berikut pada sheet pertama:</p>
        <div class="flex flex-wrap gap-2 items-center mb-6">
            <?php foreach(['Nama Lengkap', 'NIP', 'Alamat', 'No. Telp'] as $col): ?>
                <span class="px-3 py-1.5 bg-white rounded-lg border border-indigo-200 text-indigo-600 text-[10px] font-black uppercase shadow-sm tracking-wider"><?= $col ?></span>
            <?php endforeach; ?>
        </div>
        <div class="flex items-center justify-between border-t border-indigo-100 pt-4">
            <p class="text-[10px] text-indigo-400 font-bold italic uppercase">Gunakan NIP sebagai Username & Password Default</p>
            <a href="<?= BASE_URL ?>api/download_template.php?type=guru" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-bold text-sm bg-white px-4 py-2 rounded-xl border border-indigo-200 shadow-sm transition-all hover:shadow-md">
                <i class="fa fa-download mr-2"></i> Unduh Template
            </a>
        </div>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div class="relative group">
            <input type="file" name="excel_file" id="excel_file" class="hidden" accept=".xlsx" required onchange="updateFileName(this)">
            <label for="excel_file" class="flex flex-col items-center justify-center w-full h-56 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50 group-hover:border-indigo-300 transition-all cursor-pointer overflow-hidden relative">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                    <div class="w-16 h-16 bg-white rounded-2xl shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <i class="fa fa-file-excel text-3xl text-emerald-500"></i>
                    </div>
                    <p class="mb-1 text-sm text-slate-600 font-medium"><span class="font-bold text-indigo-600 underline">Klik untuk unggah</span> atau seret file ke sini</p>
                    <p class="text-xs text-slate-400 italic">Hanya mendukung format .xlsx</p>
                    <div id="fileInfo" class="mt-4 hidden animate-in zoom-in duration-300">
                        <span id="fileName" class="px-3 py-1 bg-indigo-600 text-white rounded-full text-xs font-bold shadow-lg shadow-indigo-100"></span>
                    </div>
                </div>
            </label>
        </div>

        <div class="flex gap-4">
            <a href="guru.php" class="flex-1 px-6 py-4 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 text-center transition-all flex items-center justify-center">
                <i class="fa fa-arrow-left mr-2"></i> Kembali
            </a>
            <button type="submit" class="flex-[2] bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center active:scale-[0.98]">
                <i class="fa fa-upload mr-2"></i> Import Data Sekarang
            </button>
        </div>
    </form>
</div>

<script>
function updateFileName(input) {
    const fileInfo = document.getElementById('fileInfo');
    const fileName = document.getElementById('fileName');
    if (input.files && input.files[0]) {
        fileName.textContent = input.files[0].name;
        fileInfo.classList.remove('hidden');
    } else {
        fileInfo.classList.add('hidden');
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
