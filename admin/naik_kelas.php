<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Kenaikan Kelas";
$message = ''; $message_type = '';

if (!$active_tahun_id) {
    die("Error: Tidak ada tahun pelajaran aktif. <a href='tahun_pelajaran.php'>Atur di sini</a>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['proses_naik'])) {
    $kelas_asal = (int)$_POST['kelas_asal'];
    $kelas_tujuan = (int)$_POST['kelas_tujuan'];

    if ($kelas_asal == $kelas_tujuan) {
        $message = "Gagal: Kelas asal dan tujuan tidak boleh sama."; $message_type = 'error';
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Ambil semua siswa dari kelas asal di tahun aktif (sebenarnya biasanya ini dilakukan saat pergantian tahun, tapi sesuai request "menggunakan data siswa yang ada")
            // Kita asumsikan "naik kelas" berarti memindahkan/mendaftarkan siswa ke kelas baru di tahun yang sama atau tahun berikutnya.
            // Request user: "naik kelas menggunakan data siswa yang ada dan dinaikkan ke kelas yang dituju"

            $query_siswa = mysqli_query($conn, "SELECT siswa_id FROM siswa_kelas WHERE kelas_id = $kelas_asal AND tahun_pelajaran_id = $active_tahun_id");
            $count = 0;
            while ($s = mysqli_fetch_assoc($query_siswa)) {
                $sid = $s['siswa_id'];
                // Update link kelas untuk siswa ini di tahun aktif
                mysqli_query($conn, "UPDATE siswa_kelas SET kelas_id = $kelas_tujuan WHERE siswa_id = $sid AND tahun_pelajaran_id = $active_tahun_id");
                $count++;
            }

            // Update Class Counts for both classes
            foreach([$kelas_asal, $kelas_tujuan] as $kid) {
                $qL = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas sk JOIN siswa s ON sk.siswa_id = s.id WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $active_tahun_id AND s.jenis_kelamin = 'L'");
                $jL = mysqli_fetch_assoc($qL)['jml'];
                $qP = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas sk JOIN siswa s ON sk.siswa_id = s.id WHERE sk.kelas_id = $kid AND sk.tahun_pelajaran_id = $active_tahun_id AND s.jenis_kelamin = 'P'");
                $jP = mysqli_fetch_assoc($qP)['jml'];
                mysqli_query($conn, "UPDATE kelas SET jumlah_siswa_L = $jL, jumlah_siswa_P = $jP WHERE id = $kid");
            }

            mysqli_commit($conn);
            $message = "Berhasil menaikkan $count siswa ke kelas tujuan."; $message_type = 'success';
        } catch (Exception $e) { mysqli_rollback($conn); $message = "Error: " . $e->getMessage(); $message_type = 'error'; }
    }
}

$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
require_once __DIR__ . '/../includes/header.php';
?>

<div class="mb-8">
    <h1 class="text-3xl font-bold text-slate-800 tracking-tight italic">Kenaikan Kelas</h1>
    <p class="text-slate-500">Pindahkan seluruh siswa dari satu kelas ke kelas lainnya secara massal.</p>
</div>

<?php if ($message): ?>
<script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
<?php endif; ?>

<div class="lux-card p-10 bg-white max-w-2xl">
    <form action="" method="POST" class="space-y-8">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="space-y-4 p-6 rounded-2xl bg-slate-50 border border-slate-100">
                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest">Kelas Asal</label>
                <select name="kelas_asal" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                    <option value="">-- Pilih Kelas Asal --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <p class="text-[10px] text-slate-400 italic">Siswa yang saat ini terdaftar di kelas ini.</p>
            </div>

            <div class="flex items-center justify-center">
                <div class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-xl shadow-inner">
                    <i class="fa fa-arrow-right"></i>
                </div>
            </div>

            <div class="space-y-4 p-6 rounded-2xl bg-indigo-50/50 border border-indigo-100">
                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest">Kelas Tujuan</label>
                <select name="kelas_tujuan" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                    <option value="">-- Pilih Kelas Tujuan --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
                <p class="text-[10px] text-indigo-400 italic">Target kelas baru untuk para siswa.</p>
            </div>
        </div>

        <div class="pt-6 border-t border-slate-100">
            <button type="submit" name="proses_naik" onclick="return confirm('Apakah Anda yakin ingin memindahkan seluruh siswa ke kelas tujuan?')"
                class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-2xl shadow-2xl transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-3">
                <i class="fa fa-sync-alt"></i> Proses Kenaikan Kelas
            </button>
            <p class="text-center text-[10px] text-slate-400 mt-4 uppercase tracking-widest font-black">Tahun Pelajaran: <?= htmlspecialchars($active_tahun_id ? mysqli_fetch_assoc(mysqli_query($conn, "SELECT tahun FROM tahun_pelajaran WHERE id = $active_tahun_id"))['tahun'] : '-') ?></p>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
