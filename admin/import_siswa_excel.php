<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin']);

$page_title = "Import Siswa dari Excel";
$message = ''; $message_type = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $file = $_FILES['excel_file']['tmp_name'];
    $kelas_id = (int)$_POST['kelas_id'];

    if (!$active_tahun_id) {
        $message = "Tidak ada tahun pelajaran aktif yang ditemukan.";
        $message_type = 'error';
    } elseif ($xlsx = SimpleXLSX::parse($file)) {
        $rows = $xlsx->rows();
        array_shift($rows); // Skip header

        $success_count = 0;
        $skip_count = 0;

        mysqli_begin_transaction($conn);
        try {
            // Prepared statements
            $stmt_siswa = mysqli_prepare($conn, "INSERT INTO siswa (nis, nisn, nama_siswa, jenis_kelamin, alamat, no_telp, tempat_lahir, tanggal_lahir)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                               ON DUPLICATE KEY UPDATE nisn = VALUES(nisn), nama_siswa = VALUES(nama_siswa), jenis_kelamin = VALUES(jenis_kelamin), alamat = VALUES(alamat), no_telp = VALUES(no_telp), tempat_lahir = VALUES(tempat_lahir), tanggal_lahir = VALUES(tanggal_lahir), id=LAST_INSERT_ID(id)");

            $stmt_link = mysqli_prepare($conn, "INSERT INTO siswa_kelas (siswa_id, kelas_id, tahun_pelajaran_id)
                                              VALUES (?, ?, ?)
                                              ON DUPLICATE KEY UPDATE kelas_id = VALUES(kelas_id)");

            foreach ($rows as $index => $row) {
                if (empty($row[0]) || empty($row[1])) {
                    $skip_count++;
                    continue;
                }

                $nis = (string)$row[0];
                $nisn = (string)($row[1] ?? '');
                $nama_siswa = $row[2] ?? '';
                $jk = (strtoupper(trim($row[3] ?? '')) == 'P') ? 'P' : 'L';
                $alamat = $row[4] ?? null;
                $no_telp = $row[5] ?? null;
                $tempat_lahir = $row[6] ?? null;
                $tanggal_lahir = $row[7] ?? null;

                // Insert/Update Siswa
                mysqli_stmt_bind_param($stmt_siswa, "ssssssss", $nis, $nisn, $nama_siswa, $jk, $alamat, $no_telp, $tempat_lahir, $tanggal_lahir);
                if (!mysqli_stmt_execute($stmt_siswa)) {
                    throw new Exception("Gagal memproses siswa pada baris " . ($index + 2));
                }
                $sid = mysqli_insert_id($conn);

                // Link to Class for Active Year
                mysqli_stmt_bind_param($stmt_link, "iii", $sid, $kelas_id, $active_tahun_id);
                if (!mysqli_stmt_execute($stmt_link)) {
                    throw new Exception("Gagal menautkan siswa ke kelas pada baris " . ($index + 2));
                }

                $success_count++;
            }
            mysqli_commit($conn);
            $message = "Berhasil memproses $success_count data siswa.";
            if ($skip_count > 0) $message .= " ($skip_count baris kosong dilewati)";
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

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas ASC");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Import Siswa</h1>
    <p class="text-slate-500">Unggah data siswa ke dalam kelas terpilih untuk tahun pelajaran aktif.</p>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-xl <?= $message_type == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' ?> border flex items-center animate-in fade-in slide-in-from-top-4 duration-500">
    <i class="fa <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-xl"></i>
    <span class="font-bold text-sm"><?= $message ?></span>
</div>
<?php endif; ?>

<div class="lux-card p-8">
    <form action="" method="POST" enctype="multipart/form-data" class="space-y-8">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-4">
                <label class="block text-sm font-black text-slate-400 uppercase tracking-widest ml-1">1. Pilih Kelas Tujuan</label>
                <select name="kelas_id" required class="w-full px-5 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white shadow-sm font-bold text-slate-700">
                    <option value="">-- Pilih Kelas --</option>
                    <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <p class="text-[10px] text-slate-400 font-medium italic">* Siswa yang sudah ada akan diperbarui datanya dan dipindahkan ke kelas ini.</p>
            </div>

            <div class="p-6 rounded-2xl bg-amber-50 border border-amber-100 self-start">
                <h3 class="text-amber-800 font-bold mb-2 flex items-center text-sm">
                    <i class="fa fa-info-circle mr-2"></i> Format File Excel
                </h3>
                <div class="flex flex-wrap gap-1.5 mb-3">
                    <?php foreach(['NIS', 'NISN', 'Nama Siswa', 'L/P', 'Alamat', 'No. Telp', 'Tempat Lahir', 'Tgl Lahir'] as $col): ?>
                        <span class="px-2 py-1 bg-white rounded-lg border border-amber-200 text-amber-600 text-[10px] font-black uppercase"><?= $col ?></span>
                    <?php endforeach; ?>
                </div>
                <a href="<?= BASE_URL ?>api/download_template.php?type=siswa" class="text-amber-700 hover:text-amber-900 font-bold text-xs underline inline-flex items-center">
                    <i class="fa fa-download mr-1"></i> Unduh Template Siswa
                </a>
            </div>
        </div>

        <div class="space-y-4">
            <label class="block text-sm font-black text-slate-400 uppercase tracking-widest ml-1">2. Unggah File</label>
            <div class="relative group">
                <input type="file" name="excel_file" id="excel_file" class="hidden" accept=".xlsx" required onchange="updateFileName(this)">
                <label for="excel_file" class="flex flex-col items-center justify-center w-full h-56 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50 group-hover:border-indigo-300 transition-all cursor-pointer overflow-hidden relative">
                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                        <div class="w-16 h-16 bg-white rounded-2xl shadow-sm flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                            <i class="fa fa-file-excel text-3xl text-emerald-500"></i>
                        </div>
                        <p class="mb-1 text-sm text-slate-600 font-medium"><span class="font-bold text-indigo-600 underline">Klik untuk memilih file</span></p>
                        <p class="text-xs text-slate-400 italic">Pastikan format kolom sesuai petunjuk</p>
                        <div id="fileInfo" class="mt-4 hidden animate-in zoom-in duration-300">
                            <span id="fileName" class="px-3 py-1 bg-indigo-600 text-white rounded-full text-xs font-bold shadow-lg shadow-indigo-100"></span>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <div class="flex gap-4 pt-4">
            <a href="siswa.php" class="flex-1 px-6 py-4 rounded-xl border border-slate-200 text-slate-600 font-bold hover:bg-slate-50 text-center transition-all flex items-center justify-center">
                <i class="fa fa-arrow-left mr-2"></i> Kembali
            </a>
            <button type="submit" class="flex-[2] bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-xl shadow-xl shadow-indigo-100 transition-all flex items-center justify-center active:scale-[0.98]">
                <i class="fa fa-upload mr-2"></i> Mulai Proses Import
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
