<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);
$page_title = "Import Mata Pelajaran";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    if ($xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name'])) {
        $rows = $xlsx->rows(); array_shift($rows);
        $count = 0;
        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "INSERT IGNORE INTO mata_pelajaran (kode_mapel, nama_mapel) VALUES (?, ?)");
            foreach ($rows as $row) {
                if (empty($row[0])) continue;
                $nama = $row[0];

                // Function logic for code generation
                $chars = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
                do {
                    $kode = "";
                    for ($i = 0; $i < 5; $i++) $kode .= $chars[rand(0, strlen($chars) - 1)];
                    $check = mysqli_query($conn, "SELECT id FROM mata_pelajaran WHERE kode_mapel = '$kode'");
                } while (mysqli_num_rows($check) > 0);

                mysqli_stmt_bind_param($stmt, "ss", $kode, $nama);
                mysqli_stmt_execute($stmt);
                if (mysqli_stmt_affected_rows($stmt) > 0) $count++;
            }
            mysqli_commit($conn);
            $message = "Berhasil mengimpor $count mata pelajaran baru."; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Import Mata Pelajaran</h1>
    <p class="text-slate-500">Tambah daftar mata pelajaran secara massal dari Excel.</p>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-xl <?= $message_type == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' ?> border flex items-center">
    <i class="fa <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-xl"></i>
    <span class="font-bold"><?= $message ?></span>
</div>
<?php endif; ?>

<div class="lux-card p-8">
    <div class="mb-8 p-6 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-between">
        <div>
            <h3 class="text-indigo-800 font-bold mb-1 italic text-lg">Template Import</h3>
            <p class="text-indigo-700/70 text-sm italic">Kolom: Nama Mata Pelajaran</p>
        </div>
        <a href="<?= BASE_URL ?>api/download_template.php?type=mapel" class="inline-flex items-center px-5 py-2.5 bg-white text-indigo-600 font-bold rounded-xl border border-indigo-100 shadow-sm hover:shadow-md transition-all">
            <i class="fa fa-download mr-2"></i> Unduh Template
        </a>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div class="relative group">
            <input type="file" name="excel_file" id="excel_file" class="hidden" accept=".xlsx" required onchange="updateFileName(this)">
            <label for="excel_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50 transition-all cursor-pointer">
                <i class="fa fa-book text-4xl text-slate-400 group-hover:text-indigo-500 mb-4 transition-colors"></i>
                <p class="text-sm text-slate-500"><span class="font-bold">Pilih file Excel</span> mata pelajaran</p>
                <p id="fileName" class="mt-4 text-indigo-600 font-bold"></p>
            </label>
        </div>
        <div class="flex gap-4">
            <a href="mapel.php" class="flex-1 px-6 py-3.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-center">Kembali</a>
            <button type="submit" class="flex-[2] bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg transition-all">Import Mapel</button>
        </div>
    </form>
</div>

<script>function updateFileName(i){document.getElementById('fileName').textContent = i.files[0] ? i.files[0].name : '';}</script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
