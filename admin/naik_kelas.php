<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['admin']);
$page_title = "Kenaikan Kelas";
$message = ''; $message_type = '';

if (!$active_tahun_id) {
    die("Error: Tidak ada tahun pelajaran aktif. <a href='tahun_pelajaran.php'>Atur di sini</a>");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['proses_naik']) || isset($_POST['proses_lulus']))) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) die("CSRF Token Invalid");

    $kelas_asal = (int)$_POST['kelas_asal'];

    if (isset($_POST['proses_naik'])) {
        $kelas_tujuan = (int)$_POST['kelas_tujuan'];
        if ($kelas_asal == $kelas_tujuan) {
            $message = "Gagal: Kelas asal dan tujuan tidak boleh sama."; $message_type = 'error';
        } else {
            mysqli_begin_transaction($conn);
            try {
                $query_siswa = mysqli_query($conn, "SELECT siswa_id FROM siswa_kelas WHERE kelas_id = $kelas_asal AND tahun_pelajaran_id = $active_tahun_id");
                $count = 0;
                while ($s = mysqli_fetch_assoc($query_siswa)) {
                    $sid = $s['siswa_id'];
                    mysqli_query($conn, "UPDATE siswa_kelas SET kelas_id = $kelas_tujuan WHERE siswa_id = $sid AND tahun_pelajaran_id = $active_tahun_id");
                    $count++;
                }
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
    } elseif (isset($_POST['proses_lulus'])) {
        mysqli_begin_transaction($conn);
        try {
            // Lulus: Hapus dari siswa_kelas tahun aktif (atau tandai sebagai alumni)
            // Di sistem ini, alumni dideteksi jika tidak memiliki kelas di tahun aktif.
            $query_siswa = mysqli_query($conn, "SELECT siswa_id FROM siswa_kelas WHERE kelas_id = $kelas_asal AND tahun_pelajaran_id = $active_tahun_id");
            $count = 0;
            while ($s = mysqli_fetch_assoc($query_siswa)) {
                $sid = $s['siswa_id'];
                mysqli_query($conn, "DELETE FROM siswa_kelas WHERE siswa_id = $sid AND tahun_pelajaran_id = $active_tahun_id");
                // Tandai di tabel siswa bahwa dia sudah alumni (opsional, bisa lewat query joins saja)
                $count++;
            }
            // Update counts for kelas asal
            $qL = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas sk JOIN siswa s ON sk.siswa_id = s.id WHERE sk.kelas_id = $kelas_asal AND sk.tahun_pelajaran_id = $active_tahun_id AND s.jenis_kelamin = 'L'");
            $jL = mysqli_fetch_assoc($qL)['jml'];
            $qP = mysqli_query($conn, "SELECT COUNT(*) as jml FROM siswa_kelas sk JOIN siswa s ON sk.siswa_id = s.id WHERE sk.kelas_id = $kelas_asal AND sk.tahun_pelajaran_id = $active_tahun_id AND s.jenis_kelamin = 'P'");
            $jP = mysqli_fetch_assoc($qP)['jml'];
            mysqli_query($conn, "UPDATE kelas SET jumlah_siswa_L = $jL, jumlah_siswa_P = $jP WHERE id = $kelas_asal");

            mysqli_commit($conn);
            $message = "Berhasil meluluskan $count siswa. Data mereka kini berada di menu Alumni."; $message_type = 'success';
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

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <div class="lux-card p-8 bg-white">
        <h3 class="text-xl font-black text-slate-800 italic uppercase mb-6 flex items-center gap-2">
            <i class="fa fa-level-up-alt text-indigo-500"></i> Kenaikan Kelas
        </h3>
        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <div class="space-y-4 p-5 rounded-2xl bg-slate-50 border border-slate-100">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Kelas Asal</label>
                <select name="kelas_asal" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                    <option value="">-- Pilih Kelas Asal --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="flex justify-center"><i class="fa fa-chevron-down text-slate-300"></i></div>

            <div class="space-y-4 p-5 rounded-2xl bg-indigo-50/50 border border-indigo-100">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Kelas Tujuan</label>
                <select name="kelas_tujuan" required class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                    <option value="">-- Pilih Kelas Tujuan --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" name="proses_naik" onclick="return confirm('Proses kenaikan kelas akan memindahkan semua siswa di kelas asal ke kelas tujuan. Lanjutkan?')"
                class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-black py-4 rounded-xl shadow-lg shadow-indigo-100 transition-all flex items-center justify-center gap-2">
                <i class="fa fa-sync-alt"></i> Proses Naik Kelas
            </button>
        </form>
    </div>

    <div class="lux-card p-8 bg-white border-rose-100">
        <h3 class="text-xl font-black text-slate-800 italic uppercase mb-6 flex items-center gap-2">
            <i class="fa fa-graduation-cap text-rose-500"></i> Kelulusan Siswa
        </h3>
        <p class="text-sm text-slate-500 mb-6 leading-relaxed italic">"Pilih kelas akhir (Misal Kelas XII) untuk memproses kelulusan secara massal. Siswa yang diluluskan akan dihapus dari daftar kelas aktif dan masuk ke database Alumni."</p>

        <form action="" method="POST" class="space-y-6">
            <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
            <div class="space-y-4 p-5 rounded-2xl bg-rose-50 border border-rose-100">
                <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest ml-1">Pilih Kelas yang Lulus</label>
                <select name="kelas_asal" required class="w-full px-4 py-3 rounded-xl border border-rose-200 outline-none focus:ring-4 focus:ring-rose-50 bg-white">
                    <option value="">-- Pilih Kelas --</option>
                    <?php mysqli_data_seek($kelases, 0); while($k = mysqli_fetch_assoc($kelases)): ?>
                        <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>

            <button type="submit" name="proses_lulus" onclick="return confirm('PERHATIAN: Siswa yang diluluskan akan dipindahkan ke Alumni. Tindakan ini permanen untuk tahun ajaran ini. Lanjutkan?')"
                class="w-full bg-rose-600 hover:bg-rose-700 text-white font-black py-4 rounded-xl shadow-lg shadow-rose-100 transition-all flex items-center justify-center gap-2">
                <i class="fa fa-check-double"></i> Proses Kelulusan
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
