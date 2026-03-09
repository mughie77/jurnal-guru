<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_id = mysqli_fetch_assoc($guru_res)['id'];

if (!$active_tahun_id) die("Error: Tidak ada tahun pelajaran aktif.");

$mapels = mysqli_query($conn, "SELECT mp.id, mp.nama_mapel FROM mata_pelajaran mp JOIN guru_mapel gm ON mp.id = gm.mapel_id WHERE gm.guru_id = $guru_id ORDER BY mp.nama_mapel");
$kelases = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jurnal'])) {
    $stmt = mysqli_prepare($conn, "INSERT INTO jurnal (guru_id, mapel_id, kelas_id, tahun_pelajaran_id, tanggal, jam_ke, materi, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiiissss", $guru_id, $_POST['mapel_id'], $_POST['kelas_id'], $active_tahun_id, $_POST['tanggal'], $_POST['jam_ke'], $_POST['materi'], $_POST['keterangan']);
    if (mysqli_stmt_execute($stmt)) {
        $jid = mysqli_insert_id($conn);
        $message = "Jurnal berhasil disimpan!"; $message_type = 'success';
        // Redirect to attendance directly
        header("Location: isi_absensi.php?success=1");
        exit;
    } else {
        $message = "Error: " . mysqli_error($conn); $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header { display: none; } .lg\:ml-64 { margin-left: 0; }</style>

<div class="max-w-4xl mx-auto pb-20 px-4">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Isi Jurnal Mengajar</h1>
            <p class="text-slate-500 font-medium tracking-wide"><?= date('l, d F Y') ?></p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <?php if ($message): ?>
    <script>Swal.fire({ icon: '<?= $message_type ?>', title: '<?= ucfirst($message_type) ?>', text: '<?= $message ?>' });</script>
    <?php endif; ?>

    <div class="lux-card p-10 bg-white shadow-2xl">
        <form action="" method="POST" class="space-y-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Mata Pelajaran</label>
                        <select name="mapel_id" required class="w-full px-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700 transition-all">
                            <option value="">-- Pilih Mapel --</option>
                            <?php while($m = mysqli_fetch_assoc($mapels)): ?>
                                <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nama_mapel']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-black text-slate-400 uppercase tracking-[0.2em] mb-3 ml-1">Kelas</label>
                        <select name="kelas_id" required class="w-full px-4 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 bg-white font-bold text-slate-700 transition-all">
                            <option value="">-- Pilih Kelas --</option>
                            <?php while($k = mysqli_fetch_assoc($kelases)): ?>
                                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['nama_kelas']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Tanggal</label>
                            <input type="date" name="tanggal" value="<?= date('Y-m-d') ?>" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-slate-700 transition-all">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Jam Ke-</label>
                            <input type="text" name="jam_ke" placeholder="1-3" required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-bold text-slate-700 transition-all">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Materi Pembelajaran</label>
                        <textarea name="materi" rows="3" placeholder="Tuliskan pokok bahasan hari ini..." required class="w-full px-4 py-3 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-slate-600 transition-all"></textarea>
                    </div>
                </div>
            </div>

            <div class="pt-6 border-t border-slate-100 flex flex-col md:flex-row items-center gap-6">
                <div class="flex-1 w-full">
                    <label class="block text-[10px] font-black text-slate-400 uppercase tracking-widest mb-2 ml-1">Keterangan Tambahan (Opsional)</label>
                    <input type="text" name="keterangan" placeholder="Contoh: 2 siswa terlambat..." class="w-full px-4 py-3 rounded-xl border border-slate-200 outline-none focus:ring-4 focus:ring-slate-100">
                </div>
                <button type="submit" name="simpan_jurnal" class="w-full md:w-auto px-12 py-4 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-2xl shadow-2xl shadow-indigo-100 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-3">
                    <i class="fa fa-save text-lg"></i> SIMPAN JURNAL
                </button>
            </div>
        </form>
    </div>

    <div class="mt-8 p-6 rounded-3xl bg-slate-50 border border-slate-100 flex items-center italic">
        <div class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center mr-4 shrink-0"><i class="fa fa-info-circle"></i></div>
        <p class="text-xs text-slate-500 font-medium leading-relaxed">Setelah menyimpan jurnal, sistem akan otomatis mengarahkan Anda ke halaman absensi untuk mencatat kehadiran siswa pada jam pelajaran tersebut.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
