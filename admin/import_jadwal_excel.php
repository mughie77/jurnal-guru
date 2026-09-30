<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX;

authorize_role(['admin', 'waka']);
$page_title = "Import Jadwal Pelajaran";
$message = ''; $message_type = '';
$skipped_logs = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['excel_file'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    if ($xlsx = SimpleXLSX::parse($_FILES['excel_file']['tmp_name'])) {
        $rows = $xlsx->rows();
        array_shift($rows); // Remove header row

        // Pre-fetch lookups
        $kelas_map = [];
        $res_k = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas");
        while ($r = mysqli_fetch_assoc($res_k)) {
            $kelas_map[strtolower(trim($r['nama_kelas']))] = (int)$r['id'];
        }

        $guru_map = [];
        $res_g = mysqli_query($conn, "SELECT g.id, g.nip, u.nama_lengkap FROM guru g JOIN users u ON g.user_id = u.id");
        while ($r = mysqli_fetch_assoc($res_g)) {
            if (!empty($r['nip'])) {
                $guru_map[strtolower(trim($r['nip']))] = (int)$r['id'];
            }
            if (!empty($r['nama_lengkap'])) {
                $guru_map[strtolower(trim($r['nama_lengkap']))] = (int)$r['id'];
            }
        }

        $mapel_map = [];
        $res_m = mysqli_query($conn, "SELECT id, nama_mapel, kode_mapel FROM mata_pelajaran");
        while ($r = mysqli_fetch_assoc($res_m)) {
            if (!empty($r['kode_mapel'])) {
                $mapel_map[strtolower(trim($r['kode_mapel']))] = (int)$r['id'];
            }
            if (!empty($r['nama_mapel'])) {
                $mapel_map[strtolower(trim($r['nama_mapel']))] = (int)$r['id'];
            }
        }

        $valid_days = [
            'senin' => 'Senin',
            'selasa' => 'Selasa',
            'rabu' => 'Rabu',
            'kamis' => 'Kamis',
            'jumat' => 'Jumat',
            'sabtu' => 'Sabtu',
            'minggu' => 'Minggu'
        ];

        $count = 0;
        $row_idx = 1;

        mysqli_begin_transaction($conn);
        try {
            $stmt = mysqli_prepare($conn, "INSERT INTO jadwal_pelajaran (kelas_id, hari, guru_id, mapel_id, jam_ke) VALUES (?, ?, ?, ?, ?)");

            foreach ($rows as $row) {
                $row_idx++;
                $nama_kelas_raw = trim($row[0] ?? '');
                $hari_raw = trim($row[1] ?? '');
                $guru_raw = trim($row[2] ?? '');
                $mapel_raw = trim($row[3] ?? '');
                $jam_ke_raw = trim($row[4] ?? '');

                if (empty($nama_kelas_raw) && empty($hari_raw) && empty($guru_raw)) continue;

                // Match Kelas
                $kelas_key = strtolower($nama_kelas_raw);
                if (!isset($kelas_map[$kelas_key])) {
                    $skipped_logs[] = "Baris #$row_idx: Kelas '$nama_kelas_raw' tidak ditemukan.";
                    continue;
                }
                $kelas_id = $kelas_map[$kelas_key];

                // Match Hari
                $hari_key = strtolower($hari_raw);
                if (!isset($valid_days[$hari_key])) {
                    $skipped_logs[] = "Baris #$row_idx: Hari '$hari_raw' tidak valid (Gunakan: Senin-Sabtu).";
                    continue;
                }
                $hari_val = $valid_days[$hari_key];

                // Match Guru
                $guru_key = strtolower($guru_raw);
                if (!isset($guru_map[$guru_key])) {
                    $skipped_logs[] = "Baris #$row_idx: Guru '$guru_raw' tidak ditemukan di database.";
                    continue;
                }
                $guru_id = $guru_map[$guru_key];

                // Match Mapel
                $mapel_key = strtolower($mapel_raw);
                if (!isset($mapel_map[$mapel_key])) {
                    $skipped_logs[] = "Baris #$row_idx: Mapel '$mapel_raw' tidak ditemukan di database.";
                    continue;
                }
                $mapel_id = $mapel_map[$mapel_key];

                if (empty($jam_ke_raw)) {
                    $skipped_logs[] = "Baris #$row_idx: Jam ke- wajib diisi.";
                    continue;
                }

                mysqli_stmt_bind_param($stmt, "isiis", $kelas_id, $hari_val, $guru_id, $mapel_id, $jam_ke_raw);
                if (mysqli_stmt_execute($stmt)) {
                    $count++;
                }
            }

            mysqli_commit($conn);
            $message = "Berhasil mengimpor $count entri jadwal pelajaran.";
            $message_type = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "Error: " . $e->getMessage();
            $message_type = 'error';
        }
    } else {
        $message = "Gagal membaca file Excel. Pastikan format file .xlsx valid.";
        $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Import Jadwal Pelajaran</h1>
    <p class="text-slate-500">Tambah entri jadwal pelajaran massal dari file Excel per kelas.</p>
</div>

<?php if ($message): ?>
<div class="mb-6 p-4 rounded-2xl <?= $message_type == 'success' ? 'bg-emerald-50 text-emerald-700 border-emerald-100' : 'bg-rose-50 text-rose-700 border-rose-100' ?> border flex items-center">
    <i class="fa <?= $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-3 text-xl"></i>
    <span class="font-bold text-sm"><?= $message ?></span>
</div>
<?php endif; ?>

<?php if (!empty($skipped_logs)): ?>
<div class="mb-6 p-5 rounded-2xl bg-amber-50 text-amber-900 border border-amber-200">
    <h4 class="font-black text-xs uppercase tracking-widest text-amber-800 mb-2 flex items-center gap-2">
        <i class="fa fa-triangle-exclamation"></i> Peringatan Baris Dilewati (<?= count($skipped_logs) ?>)
    </h4>
    <ul class="list-disc list-inside text-xs font-semibold space-y-1 max-h-40 overflow-y-auto pr-1">
        <?php foreach ($skipped_logs as $log): ?>
            <li><?= htmlspecialchars($log) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="lux-card p-8">
    <div class="mb-8 p-6 rounded-2xl bg-indigo-50 border border-indigo-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div>
            <h3 class="text-indigo-900 font-bold mb-1 italic text-lg">Template Import Jadwal</h3>
            <p class="text-indigo-700/80 text-xs">Kolom: Nama Kelas, Hari, NIP / Nama Guru, Kode / Nama Mapel, Jam Ke</p>
        </div>
        <a href="<?= BASE_URL ?>api/download_template.php?type=jadwal" class="inline-flex items-center px-5 py-2.5 bg-white text-indigo-600 font-bold text-xs rounded-xl border border-indigo-100 shadow-sm hover:shadow-md transition-all shrink-0">
            <i class="fa fa-download mr-2"></i> Unduh Template Excel
        </a>
    </div>

    <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
        <div class="relative group">
            <input type="file" name="excel_file" id="excel_file" class="hidden" accept=".xlsx" required onchange="updateFileName(this)">
            <label for="excel_file" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-slate-300 rounded-3xl bg-slate-50 group-hover:bg-indigo-50/50 transition-all cursor-pointer">
                <i class="fa fa-calendar-alt text-4xl text-slate-400 group-hover:text-indigo-500 mb-4 transition-colors"></i>
                <p class="text-xs text-slate-500"><span class="font-bold text-slate-700">Pilih file Excel</span> jadwal pelajaran (.xlsx)</p>
                <p id="fileName" class="mt-3 text-indigo-600 font-bold text-xs"></p>
            </label>
        </div>
        <div class="flex gap-4">
            <a href="jadwal.php" class="flex-1 px-6 py-3.5 rounded-xl border border-slate-200 text-slate-600 font-bold text-center text-xs">Kembali</a>
            <button type="submit" class="flex-[2] bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3.5 rounded-xl shadow-lg shadow-indigo-100 transition-all text-xs">Import Jadwal</button>
        </div>
    </form>
</div>

<script>
function updateFileName(i) {
    document.getElementById('fileName').textContent = i.files[0] ? i.files[0].name : '';
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
