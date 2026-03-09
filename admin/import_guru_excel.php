<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);

$page_title = "Import Guru dari Excel";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file']['tmp_name'];
    if ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows(); array_shift($rows);
        $success_count = 0;
        mysqli_begin_transaction($conn);
        try {
            $stmt_user = mysqli_prepare($conn, "INSERT INTO users (nama_lengkap, username, password, role) VALUES (?, ?, ?, 'guru')");
            $stmt_guru = mysqli_prepare($conn, "INSERT INTO guru (user_id, nip) VALUES (?, ?)");
            foreach ($rows as $row) {
                if (empty($row[0]) || empty($row[1])) continue;
                $pass = password_hash($row[1], PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt_user, "sss", $row[0], $row[1], $pass);
                mysqli_stmt_execute($stmt_user);
                $uid = mysqli_insert_id($conn);
                mysqli_stmt_bind_param($stmt_guru, "is", $uid, $row[1]);
                mysqli_stmt_execute($stmt_guru);
                $success_count++;
            }
            mysqli_commit($conn);
            $message = "Berhasil mengimpor $success_count guru."; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Import Guru</h1>
    <p class="text-slate-500">Unggah file Excel untuk menambah data guru secara massal.</p>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-xl <?= $message_type == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' ?> border flex items-center">
    <i class="fa <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-xl"></i>
    <span class="font-bold"><?= $message ?></span>
</div>
<?php endif; ?>

<div class="lux-card p-8">
    <div class="mb-8 p-6 rounded-2xl bg-indigo-50 border border-indigo-100">
        <h3 class="text-indigo-800 font-bold mb-2 flex items-center">
            <i class="fa fa-info-circle mr-2"></i> Petunjuk Format Excel
        </h3>
        <p class="text-indigo-700/80 text-sm leading-relaxed">Pastikan file Excel Anda memiliki kolom dengan urutan sebagai berikut pada sheet pertama:</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <?php foreach(['Nama Lengkap', 'NIP (Username)', 'Alamat', 'No. Telp'] as $col): ?>
                <span class="px-3 py-1.5 bg-white rounded-lg border border-indigo-200 text-indigo-600 text-xs font-bold uppercase shadow-sm"><?= $col ?></span>
            <?php endforeach; ?>
        </div>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
        <div class="relative group">
            <input type="file" name="excel_file" id="excel_file" class="hidden" accept=".xlsx" required onchange="updateFileName(this)">
            <label for="excel_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50 group-hover:border-indigo-300 transition-all cursor-pointer">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                    <i class="fa fa-cloud-upload-alt text-4xl text-slate-400 group-hover:text-indigo-500 mb-4 transition-colors"></i>
                    <p class="mb-2 text-sm text-slate-500 group-hover:text-indigo-600"><span class="font-bold">Klik untuk unggah</span> atau drag and drop</p>
                    <p class="text-xs text-slate-400 group-hover:text-indigo-500">Hanya file Excel (.xlsx)</p>
                    <p id="fileName" class="mt-4 text-indigo-600 font-bold"></p>
                </div>
            </label>
        </div>

        <div class="flex gap-4">
            <a href="guru.php" class="flex-1 px-6 py-3.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 text-center transition-all">Kembali</a>
            <button type="submit" class="flex-[2] bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-200 transition-all flex items-center justify-center">
                <i class="fa fa-upload mr-2"></i> Mulai Import Sekarang
            </button>
        </div>
    </form>
</div>

<script>
function updateFileName(input) {
    const fileName = input.files[0] ? input.files[0].name : '';
    document.getElementById('fileName').textContent = fileName;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
