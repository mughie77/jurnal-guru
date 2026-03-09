<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$jurnal_id = (int)($_GET['jid'] ?? 0);
if ($jurnal_id <= 0) {
    // If accessed directly without ID, redirect to attendance
    header("Location: isi_absensi.php");
    exit;
}

$res_j = mysqli_query($conn, "SELECT j.*, mp.nama_mapel, k.nama_kelas
                               FROM jurnal j
                               JOIN mata_pelajaran mp ON j.mapel_id = mp.id
                               JOIN kelas k ON j.kelas_id = k.id
                               WHERE j.id = $jurnal_id");
$j = mysqli_fetch_assoc($res_j);

$message = ''; $message_type = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan_jurnal'])) {
    $materi = mysqli_real_escape_string($conn, $_POST['materi']);
    $keterangan = mysqli_real_escape_string($conn, $_POST['keterangan']);

    if (mysqli_query($conn, "UPDATE jurnal SET materi = '$materi', keterangan = '$keterangan' WHERE id = $jurnal_id")) {
        $message = "Jurnal berhasil disimpan!"; $message_type = 'success';
        header("Location: riwayat.php?success=1");
        exit;
    } else {
        $message = "Error: " . mysqli_error($conn); $message_type = 'error';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<style>#sidebar, header, nav.navbar { display: none !important; } .lg\:ml-64 { margin-left: 0 !important; } .main-content { margin-left: 0 !important; padding-top: 2rem !important; }</style>

<div class="max-w-4xl mx-auto pb-32 px-4">
    <!-- Progress Indicator -->
    <div class="flex items-center gap-2 mb-10 overflow-hidden rounded-full bg-slate-200 h-2">
        <div class="w-1/2 h-full bg-emerald-500"></div>
        <div class="w-1/2 h-full bg-indigo-500 animate-pulse"></div>
    </div>

    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Langkah 2: Isi Jurnal</h1>
            <p class="text-slate-500 font-medium tracking-wide uppercase text-[10px] tracking-[0.3em]">Finalisasi Catatan Mengajar</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <div class="lux-card p-10 bg-white shadow-2xl relative overflow-hidden">
        <div class="absolute top-0 left-0 w-1 h-full bg-indigo-600"></div>

        <div class="mb-10 grid grid-cols-2 md:grid-cols-4 gap-6 pb-8 border-b border-slate-50">
            <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Mata Pelajaran</p><p class="font-bold text-slate-800 italic"><?= htmlspecialchars($j['nama_mapel']) ?></p></div>
            <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Kelas</p><p class="font-bold text-slate-800"><?= htmlspecialchars($j['nama_kelas']) ?></p></div>
            <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Jam Ke-</p><p class="font-bold text-slate-800"><?= htmlspecialchars($j['jam_ke']) ?></p></div>
            <div><p class="text-[10px] font-black text-slate-400 uppercase tracking-widest mb-1">Kehadiran</p><p class="font-bold text-emerald-600"><?= $j['jml_hadir'] ?> Siswa</p></div>
        </div>

        <form action="" method="POST" class="space-y-8">
            <div>
                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Materi Pembahasan Hari Ini</label>
                <textarea name="materi" rows="5" required placeholder="Jelaskan pokok bahasan, kompetensi dasar, atau aktivitas yang dilakukan..." class="w-full px-6 py-4 rounded-3xl border border-slate-200 outline-none focus:ring-4 focus:ring-indigo-100 font-medium text-slate-600 text-lg transition-all"></textarea>
            </div>

            <div>
                <label class="block text-xs font-black text-slate-400 uppercase tracking-widest mb-3 ml-1">Catatan Tambahan (Opsional)</label>
                <input type="text" name="keterangan" placeholder="Contoh: Media pembelajaran yang digunakan, kendala, dll." class="w-full px-6 py-4 rounded-2xl border border-slate-200 outline-none focus:ring-4 focus:ring-slate-100 font-medium text-slate-500">
            </div>

            <button type="submit" name="simpan_jurnal" class="w-full py-5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-3xl shadow-2xl shadow-indigo-200 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center justify-center gap-3 tracking-widest text-lg">
                <i class="fa fa-save text-xl"></i> SELESAIKAN JURNAL
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
