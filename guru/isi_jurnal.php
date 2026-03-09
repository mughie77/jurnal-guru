<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
if(mysqli_num_rows($guru_res) == 0) die("Error: Data guru tidak ditemukan.");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

$mapels = mysqli_query($conn, "SELECT mp.id, mp.nama_mapel FROM mata_pelajaran mp JOIN guru_mapel gm ON mp.id = gm.mapel_id WHERE gm.guru_id = $guru_id ORDER BY mp.nama_mapel");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $stmt = mysqli_prepare($conn, "INSERT INTO jurnal (guru_id, mapel_id, kelas_id, tahun_pelajaran_id, tanggal, jam_ke, materi, jml_hadir, jml_sakit, jml_izin, jml_alfa, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiiisssiiiis", $guru_id, $_POST['mapel_id'], $_POST['kelas_id'], $active_tahun_id, $_POST['tanggal'], $_POST['jam_ke'], $_POST['materi'], $_POST['jml_hadir'], $_POST['jml_sakit'], $_POST['jml_izin'], $_POST['jml_alfa'], $_POST['keterangan']);
    if (mysqli_stmt_execute($stmt)) {
        $message = "Jurnal berhasil disimpan!"; $message_type = 'success';
    } else {
        $message = "Error: " . mysqli_error($conn); $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header { display: none; } .lg\:ml-64 { margin-left: 0; }</style>

<div class="max-w-4xl mx-auto pb-20">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-bold text-slate-800 tracking-tight">Isi Jurnal Baru</h1>
            <p class="text-slate-500 font-medium"><?= date('l, d F Y') ?></p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <div class="lux-card p-8 bg-white/80 backdrop-blur-md">
        <form action="" method="POST" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Mata Pelajaran</label>
                    <select name="mapel_id" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                        <option value="">-- Pilih Mapel --</option>
                        <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Kelas</label>
                    <select name="kelas_id" id="kelas_id" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 bg-white">
                        <option value="">-- Pilih Kelas --</option>
                        <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                            <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Tanggal</label>
                    <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50">
                </div>
                <div>
                    <label class="block text-sm font-bold text-slate-700 mb-2">Jam Ke-</label>
                    <input type="text" name="jam_ke" placeholder="Contoh: 1-3" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50">
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Materi Pembelajaran</label>
                <textarea name="materi" rows="4" placeholder="Tuliskan pokok bahasan hari ini..." required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></textarea>
            </div>

            <div class="bg-slate-50 rounded-3xl p-6 border border-slate-100">
                <h4 class="text-slate-800 font-bold mb-6 flex items-center">
                    <i class="fa fa-users mr-3 text-indigo-500"></i> Kehadiran Siswa
                </h4>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Hadir</label>
                        <input type="number" name="jml_hadir" id="jml_hadir" value="0" readonly class="w-full px-4 py-3 rounded-xl bg-slate-200 text-slate-600 font-bold text-center border-none">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Sakit</label>
                        <input type="number" name="jml_sakit" id="jml_sakit" value="0" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-center">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Izin</label>
                        <input type="number" name="jml_izin" id="jml_izin" value="0" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-center">
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold text-slate-500 uppercase">Alfa</label>
                        <input type="number" name="jml_alfa" id="jml_alfa" value="0" class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50 font-bold text-center">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-bold text-slate-700 mb-2">Keterangan (Opsional)</label>
                <textarea name="keterangan" rows="2" class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-50"></textarea>
            </div>

            <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-4 rounded-2xl shadow-xl shadow-indigo-200 transition-all transform hover:-translate-y-1 active:scale-[0.98]">
                <i class="fa fa-save mr-2"></i> Simpan Catatan Jurnal
            </button>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const kSelect = document.getElementById('kelas_id');
    const hInput = document.getElementById('jml_hadir');
    const sInput = document.getElementById('jml_sakit');
    const iInput = document.getElementById('jml_izin');
    const aInput = document.getElementById('jml_alfa');
    let totalSiswa = 0;

    const calc = () => {
        const val = totalSiswa - (parseInt(sInput.value)||0) - (parseInt(iInput.value)||0) - (parseInt(aInput.value)||0);
        hInput.value = Math.max(0, val);
    };

    kSelect.addEventListener('change', function() {
        if (!this.value) return;
        fetch(`<?= BASE_URL ?>api/get_jumlah_siswa.php?kelas_id=${this.value}`)
            .then(r => r.json()).then(d => { totalSiswa = d.total_siswa; calc(); });
    });
    [sInput, iInput, aInput].forEach(el => el.addEventListener('input', calc));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
