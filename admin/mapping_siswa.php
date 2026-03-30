<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Mapping Siswa ke Kelas";
$message = ''; $message_type = '';

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_mapping'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");
    $kelas_id = (int)$_POST['kelas_id'];
    $siswa_ids = $_POST['siswa_ids'] ?? [];

    if (empty($siswa_ids)) {
        $message = "Peringatan: Tidak ada siswa yang dipilih."; $message_type = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            foreach ($siswa_ids as $sid) {
                $sid = (int)$sid;
                // Delete existing mapping for this year if any
                mysqli_query($conn, "DELETE FROM siswa_kelas WHERE siswa_id = $sid AND tahun_pelajaran_id = $active_tahun_id");
                // Insert new mapping
                mysqli_query($conn, "INSERT INTO siswa_kelas (siswa_id, kelas_id, tahun_pelajaran_id) VALUES ($sid, $kelas_id, $active_tahun_id)");
            }

            // Update counts for all classes
            $res_kelas = mysqli_query($conn, "SELECT id FROM kelas");
            while ($k = mysqli_fetch_assoc($res_kelas)) {
                $kid = $k['id'];
                $qL = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas sk JOIN siswa s ON sk.siswa_id = s.id WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $active_tahun_id AND s.jenis_kelamin = 'L'");
                $jL = mysqli_fetch_assoc($qL)['jml'];
                $qP = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas sk JOIN siswa s ON sk.siswa_id = s.id WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $active_tahun_id AND s.jenis_kelamin = 'P'");
                $jP = mysqli_fetch_assoc($qP)['jml'];
                mysqli_query($conn, "UPDATE kelas SET jumlah_siswa_L = $jL, jumlah_siswa_P = $jP WHERE id = $kid");
            }

            mysqli_commit($conn);
            $message = "Berhasil memetakan " . count($siswa_ids) . " siswa ke kelas terpilih."; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
    }
}

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
$siswas = mysqli_query($conn, "SELECT s.*, k.nama_kelas as kelas_aktif
                                FROM siswa s
                                LEFT JOIN siswa_kelas sk ON s.id = sk.siswa_id AND sk.tahun_pelajaran_id = $active_tahun_id
                                LEFT JOIN kelas k ON sk.kelas_id = k.id
                                ORDER BY s.nama_siswa ASC");

require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Mapping Siswa</h1>
    <p class="text-slate-500">Pilih siswa dan tentukan kelas tujuan mereka untuk tahun ini.</p>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
<?php endif; ?>

<form action="" method="POST" id="mappingForm">
    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Sidebar: Filter & Action -->
        <div class="space-y-6">
            <div class="lux-card p-6 bg-indigo-600 text-white shadow-indigo-200">
                <h3 class="text-lg font-bold mb-4 flex items-center italic">
                    <i class="fa fa-school mr-2"></i> Kelas Tujuan
                </h3>
                <select name="kelas_id" required class="w-full px-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white outline-none focus:bg-white focus:text-slate-800 transition-all font-bold">
                    <option value="" class="text-slate-800">-- Pilih Kelas --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>" class="text-slate-800"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="mt-8">
                    <button type="submit" name="proses_mapping" class="w-full bg-white text-indigo-600 font-black py-4 rounded-2xl shadow-xl hover:scale-[1.02] active:scale-95 transition-all">
                        PROSES MAPPING
                    </button>
                </div>
            </div>

            <div class="lux-card p-6 bg-white">
                <h3 class="text-slate-800 font-bold mb-4 flex items-center">
                    <i class="fa fa-filter mr-2 text-indigo-500"></i> Cari Siswa
                </h3>
                <input type="text" id="siswaSearch" placeholder="Ketik nama atau NIS..." class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50">
                <div class="mt-4 flex items-center justify-between">
                    <button type="button" onclick="toggleAll(true)" class="text-[10px] font-black text-indigo-600 uppercase tracking-widest hover:text-indigo-800">Pilih Semua</button>
                    <button type="button" onclick="toggleAll(false)" class="text-[10px] font-black text-slate-400 uppercase tracking-widest hover:text-slate-600">Batal Pilih</button>
                </div>
            </div>
        </div>

        <!-- Main: Student List -->
        <div class="lg:col-span-2">
            <div class="lux-card overflow-hidden">
                <div class="max-h-[70vh] overflow-y-auto">
                    <table class="w-full text-left border-collapse" id="siswaTable">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100 sticky top-0 z-10">
                                <th class="px-6 py-4 w-12"></th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest">NIS & Nama</th>
                                <th class="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-widest text-center">Kelas Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php while($s = mysqli_fetch_assoc($siswas)): ?>
                            <tr class="siswa-row hover:bg-slate-50/50 transition-colors cursor-pointer" onclick="toggleRow(this)">
                                <td class="px-6 py-4">
                                    <input type="checkbox" name="siswa_ids[]" value="<?= $s['id'] ?>" class="siswa-checkbox w-5 h-5 rounded-lg border-slate-300 text-indigo-600 focus:ring-indigo-500 transition-all">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-700 text-sm"><?= htmlspecialchars($s['nama_siswa']) ?></div>
                                    <div class="text-[9px] text-slate-400 font-mono"><?= $s['nis'] ?> | <?= $s['jenis_kelamin'] ?></div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 rounded bg-slate-100 text-slate-500 text-[9px] font-black uppercase border border-slate-200">
                                        <?= htmlspecialchars($s['kelas_aktif'] ?? 'Belum Ada') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function toggleRow(tr) {
    const cb = tr.querySelector('.siswa-checkbox');
    cb.checked = !cb.checked;
    tr.classList.toggle('bg-indigo-50/50', cb.checked);
}
// Prevent click inside checkbox from double toggling
document.querySelectorAll('.siswa-checkbox').forEach(cb => {
    cb.onclick = (e) => e.stopPropagation();
});

function toggleAll(val) {
    document.querySelectorAll('.siswa-checkbox').forEach(cb => {
        cb.checked = val;
        cb.closest('tr').classList.toggle('bg-indigo-50/50', val);
    });
}

document.getElementById('siswaSearch').onkeyup = function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('.siswa-row').forEach(row => {
        const txt = row.textContent.toLowerCase();
        row.style.display = txt.includes(q) ? '' : 'none';
    });
};
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
