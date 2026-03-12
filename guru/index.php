<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/database.php';

authorize_role(['guru', 'admin']);

$user_id = $_SESSION['user_id'];
$guru_res = mysqli_query($conn, "SELECT id, foto FROM guru WHERE user_id = $user_id");
$g_data = mysqli_fetch_assoc($guru_res);
$guru_id = $g_data['id'];
$guru_foto = $g_data['foto'];

// Stats for Dashboard
$total_jurnal = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM jurnal WHERE guru_id = $guru_id"))['total'];
$hadir_avg = mysqli_fetch_assoc(mysqli_query($conn, "SELECT AVG(jml_hadir) as avg FROM jurnal WHERE guru_id = $guru_id"))['avg'];
$recent_jurnals = mysqli_query($conn, "SELECT j.*, k.nama_kelas, mp.nama_mapel FROM jurnal j JOIN kelas k ON j.kelas_id = k.id JOIN mata_pelajaran mp ON j.mapel_id = mp.id WHERE j.guru_id = $guru_id ORDER BY j.tanggal DESC LIMIT 5");

$page_title = "Beranda Guru";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
    #sidebar, header { display: none; }
    .lg\:ml-64 { margin-left: 0; }
    body { background-color: #F8FAFC; }
</style>

<div class="bg-slate-50 min-h-screen pb-24">
    <!-- Main Content -->
    <div class="p-8 lg:p-12 max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-12">
            <div class="flex items-center gap-6">
                <div class="w-20 h-20 rounded-2xl bg-indigo-600 border-4 border-white shadow-xl overflow-hidden flex items-center justify-center">
                    <?php if(!empty($guru_foto)): ?>
                        <img src="<?= BASE_URL ?>uploads/guru/<?= $guru_foto ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <i class="fa fa-user-tie text-white text-3xl"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-3xl font-black text-slate-800 tracking-tight italic">Beranda Guru <span class="text-indigo-600">(<?= htmlspecialchars($_SESSION['nama_lengkap']) ?>)</span></h1>
                    <p class="text-slate-400 font-medium tracking-wide"><?= date('l, d F Y') ?></p>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <button class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-all"><i class="fa fa-search"></i></button>
                <button class="w-10 h-10 rounded-full bg-white border border-slate-200 flex items-center justify-center text-slate-500 hover:bg-slate-50 transition-all relative">
                    <i class="fa fa-bell"></i>
                    <span class="absolute top-0 right-0 w-2.5 h-2.5 bg-rose-500 border-2 border-white rounded-full"></span>
                </button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-12">
            <div class="lux-card p-8 bg-indigo-600 text-white shadow-indigo-200 border-none relative overflow-hidden group">
                <div class="relative z-10">
                    <p class="text-indigo-100 text-xs font-black uppercase tracking-widest mb-4">Total Jurnal</p>
                    <h2 class="text-5xl font-black italic"><?= $total_jurnal ?></h2>
                    <p class="mt-4 text-xs font-bold bg-white/20 inline-block px-3 py-1 rounded-full">+12% dari bulan lalu</p>
                </div>
                <i class="fa fa-book-open absolute -bottom-4 -right-4 text-8xl opacity-10 group-hover:scale-110 transition-transform"></i>
            </div>
            <div class="lux-card p-8 bg-white border-slate-100">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest mb-4">Rata-rata Kehadiran</p>
                <h2 class="text-5xl font-black italic text-slate-800"><?= round($hadir_avg, 1) ?></h2>
                <div class="mt-6 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                    <div class="bg-emerald-500 h-full w-[85%] rounded-full"></div>
                </div>
            </div>
            <div class="lux-card p-8 bg-white border-slate-100 flex flex-col justify-center items-center text-center group cursor-pointer hover:border-indigo-500 transition-all">
                <a href="isi_absensi.php" class="contents">
                    <div class="w-16 h-16 rounded-2xl bg-slate-50 text-indigo-600 flex items-center justify-center mb-4 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-500 shadow-inner">
                        <i class="fa fa-user-check text-2xl"></i>
                    </div>
                    <p class="font-black text-slate-800 italic uppercase tracking-tighter">Mulai Absensi & Jurnal</p>
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Menu Grid -->
            <div class="grid grid-cols-2 gap-4">
                <a href="rekap_absen.php" class="lux-card p-6 flex flex-col items-center justify-center text-center gap-4 group hover:bg-emerald-600 transition-all duration-500">
                    <div class="w-14 h-14 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center shadow-sm group-hover:rotate-12 group-hover:bg-white transition-all"><i class="fa fa-chart-line text-xl"></i></div>
                    <span class="text-sm font-black text-slate-700 uppercase tracking-tighter group-hover:text-white">Rekap Absensi</span>
                </a>
                <a href="riwayat.php" class="lux-card p-6 flex flex-col items-center justify-center text-center gap-4 group hover:bg-amber-500 transition-all duration-500">
                    <div class="w-14 h-14 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center shadow-sm group-hover:rotate-12 group-hover:bg-white transition-all"><i class="fa fa-history text-xl"></i></div>
                    <span class="text-sm font-black text-slate-700 uppercase tracking-tighter group-hover:text-white">Riwayat Jurnal</span>
                </a>
                <a href="perangkat.php" class="lux-card p-6 flex flex-col items-center justify-center text-center gap-4 group hover:bg-indigo-600 transition-all duration-500">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center shadow-sm group-hover:rotate-12 group-hover:bg-white transition-all"><i class="fa fa-folder-open text-xl"></i></div>
                    <span class="text-sm font-black text-slate-700 uppercase tracking-tighter group-hover:text-white">Perangkat</span>
                </a>
                <a href="<?= BASE_URL ?>logout.php" class="lux-card p-6 flex flex-col items-center justify-center text-center gap-4 group hover:bg-rose-600 transition-all duration-500">
                    <div class="w-14 h-14 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center shadow-sm group-hover:rotate-12 group-hover:bg-white transition-all"><i class="fa fa-sign-out-alt text-xl"></i></div>
                    <span class="text-sm font-black text-slate-700 uppercase tracking-tighter group-hover:text-white">Keluar Sistem</span>
                </a>
            </div>

            <!-- Activity Table -->
            <div class="lux-card overflow-hidden border-none shadow-2xl">
                <div class="p-6 border-b border-slate-50 flex items-center justify-between">
                    <h3 class="font-black text-slate-800 italic uppercase tracking-widest text-sm">Aktivitas Terakhir</h3>
                    <a href="riwayat.php" class="text-xs font-bold text-indigo-600 hover:underline">Lihat Semua</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-[10px] font-black text-slate-400 uppercase tracking-widest border-b border-slate-50">
                                <th class="px-6 py-4">Tanggal</th>
                                <th class="px-6 py-4">Pelajaran</th>
                                <th class="px-6 py-4 text-center">Kelas</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-50">
                            <?php mysqli_data_seek($recent_jurnals, 0); while($j = mysqli_fetch_assoc($recent_jurnals)): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-4 text-xs font-bold text-slate-700"><?= date('d M', strtotime($j['tanggal'])) ?></td>
                                <td class="px-6 py-4 font-bold text-slate-800 text-sm italic"><?= htmlspecialchars($j['nama_mapel']) ?></td>
                                <td class="px-6 py-4 text-center">
                                    <span class="px-2 py-1 rounded bg-slate-100 text-slate-500 text-[10px] font-black uppercase border border-slate-200"><?= $j['nama_kelas'] ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
