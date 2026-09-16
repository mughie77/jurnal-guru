<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id FROM guru WHERE user_id = $user_id");
$guru_data = mysqli_fetch_assoc($guru_res);
$guru_id = $guru_data['id'] ?? 0;

// Verify supervisor status
$q_pkl_guru = mysqli_query($conn, "SELECT COUNT(*) as total_tempat FROM tempat_pkl WHERE guru_pembimbing_id = $guru_id");
$total_tempat = (mysqli_fetch_assoc($q_pkl_guru)['total_tempat'] ?? 0);

if ($total_tempat == 0 && $_SESSION['role'] !== 'admin') {
    die("Akses ditolak: Anda belum ditugaskan sebagai Guru Pembimbing PKL.");
}

// Stats
$total_siswa = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM siswa_pkl sp JOIN tempat_pkl tp ON sp.tempat_pkl_id = tp.id WHERE tp.guru_pembimbing_id = $guru_id AND sp.tahun_pelajaran_id = $active_tahun_id AND sp.status = 'aktif'"))['total'] ?? 0;
$total_jurnal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal_pkl jp JOIN tempat_pkl tp ON jp.tempat_pkl_id = tp.id WHERE tp.guru_pembimbing_id = $guru_id"))['total'] ?? 0;

$page_title = "Portal Pembimbing PKL";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="p-4 sm:p-6 lg:p-8 max-w-7xl mx-auto pb-24">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Portal Pembimbing PKL</h1>
            <p class="text-slate-500 font-medium">Pusat kendali dan monitoring siswa bimbingan Praktik Kerja Lapangan.</p>
        </div>
        <a href="index.php" class="w-12 h-12 flex items-center justify-center rounded-2xl bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition-all shadow-sm">
            <i class="fa fa-arrow-left"></i>
        </a>
    </div>

    <!-- Overview Banner -->
    <div class="lux-card p-8 mb-10 bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white shadow-2xl rounded-3xl relative overflow-hidden">
        <div class="relative z-10">
            <span class="px-3 py-1 bg-white/10 rounded-full text-[10px] font-black uppercase tracking-widest border border-white/20 text-indigo-300">Guru Pembimbing PKL</span>
            <h2 class="text-3xl font-black italic tracking-tight mt-3 mb-2"><?= htmlspecialchars($_SESSION['nama_lengkap']) ?></h2>
            <div class="flex flex-wrap gap-6 text-xs font-bold text-slate-300 mt-4 pt-4 border-t border-white/10">
                <div><i class="fa fa-building text-indigo-400 mr-2"></i> Lokasi Industri Bimbingan: <span class="text-white font-black"><?= $total_tempat ?> Tempat</span></div>
                <div><i class="fa fa-user-graduate text-emerald-400 mr-2"></i> Total Siswa Bimbingan: <span class="text-white font-black"><?= $total_siswa ?> Siswa</span></div>
                <div><i class="fa fa-book-open text-amber-400 mr-2"></i> Total Laporan Jurnal: <span class="text-white font-black"><?= $total_jurnal ?> Laporan</span></div>
            </div>
        </div>
        <i class="fa fa-briefcase absolute -bottom-8 -right-8 text-9xl opacity-10"></i>
    </div>

    <!-- Tile Menu Options for PKL Supervisor -->
    <h2 class="text-xl font-black text-slate-800 italic uppercase tracking-wider mb-6 flex items-center gap-2">
        <i class="fa fa-th-large text-indigo-600"></i> Menu Pembimbing PKL
    </h2>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Tile 1: Daftar Siswa -->
        <a href="pkl_siswa.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-indigo-500 transition-all flex flex-col justify-between group hover:scale-[1.02] active:scale-95">
            <div>
                <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all shadow-inner">
                    <i class="fa fa-users"></i>
                </div>
                <h3 class="text-lg font-black text-slate-800 italic group-hover:text-indigo-600 transition-colors">Daftar Siswa PKL</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Lihat seluruh siswa bimbingan PKL yang ditugaskan kepada Anda.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-50 flex items-center justify-between text-xs font-bold text-indigo-600">
                <span>Buka Daftar Siswa</span>
                <i class="fa fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- Tile 2: Rekap Absensi -->
        <a href="pkl_absensi.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-emerald-500 transition-all flex flex-col justify-between group hover:scale-[1.02] active:scale-95">
            <div>
                <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mb-4 group-hover:bg-emerald-600 group-hover:text-white transition-all shadow-inner">
                    <i class="fa fa-user-check"></i>
                </div>
                <h3 class="text-lg font-black text-slate-800 italic group-hover:text-emerald-600 transition-colors">Rekap Absensi GPS</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Pantau presensi lokasi GPS harian siswa di industri tempat PKL.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-50 flex items-center justify-between text-xs font-bold text-emerald-600">
                <span>Lihat Presensi</span>
                <i class="fa fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- Tile 3: Jurnal Siswa -->
        <a href="pkl_jurnal.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-amber-500 transition-all flex flex-col justify-between group hover:scale-[1.02] active:scale-95">
            <div>
                <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mb-4 group-hover:bg-amber-500 group-hover:text-white transition-all shadow-inner">
                    <i class="fa fa-book-open"></i>
                </div>
                <h3 class="text-lg font-black text-slate-800 italic group-hover:text-amber-600 transition-colors">Jurnal PKL Siswa</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Monitoring laporan kegiatan harian & verifikasi dari DU/DI.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-50 flex items-center justify-between text-xs font-bold text-amber-600">
                <span>Pantau Jurnal</span>
                <i class="fa fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>

        <!-- Tile 4: Pointing Lokasi PKL -->
        <a href="pkl_lokasi.php" class="lux-card p-6 bg-white shadow-xl rounded-3xl border border-slate-100 hover:border-rose-500 transition-all flex flex-col justify-between group hover:scale-[1.02] active:scale-95">
            <div>
                <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-2xl mb-4 group-hover:bg-rose-600 group-hover:text-white transition-all shadow-inner">
                    <i class="fa fa-map-marker-alt"></i>
                </div>
                <h3 class="text-lg font-black text-slate-800 italic group-hover:text-rose-600 transition-colors">Lokasi & Geofence</h3>
                <p class="text-xs text-slate-500 font-medium mt-1 leading-relaxed">Atur titik presisi koordinat GPS dan radius absensi industri.</p>
            </div>
            <div class="mt-6 pt-4 border-t border-slate-50 flex items-center justify-between text-xs font-bold text-rose-600">
                <span>Atur Lokasi</span>
                <i class="fa fa-arrow-right group-hover:translate-x-1 transition-transform"></i>
            </div>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
