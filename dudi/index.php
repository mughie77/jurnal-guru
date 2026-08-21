<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['dudi', 'admin']);

$user_id = $_SESSION['user_id'];

// Get Tempat PKL assigned to this DU/DI user
$res_tp = mysqli_query($conn, "SELECT tp.*, u.nama_lengkap as nama_guru_pembimbing
                               FROM tempat_pkl tp
                               LEFT JOIN guru g ON tp.guru_pembimbing_id = g.id
                               LEFT JOIN users u ON g.user_id = u.id
                               WHERE tp.user_dudi_id = $user_id
                               LIMIT 1");
$tempat_pkl = mysqli_fetch_assoc($res_tp);
$tempat_pkl_id = $tempat_pkl['id'] ?? 0;

// Stats
$total_siswa = 0;
$jurnal_pending = 0;
$jurnal_total = 0;

if ($tempat_pkl_id > 0) {
    $total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa_pkl WHERE tempat_pkl_id = $tempat_pkl_id AND status = 'aktif' AND tahun_pelajaran_id = $active_tahun_id"))['total'] ?? 0;
    $jurnal_pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal_pkl WHERE tempat_pkl_id = $tempat_pkl_id AND status_verifikasi = 'pending'"))['total'] ?? 0;
    $jurnal_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal_pkl WHERE tempat_pkl_id = $tempat_pkl_id"))['total'] ?? 0;
}

$page_title = "Beranda Pembimbing DU/DI";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <div class="lux-card p-8 mb-8 bg-gradient-to-br from-slate-900 to-indigo-950 text-white shadow-2xl rounded-3xl relative overflow-hidden">
        <div class="relative z-10">
            <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20 text-indigo-300">Portal Pembimbing DU/DI</span>
            <h1 class="text-3xl font-black italic tracking-tight mt-3 mb-1"><?= htmlspecialchars($tempat_pkl['nama_tempat'] ?? 'Pembimbing Industri / DU/DI') ?></h1>
            <p class="text-xs text-slate-300 italic"><?= htmlspecialchars($tempat_pkl['alamat'] ?? 'Selamat Datang di Portal Pembimbing DU/DI') ?></p>
            <?php if ($tempat_pkl): ?>
            <div class="mt-4 pt-4 border-t border-white/10 flex flex-wrap gap-4 text-xs font-bold text-slate-300">
                <div><i class="fa fa-user-tie text-indigo-400 mr-1.5"></i> Guru Pembimbing Sekolah: <span class="text-white"><?= htmlspecialchars($tempat_pkl['nama_guru_pembimbing'] ?? '-') ?></span></div>
                <div><i class="fa fa-id-badge text-emerald-400 mr-1.5"></i> Pembimbing Industri: <span class="text-white"><?= htmlspecialchars($tempat_pkl['pembimbing_dudi'] ?? '-') ?></span></div>
            </div>
            <?php endif; ?>
        </div>
        <i class="fa fa-briefcase absolute -bottom-6 -right-6 text-9xl opacity-10"></i>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <a href="siswa.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-indigo-200 transition-all flex items-center justify-between group">
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Siswa PKL Murid</p>
                <h2 class="text-4xl font-black text-slate-800 italic"><?= $total_siswa ?></h2>
                <p class="text-xs text-indigo-600 font-bold mt-2 group-hover:underline">Lihat Daftar Siswa <i class="fa fa-arrow-right text-[10px]"></i></p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl group-hover:bg-indigo-600 group-hover:text-white transition-all shadow-inner">
                <i class="fa fa-user-graduate"></i>
            </div>
        </a>

        <a href="jurnal.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-amber-200 transition-all flex items-center justify-between group">
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Jurnal Pending</p>
                <h2 class="text-4xl font-black text-amber-500 italic"><?= $jurnal_pending ?></h2>
                <p class="text-xs text-amber-600 font-bold mt-2 group-hover:underline">Verifikasi Jurnal <i class="fa fa-arrow-right text-[10px]"></i></p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl group-hover:bg-amber-500 group-hover:text-white transition-all shadow-inner">
                <i class="fa fa-book-open"></i>
            </div>
        </a>

        <a href="nilai.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-emerald-200 transition-all flex items-center justify-between group">
            <div>
                <p class="text-[10px] font-black uppercase text-slate-400 tracking-widest mb-1">Penilaian PKL</p>
                <h2 class="text-2xl font-black text-emerald-600 italic uppercase tracking-wider">Input Nilai</h2>
                <p class="text-xs text-emerald-600 font-bold mt-2 group-hover:underline">Beri Nilai Murid <i class="fa fa-arrow-right text-[10px]"></i></p>
            </div>
            <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl group-hover:bg-emerald-600 group-hover:text-white transition-all shadow-inner">
                <i class="fa fa-star"></i>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
