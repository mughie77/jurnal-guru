<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);
$page_title = "Import Siswa";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $file = $_FILES['excel_file']['tmp_name'];
    $kelas_id = (int)$_POST['kelas_id'];
    if ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows(); array_shift($rows);
        $success_count = 0;
        mysqli_begin_transaction($conn);
        try {
            $stmt_siswa = mysqli_prepare($conn, "INSERT INTO siswa (nis, nama_siswa, jenis_kelamin) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE nama_siswa = VALUES(nama_siswa), jenis_kelamin = VALUES(jenis_kelamin)");
            $stmt_link = mysqli_prepare($conn, "INSERT INTO siswa_kelas (siswa_id, kelas_id, tahun_pelajaran_id) VALUES (?, ?, ?)");
            foreach ($rows as $row) {
                if (empty($row[0]) || empty($row[1])) continue;
                $jk = strtoupper($row[2]) == 'P' ? 'P' : 'L';
                mysqli_stmt_bind_param($stmt_siswa, "sss", $row[0], $row[1], $jk);
                mysqli_stmt_execute($stmt_siswa);
                $res_id = mysqli_query($conn, "SELECT id FROM siswa WHERE nis = '".mysqli_real_escape_string($conn, $row[0])."'");
                $sid = mysqli_fetch_assoc($res_id)['id'];
                $check_link = mysqli_query($conn, "SELECT id FROM siswa_kelas WHERE siswa_id = $sid AND tahun_pelajaran_id = $active_tahun_id");
                if (mysqli_num_rows($check_link) == 0) {
                    mysqli_stmt_bind_param($stmt_link, "iii", $sid, $kelas_id, $active_tahun_id);
                    mysqli_stmt_execute($stmt_link);
                }
                $success_count++;
            }
            mysqli_commit($conn); $message = "Berhasil mengimpor $success_count siswa."; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
    }
}

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Import Siswa</h1>
    <p class="text-slate-500">Unggah file Excel untuk menambah siswa ke kelas terpilih.</p>
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
            <h3 class="text-indigo-800 font-bold mb-1">Format Excel</h3>
            <p class="text-indigo-700/70 text-xs">Kolom: NIS, Nama Siswa, L/P</p>
        </div>
        <a href="<?= BASE_URL ?>api/download_template.php?type=siswa" class="inline-flex items-center text-indigo-600 hover:text-indigo-800 font-bold text-sm bg-white px-4 py-2 rounded-xl border border-indigo-100 shadow-sm transition-all">
            <i class="fa fa-download mr-2"></i> Unduh Template
        </a>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div>
            <label class="block text-sm font-bold text-slate-700 mb-2">Pilih Kelas Tujuan</label>
            <select name="kelas_id" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white shadow-sm">
                <option value="">-- Pilih Kelas --</option>
                <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="relative group">
            <input type="file" name="excel_file" id="excel_file" class="hidden" accept=".xlsx" required onchange="updateFileName(this)">
            <label for="excel_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50 group-hover:border-indigo-300 transition-all cursor-pointer">
                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                    <i class="fa fa-cloud-upload-alt text-4xl text-slate-400 group-hover:text-indigo-500 mb-4"></i>
                    <p class="mb-2 text-sm text-slate-500"><span class="font-bold">Klik untuk unggah</span> file Excel siswa</p>
                    <p class="text-xs text-slate-400">Format: NIS, Nama Siswa, L/P</p>
                    <p id="fileName" class="mt-4 text-indigo-600 font-bold"></p>
                </div>
            </label>
        </div>

        <div class="flex gap-4">
            <a href="siswa.php" class="flex-1 px-6 py-3.5 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 text-center transition-all">Kembali</a>
            <button type="submit" class="flex-[2] bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-200 transition-all flex items-center justify-center">
                <i class="fa fa-upload mr-2"></i> Mulai Import
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
